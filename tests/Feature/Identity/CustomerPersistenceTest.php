<?php

namespace Tests\Feature\Identity;

use App\Domains\Identity\Application\Actions\AuthenticateGoogleCustomer;
use App\Domains\Identity\Application\DTOs\GoogleIdentityData;
use App\Domains\Identity\Application\Ports\CustomerIdentityRepository;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\IdentityTestCase;

class CustomerPersistenceTest extends IdentityTestCase
{
    public function test_mysql_schema_contains_only_identity_columns_and_required_indexes(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('sumaya_pharma_test', DB::connection()->getDatabaseName());
        $this->assertSame(['id', 'google_sub', 'name', 'email', 'avatar_url', 'account_status', 'last_login_at', 'created_at', 'updated_at'], Schema::getColumnListing('customers'));
        $indexes = Schema::getIndexes('customers');
        $this->assertCount(3, $indexes);
        $this->assertTrue(Schema::hasIndex('customers', ['id'], 'primary'));
        $this->assertTrue(Schema::hasIndex('customers', ['google_sub'], 'unique'));
        $this->assertTrue(Schema::hasIndex('customers', ['email']));
        $emailIndex = collect($indexes)->firstWhere('columns', ['email']);
        $this->assertFalse($emailIndex['unique']);
        foreach (['users', 'password_reset_tokens', 'personal_access_tokens', 'cache', 'jobs', 'failed_jobs'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_customer_uses_uuidv7_status_cast_and_hides_google_identity(): void
    {
        $customer = $this->customer();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $customer->id);
        $this->assertFalse($customer->getIncrementing());
        $this->assertSame('string', $customer->getKeyType());
        $this->assertSame(CustomerAccountStatus::Active, $customer->fresh()->account_status);
        $this->assertArrayNotHasKey('google_sub', $customer->toArray());
    }

    public function test_mysql_enforces_google_sub_uniqueness(): void
    {
        $this->customer();
        $this->expectException(UniqueConstraintViolationException::class);
        $this->customer();
    }

    public function test_create_or_first_recovers_duplicate_insert_without_creating_second_customer(): void
    {
        $repository = app(CustomerIdentityRepository::class);
        $first = $repository->createOrFirst($this->identity());
        $second = $repository->createOrFirst($this->identity());
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_same_email_never_links_different_google_subjects(): void
    {
        $action = app(AuthenticateGoogleCustomer::class);
        $first = $action->execute($this->identity('subject-one'));
        $second = $action->execute($this->identity('subject-two'));
        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_existing_subject_updates_profile_and_login_time_without_changing_identity(): void
    {
        $this->travelTo(now()->startOfSecond());
        $action = app(AuthenticateGoogleCustomer::class);
        $first = $action->execute($this->identity());
        $this->travel(1)->minutes();
        $second = $action->execute(new GoogleIdentityData('google-123', 'Updated', 'new@example.test', null));
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Updated', $second->name);
        $this->assertSame('new@example.test', $second->email);
        $this->assertNull($second->avatarUrl);
        $this->assertGreaterThan($first->lastLoginAt, $second->lastLoginAt);
        $this->assertSame('google-123', Customer::query()->sole()->google_sub);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_suspended_subject_is_rejected_without_profile_or_status_changes(): void
    {
        $customer = $this->customer(CustomerAccountStatus::Suspended);
        try {
            app(AuthenticateGoogleCustomer::class)->execute(new GoogleIdentityData('google-123', 'Changed', 'new@example.test', null));
            $this->fail('Suspended customer was accepted.');
        } catch (AccountSuspended) {
            $this->assertSame('Customer', $customer->fresh()->name);
            $this->assertSame(CustomerAccountStatus::Suspended, $customer->fresh()->account_status);
        }
    }
}
