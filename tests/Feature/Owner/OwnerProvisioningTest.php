<?php

namespace Tests\Feature\Owner;

use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\ManagementIdentity\Interfaces\Console\ProvisionOwnerCommand;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\CatalogTestCase;

class OwnerProvisioningTest extends CatalogTestCase
{
    public function test_hash_looking_password_is_hashed_as_literal_input_not_imported(): void
    {
        $literal = Hash::make('Underlying-fixture-passphrase');
        $owner = app(ProvisionOwner::class)->execute('Fixture', 'literal@example.test', $literal, $literal);
        $this->assertTrue(Hash::check($literal, $owner->password));
        $this->assertFalse(Hash::check('Underlying-fixture-passphrase', $owner->password));
        $this->assertNotSame($literal, $owner->password);
    }

    public function test_noninteractive_invocation_refuses_before_prompting(): void
    {
        $command = app(ProvisionOwnerCommand::class);
        $command->setLaravel(app());
        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([], ['interactive' => false]));
        $this->assertStringContainsString('Interactive trusted terminal access is required.', $tester->getDisplay());
        $this->assertDatabaseCount('management_users', 0);
        $this->assertFalse($command->getDefinition()->hasOption('password'));
    }

    public function test_missing_schema_refuses_without_hidden_prompt_or_fallback(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('management_users')->andReturn(false);
        $this->artisan('owner:provision')->expectsOutput('Owner schema missing or owner already provisioned. No account created.')->assertFailed();
        $this->assertDatabaseCount('management_users', 0);
    }

    public function test_interactive_command_hashes_password_normalizes_identity_and_preserves_customers(): void
    {
        $this->customer();
        $before = DB::table('customers')->get()->toArray();
        $password = 'Synthetic-safe-passphrase-2026';
        $this->assertDatabaseCount('management_users', 0);
        $this->artisan('owner:provision')->expectsQuestion('اسم المالكة', '  سمية  ')->expectsQuestion('البريد الإلكتروني', ' OWNER@example.test ')
            ->expectsQuestion('كلمة المرور (12 محرفاً على الأقل)', $password)->expectsQuestion('تأكيد كلمة المرور', $password)
            ->expectsOutput('تم إنشاء حساب المالكة بأمان.')->doesntExpectOutput($password)->assertSuccessful();
        $owner = ManagementUser::query()->sole();
        $this->assertSame('سمية', $owner->display_name);
        $this->assertSame('owner@example.test', $owner->email);
        $this->assertTrue(Hash::check($password, $owner->password));
        $this->assertNotSame($password, $owner->password);
        $this->assertSame('7', substr($owner->id, 14, 1));
        $this->assertSame('1', $owner->credential_version);
        $this->artisan('owner:provision')->expectsOutput('Owner schema missing or owner already provisioned. No account created.')->assertFailed();
        $this->assertDatabaseCount('management_users', 1);
        $this->assertEquals($before, DB::table('customers')->get()->toArray());
    }

    public static function invalidProvisioning(): array
    {
        return ['email' => ['Name', 'invalid', 'Valid-passphrase-2026', 'Valid-passphrase-2026'], 'weak' => ['Name', 'a@example.test', 'short', 'short'],
            'confirmation' => ['Name', 'a@example.test', 'Valid-passphrase-2026', 'different'], 'name' => ['!!!', 'a@example.test', 'Valid-passphrase-2026', 'Valid-passphrase-2026'],
            'unicode-too-long' => ['Name', 'a@example.test', str_repeat('أ', 40), str_repeat('أ', 40)]];
    }

    #[DataProvider('invalidProvisioning')]
    public function test_invalid_provisioning_never_creates_default_account(string $name, string $email, string $password, string $confirmation): void
    {
        try {
            app(ProvisionOwner::class)->execute($name, $email, $password, $confirmation);
            $this->fail('Invalid provisioning must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('management_users', 0);
        }
    }

    public function test_real_constraints_single_owner_security_version_guarded_fields_and_owner_lifecycle(): void
    {
        $owner = app(ProvisionOwner::class)->execute('سمية', 'one@example.test', 'عبارة مرور آمنة طويلة', 'عبارة مرور آمنة طويلة');
        $row = (array) DB::table('management_users')->sole();
        unset($row['owner_slot']);
        $row['id'] = (string) Str::uuid7();
        $row['email'] = 'two@example.test';
        $this->assertConstraint(fn () => DB::table('management_users')->insert($row), 1062);
        $this->assertConstraint(fn () => DB::table('management_users')->update(['credential_version' => 0]), 3819);
        $this->assertFalse(Schema::hasColumn('management_users', 'remember_token'));
        $this->assertFalse(Schema::hasColumn('management_users', 'password_confirmation'));
        try {
            $owner->forceFill(['role' => 'restricted'])->save();
            $this->fail('Owner demotion must fail.');
        } catch (\LogicException) {
            $this->assertSame('owner', $owner->fresh()->role);
        }
        try {
            $owner->delete();
            $this->fail('Owner deletion must fail.');
        } catch (\LogicException) {
            $this->assertDatabaseCount('management_users', 1);
        }
        $this->expectException(MassAssignmentException::class);
        $owner->fill(['password' => 'forged']);
    }
}
