<?php

namespace Tests\Feature\OwnerDashboard;

use App\Domains\ManagementDashboard\Application\GlobalSearch;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\OwnerDashboardTestCase;

class OwnerDashboardSearchTest extends OwnerDashboardTestCase
{
    public function test_arabic_search_includes_hidden_resources_and_links_to_protected_real_details(): void
    {
        $brand = $this->brand(['name' => 'عناية عربية', 'status' => 'hidden']);
        $product = $this->product(['name' => 'عناية للبشرة', 'status' => 'hidden'], $brand);
        $concern = $this->concern(['name' => 'عناية وترطيب', 'status' => 'hidden']);
        $this->ownerLogin();
        $response = $this->get(route('owner.search', ['q' => '  عناية  ']))->assertOk();
        $this->assertSame('عناية', $response->viewData('term'));
        foreach (['products' => $product, 'brands' => $brand, 'concerns' => $concern] as $key => $record) {
            $response->assertSee($record->name)->assertSee(route('owner.'.$key.'.show', ['id' => $record->id]), false);
            $this->get(route('owner.'.$key.'.show', ['id' => $record->id]))->assertOk();
        }
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
    }

    public function test_request_identifier_search_is_safe_and_never_scans_contact_snapshots(): void
    {
        $id = $this->requestFixture();
        $this->ownerLogin();
        $response = $this->get(route('owner.search', ['q' => strtoupper($id)]))->assertOk()->assertSee($id);
        foreach (['Private-contact-name-sentinel', 'private-contact-sentinel@example.test', 'Private-address-sentinel', 'Private-notes-sentinel', 'private-google-sub-sentinel'] as $private) {
            $response->assertDontSee($private);
        }
        $this->get(route('owner.search', ['q' => 'Private-contact']))->assertOk()->assertDontSee($id);
        $response = $this->get(route('owner.requests.show', ['id' => $id]))->assertOk()->assertSee('Private-address-sentinel')->assertSee('Private-contact-name-sentinel')->assertSee('كما سُجلت عند إرسال الطلب');
        $response->assertDontSee(DB::table('checkout_intents')->where('id', $id)->value('request_fingerprint'));
    }

    public function test_literal_wildcards_sql_bindings_and_xss_never_broaden_or_execute(): void
    {
        $exact = $this->product(['name' => 'عناية %_! خاصة']);
        $other = $this->product(['name' => 'عناية أخرى']);
        $this->ownerLogin();
        $response = $this->get(route('owner.search', ['q' => 'عناية %_!']))->assertOk();
        $this->assertCount(1, $response->viewData('groups')['products']['results']);
        $this->assertSame($exact->id, $response->viewData('groups')['products']['results'][0]->id);
        $this->get(route('owner.search', ['q' => "x' OR 1=1 --"]))->assertOk()->assertDontSee($other->name);
        $xss = $this->product(['name' => '<script>alert(1)</script> عناية']);
        $this->get(route('owner.search', ['q' => 'script']))->assertOk()->assertSee($xss->name)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_high_cardinality_output_is_stable_ten_per_group_and_reads_only_explicit_sources(): void
    {
        $term = substr($this->requestFixture(), 0, 4);
        for ($i = 0; $i < 12; $i++) {
            $this->requestFixture();
        }
        for ($i = 0; $i < 15; $i++) {
            $brand = $this->brand(['name' => $term]);
            $this->product(['name' => $term], $brand);
            $this->concern(['name' => $term]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = app(GlobalSearch::class)->execute($term);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $second = app(GlobalSearch::class)->execute($term);
        foreach (['products', 'brands', 'concerns', 'requests'] as $key) {
            $this->assertCount(10, $first[$key]['results']);
            $this->assertSame(array_column($first[$key]['results'], 'id'), array_column($second[$key]['results'], 'id'));
        }
        $this->assertSame(40, array_sum(array_map(fn ($group) => count($group['results'] ?? []), $first)));
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('management_users', $query['query']);
            $this->assertStringNotContainsString('google_sub', $query['query']);
            $this->assertStringNotContainsString('full_name', $query['query']);
            $this->assertStringNotContainsString('request_fingerprint', $query['query']);
            $this->assertStringStartsWith('select', $query['query']);
        }
    }

    public static function invalidTerms(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['  '],
            'short' => ['أ'],
            'large' => [str_repeat('ب', 101)],
            'wildcards-only' => ['%%_'],
            'array' => [['term']],
            'control' => ["name\0secret"],
        ];
    }

    #[DataProvider('invalidTerms')]
    public function test_bad_search_input_is_rejected_before_search(array|string $term): void
    {
        $this->ownerLogin();
        $this->get(route('owner.search', ['q' => $term]))->assertUnprocessable()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }
}
