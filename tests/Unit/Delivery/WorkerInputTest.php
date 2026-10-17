<?php

namespace Tests\Unit\Delivery;

use App\Domains\Delivery\Application\ManageDeliveryWorker;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkerInputTest extends TestCase
{
    public static function invalid(): array
    {
        return array_map(fn ($data) => [$data], [
            ['owner_id' => 'forged'], ['operation_id' => 1], ['operation_id' => 'not-uuid'], ['expected_version' => 0], ['expected_version' => '01'], ['expected_version' => '1e1'], ['expected_version' => '-1'], ['expected_version' => '18446744073709551615'], ['expected_version' => '18446744073709551616'], ['action' => 'assign'], ['action' => 'delete'], ['action' => 'disable'], ['values' => 'x'], ['values' => ['salary' => '10']], ['values' => ['name' => null]], ['values' => ['name' => ' ']], ['values' => ['name' => str_repeat('ن', 151)]], ['values' => ['name' => '<script>x</script>']], ['values' => ['phone' => '123x']], ['values' => ['phone' => '12']], ['values' => ['phone' => str_repeat('1', 41)]], ['values' => ['notes' => str_repeat('ن', 1001)]], ['values' => ['notes' => "x\x00y"]], ['values' => ['is_active' => 'true']], ['values' => ['is_active' => 1]], ['confirm' => '1'], ['action' => 'enable', 'confirm' => '1', 'values' => ['name' => 'غير مسموح']],
        ]);
    }

    #[DataProvider('invalid')]
    public function test_invalid_authority_versions_and_fixed_fields_are_rejected(array $overrides): void
    {
        try {
            app(ManageDeliveryWorker::class)->input(array_replace(['operation_id' => '019a0123-4567-7000-8000-000000000001', 'expected_version' => '0', 'action' => 'save'], $overrides));
            $this->fail('Invalid input accepted.');
        } catch (ValidationException|HttpException $e) {
            $this->assertTrue($e instanceof ValidationException || $e->getStatusCode() === 422);
        }
    }

    public function test_unicode_phone_null_notes_boolean_and_exact_large_version_are_normalized(): void
    {
        $input = app(ManageDeliveryWorker::class)->input(['operation_id' => '019a0123-4567-7000-8000-000000000001', 'expected_version' => '18446744073709551614', 'action' => 'save', 'values' => ['name' => ' عامل عربي ', 'phone' => '+٩٦٣ (١٢٣)-٤٥٦', 'notes' => ' ', 'is_active' => false]]);
        $this->assertSame('عامل عربي', $input['values']['name']);
        $this->assertNull($input['values']['notes']);
        $this->assertFalse($input['values']['is_active']);
        $this->assertSame('18446744073709551614', $input['expected_version']);
    }
}
