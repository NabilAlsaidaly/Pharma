<?php

namespace App\Domains\Catalog\Application\Administration;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogChange
{
    public function __construct(public readonly ManagementUser $owner, public readonly string $requestId, public readonly ?string $bulkId = null)
    {
        abort_unless($owner->exists && $owner->role === 'owner' && $owner->status === 'active', 403);
    }

    public function record(string $type, Model $record, string $action, array $before, array $after): void
    {
        // Only explicit changed Catalog values; no model serialization or customer/account data.
        DB::table('catalog_audit_events')->insert([
            'id' => (string) Str::uuid7(),
            'owner_id' => $this->owner->getKey(),
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'action' => $action,
            'request_id' => substr($this->requestId, 0, 100),
            'bulk_id' => $this->bulkId,
            'before_values' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function save(string $type, Model $record, string $action, array $values): void
    {
        $before = $record->exists ? $record->only(array_keys($values)) : [];
        $record->fill($values);
        if ($record->exists) {
            CatalogRecords::advance($record);
        }
        $record->save();
        $this->record($type, $record, $action, $before, $record->only(array_keys($values)));
    }
}
