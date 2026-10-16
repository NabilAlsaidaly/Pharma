<?php

namespace Tests;

use App\Domains\Content\Application\ManageStoreContent;
use App\Domains\Settings\Application\ManageStoreSettings;
use Illuminate\Support\Str;

abstract class StoreConfigurationTestCase extends OwnerDashboardTestCase
{
    protected function command(array $values = [], string $version = '0', string $action = 'save', ?string $operation = null): array
    {
        return ['operation_id' => $operation ?? (string) Str::uuid7(), 'expected_version' => $version, 'action' => $action, 'values' => $values] + ($action !== 'save' && $action !== 'media' ? ['confirm' => '1'] : []);
    }

    protected function setting(string $group = 'profile', array $values = ['name' => 'متجر اختباري'], string $version = '0', ?string $id = null, string $action = 'save'): string
    {
        return app(ManageStoreSettings::class)->execute($group, $id, $this->owner, $this->command($values, $version, $action));
    }

    protected function content(string $type = 'pages', array $values = ['title' => 'صفحة اختبار', 'slug' => 'about-fixture', 'body' => 'نص عربي واضح'], string $version = '0', ?string $id = null, string $action = 'save'): string
    {
        return app(ManageStoreContent::class)->execute($type, $id, $this->owner, $this->command($values, $version, $action));
    }
}
