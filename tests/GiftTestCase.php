<?php

namespace Tests;

use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Gifts\Application\SubmitGiftRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class GiftTestCase extends OwnerDashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function giftCommand(string $version = '0', string $action = 'save', array $extra = []): array
    {
        return array_replace(['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'action' => $action] + (in_array($action, ['publish', 'unpublish', 'archive', 'restore'], true) ? ['confirm' => '1'] : []), $extra);
    }

    protected function gift(array $extra = [], bool $publish = true): string
    {
        $id = app(ManageGiftPackage::class)->execute(null, $this->owner, $this->giftCommand(extra: array_replace(['slug' => 'gift-'.Str::lower(Str::random(8)), 'values' => ['name' => 'باقة العناية', 'price_usd' => '50.00', 'price_syp' => '650000.00', 'availability' => 'available'], 'components' => [['type' => 'manual', 'name' => 'علبة هدية', 'quantity' => 1, 'sort_order' => 0]]], $extra)));
        if ($publish) {
            app(ManageGiftPackage::class)->execute($id, $this->owner, $this->giftCommand('1', 'media', ['alt_text' => 'صورة باقة العناية']), UploadedFile::fake()->image('gift.png', 20, 30));
            app(ManageGiftPackage::class)->execute($id, $this->owner, $this->giftCommand('2', 'publish'));
        }

        return $id;
    }

    protected function giftInput(string $id, array $extra = []): array
    {
        return array_replace(['operation_id' => (string) Str::uuid7(), 'package_id' => $id, 'expected_published_version' => (string) DB::table('gift_packages')->where('id', $id)->value('published_version'), 'currency' => 'USD', 'recipient' => ['full_name' => 'مستلم تجريبي', 'phone' => '+963 123456789', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'عنوان خاص للاختبار', 'notes' => null]], $extra);
    }

    protected function submitGift(string $id, array $extra = []): array
    {
        $customer = $this->customer();

        return app(SubmitGiftRequest::class)->execute($customer->id, $this->giftInput($id, $extra));
    }
}
