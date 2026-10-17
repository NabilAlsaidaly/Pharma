<?php

namespace Tests;

use App\Domains\Delivery\Application\ManageDeliveryWorker;

abstract class DeliveryTestCase extends StoreConfigurationTestCase
{
    protected function worker(array $values = [], string $version = '0', ?string $id = null, string $action = 'save'): string
    {
        return app(ManageDeliveryWorker::class)->execute($id, $this->owner, $this->command($values ?: ($id === null ? ['name' => 'عامل تجريبي', 'phone' => '+963123456789', 'notes' => 'ملاحظة خاصة', 'is_active' => true] : []), $version, $action));
    }

    protected function zone(array $values = [], bool $publish = true): string
    {
        $id = $this->setting('zones', $values + ['name' => 'منطقة تجريبية', 'governorate' => 'حماة', 'city' => null, 'fee_usd' => null, 'fee_syp' => '0.00', 'sort_order' => '0', 'conditions' => 'private-zone-conditions-sentinel']);
        if ($publish) {
            $this->setting('zones', [], '1', $id, 'publish');
        }

        return $id;
    }
}
