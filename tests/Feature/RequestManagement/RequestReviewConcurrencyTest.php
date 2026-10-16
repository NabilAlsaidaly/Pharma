<?php

namespace Tests\Feature\RequestManagement;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Application\Review\BulkReviewRequests;
use App\Domains\Orders\Application\Review\ReviewRequestAction;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use App\Domains\Orders\Infrastructure\Persistence\Queries\OwnerRequestReviewReader;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RequestReviewConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private migrateIsolated; }

    private ManagementUser $owner;

    private string $id;

    private string $customer;

    private array $input;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Review races require isolated physical MySQL.');
        }
        $this->migrateIsolated();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = app(ProvisionOwner::class)->execute('Race Owner', 'review-race@example.test', 'Review-race-fixture-passphrase', 'Review-race-fixture-passphrase');
        $brand = Brand::query()->create(['name' => 'Fixture', 'slug' => 'fixture', 'status' => 'active']);
        $product = Product::query()->create(['brand_id' => $brand->id, 'name' => 'Fixture', 'slug' => 'fixture', 'status' => 'active', 'availability_status' => 'available', 'price_usd' => '12']);
        $customer = Customer::query()->create(['name' => 'Customer', 'email' => 'fixture@example.test', 'google_sub' => 'review-fixture-sub']);
        $this->customer = $customer->id;
        app(CartOperations::class)->add($customer->id, $product->id, null, 1);
        $this->input = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => ['full_name' => 'Fixture Customer', 'phone' => '+1234567890', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'Fixture address']];
        $this->id = app(SubmitPreliminaryCheckoutRequest::class)->execute($this->customer, $this->input)['intent']->id;
    }

    private function action(array $extra = []): array
    {
        return array_replace(['action' => 'review', 'id' => $this->id, 'operation' => (string) Str::uuid7(), 'version' => '0', 'kind' => 'transition', 'target' => 'under_review'], $extra);
    }

    private function preview(array $ids): array
    {
        return ['versions' => array_fill_keys($ids, '0'), 'operations' => array_combine($ids, array_map(fn () => (string) Str::uuid7(), $ids)), 'filters' => ['review_status' => 'new'], 'target' => 'under_review', 'bulk_id' => (string) Str::uuid7(), 'scope' => 'matching'];
    }

    private function overlap(array $payloads, ?callable $mutation = null, string $table = 'checkout_intents', ?string $key = null): array
    {
        $dir = storage_path('framework/testing/review-race-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        $connections = [];
        DB::beginTransaction();
        DB::table($table)->where('id', $key ?? $this->id)->lockForUpdate()->first();
        try {
            foreach ($payloads as $i => $payload) {
                $ready = $dir.'/ready-'.$i;
                $file = $dir.'/payload-'.$i.'.json';
                file_put_contents($file, json_encode($payload + ['owner' => $this->owner->id, 'ready' => $ready], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/request-review-worker.php'), $file], base_path(), null, null, 45);
                $workers[] = $worker;
                $worker->start();
                $deadline = microtime(true) + 25;
                $blocked = false;
                do {
                    if (! $worker->isRunning()) {
                        $this->fail($worker->getErrorOutput().$worker->getOutput());
                    }
                    if (file_exists($ready)) {
                        $connection = (int) file_get_contents($ready);
                        $active = collect(DB::select('SHOW FULL PROCESSLIST'))->first(fn ($row) => (int) $row->Id === $connection);
                        $blocked = $active !== null && str_contains((string) $active->Info, '`'.$table.'`') && str_contains((string) $active->Info, 'for update');
                    }
                    if (! $blocked) {
                        usleep(10000);
                    }
                } while (! $blocked && microtime(true) < $deadline);
                $this->assertTrue($blocked, 'Independent worker SQL must wait on our held row.');
                $this->assertNotSame((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $connection);
                $connections[] = $connection;
            }
            $this->assertCount(count($payloads), array_unique($connections));
            if ($mutation) {
                $mutation();
            }
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'REVIEW_REAL_OVERLAP '.json_encode(['table' => $table, 'connections' => $connections, 'actions' => array_column($payloads, 'action'), 'results' => $results], JSON_THROW_ON_ERROR).PHP_EOL);

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($dir.'/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($dir);
        }
    }

    public function test_first_review_creation_competing_actions_create_exactly_one_aggregate(): void
    {
        $results = $this->overlap([$this->action(), $this->action()]);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['success'] ?? false)));
        $this->assertDatabaseCount('preliminary_request_reviews', 1);
        $this->assertDatabaseCount('preliminary_request_review_events', 1);
    }

    public function test_competing_transitions_same_version_have_one_commit_and_one_conflict(): void
    {
        app(ReviewRequestAction::class)->execute($this->id, $this->owner, (string) Str::uuid7(), '0', 'transition', 'under_review');
        $results = $this->overlap([$this->action(['version' => '1', 'target' => 'awaiting_contact']), $this->action(['version' => '1', 'target' => 'reviewed'])]);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['success'] ?? false)));
        $this->assertDatabaseCount('preliminary_request_review_events', 2);
        $this->assertSame(2, DB::table('preliminary_request_reviews')->sole()->lock_version);
    }

    public function test_same_note_operation_in_two_processes_commits_one_note_and_replays_same_event(): void
    {
        $payload = $this->action(['kind' => 'note', 'target' => null, 'note' => 'ملاحظة متزامنة واحدة']);
        $results = $this->overlap([$payload, $payload]);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertSame($results[0]['result']['event'], $results[1]['result']['event']);
        $this->assertDatabaseCount('preliminary_request_review_events', 1);
    }

    public function test_customer_committed_retry_overlaps_review_and_returns_original_immutable_request(): void
    {
        $facts = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()];
        $results = $this->overlap([['action' => 'submit', 'customer' => $this->customer, 'input' => $this->input]], fn () => app(ReviewRequestAction::class)->execute($this->id, $this->owner, (string) Str::uuid7(), '0', 'transition', 'under_review'));
        $this->assertSame($this->id, $results[0]['result']['id']);
        $this->assertFalse($results[0]['result']['created']);
        $this->assertSame($facts, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()]);
    }

    public function test_different_concurrent_notes_preserve_first_append_and_require_a_fresh_version_for_the_other(): void
    {
        $results = $this->overlap([$this->action(['kind' => 'note', 'target' => null, 'note' => 'ملاحظة أولى']), $this->action(['kind' => 'note', 'target' => null, 'note' => 'ملاحظة أخرى'])]);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['success'] ?? false)));
        $this->assertDatabaseCount('preliminary_request_review_events', 1);
        app(ReviewRequestAction::class)->execute($this->id, $this->owner, (string) Str::uuid7(), '1', 'note', note: 'إضافة بعد قراءة الإصدار الحالي');
        $this->assertDatabaseCount('preliminary_request_review_events', 2);
    }

    public function test_overlapping_bulk_and_individual_never_lose_update_or_partially_commit_batch(): void
    {
        $second = app(SubmitPreliminaryCheckoutRequest::class)->execute($this->customer, array_replace($this->input, ['operation_id' => (string) Str::uuid7()]))['intent']->id;
        $results = $this->overlap([['action' => 'bulk', 'preview' => $this->preview([$this->id, $second])], $this->action()]);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['success'] ?? false)));
        $count = DB::table('preliminary_request_review_events')->count();
        $this->assertSame(($results[0]['success'] ?? false) ? 2 : 1, $count);
        $this->assertSame($count, DB::table('preliminary_request_reviews')->count());
    }

    public function test_new_customer_request_during_pending_matching_confirmation_is_not_silently_included(): void
    {
        $preview = $this->preview([$this->id]);
        $results = $this->overlap([['action' => 'submit', 'customer' => $this->customer, 'input' => array_replace($this->input, ['operation_id' => (string) Str::uuid7()])]], table: 'customers', key: $this->customer);
        $new = $results[0]['result']['id'];
        $this->assertNotSame($this->id, $new);
        $this->assertSame(1, app(BulkReviewRequests::class)->execute($preview, $this->owner));
        $this->assertDatabaseMissing('preliminary_request_reviews', ['checkout_intent_id' => $new]);
        $this->assertDatabaseCount('preliminary_request_review_events', 1);
    }

    public function test_detail_reader_snapshot_stays_coherent_when_review_commits_between_its_queries(): void
    {
        $dir = storage_path('framework/testing/review-snapshot-'.Str::uuid7());
        mkdir($dir);
        $payload = ['action' => 'read_snapshot', 'id' => $this->id, 'owner' => $this->owner->id, 'ready' => $dir.'/ready', 'snapshot_ready' => $dir.'/snapshot', 'release' => $dir.'/release'];
        file_put_contents($dir.'/payload.json', json_encode($payload, JSON_THROW_ON_ERROR));
        $worker = new Process([PHP_BINARY, base_path('tests/Support/request-review-worker.php'), $dir.'/payload.json'], base_path(), null, null, 45);
        try {
            $worker->start();
            $deadline = microtime(true) + 25;
            while (! file_exists($payload['snapshot_ready']) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($payload['snapshot_ready']);
            app(ReviewRequestAction::class)->execute($this->id, $this->owner, (string) Str::uuid7(), '0', 'note', note: 'Committed while reader paused');
            file_put_contents($payload['release'], 'commit done');
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $old = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['result'];
            $this->assertSame(['version' => '0', 'events' => 0, 'submitted' => 'submitted'], $old);
            $current = app(OwnerRequestReviewReader::class)->detail($this->id);
            $this->assertSame(1, $current['review']->lock_version);
            $this->assertSame(1, $current['events']->total());
            fwrite(STDOUT, 'REVIEW_REAL_SNAPSHOT '.json_encode(['reader_connection' => (int) file_get_contents($payload['ready']), 'writer_connection' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id, 'writer_committed_before_reader_resumed' => true, 'old' => $old, 'new_version' => $current['review']->lock_version], JSON_THROW_ON_ERROR).PHP_EOL);
        } finally {
            if ($worker->isRunning()) {
                $worker->stop();
            }
            foreach (glob($dir.'/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($dir);
        }
    }
}
