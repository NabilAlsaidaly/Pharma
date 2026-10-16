<?php

namespace App\Domains\Gifts\Application;

use App\Domains\Gifts\Domain\GiftRules;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManageGiftOccasion
{
    public function execute(?string $id, ManagementUser $owner, array $input): string
    {
        GiftStore::ready();
        GiftInput::only($input, ['operation_id', 'expected_version', 'action', 'confirm', 'values']);
        abort_if(array_key_exists('values', $input) && ! is_array($input['values']), 422);
        $op = GiftInput::operation($input['operation_id'] ?? null);
        $version = GiftInput::version($input['expected_version'] ?? null);
        $action = $input['action'] ?? '';
        abort_unless(in_array($action, ['save', 'archive', 'restore'], true), 422);
        abort_if($action !== 'save' && (! in_array($input['confirm'] ?? null, ['1', 1, true], true) || ! empty($input['values'])), 422);
        $values = GiftInput::text($input['values'] ?? [], ['name' => ['اسم المناسبة', 160], 'description' => ['الوصف', 500], 'sort_order' => ['الترتيب', 9, 'order'], 'is_active' => ['الإظهار', 1]]);
        abort_if(isset($values['is_active']) && ! in_array($values['is_active'], ['0', '1'], true), 422);
        ksort($values);
        $fingerprint = GiftInput::fingerprint([$id, $version, $action, $values]);

        return DB::transaction(function () use ($id, $owner, $op, $version, $action, $values, $fingerprint): string {
            GiftStore::actor($owner);
            if ($prior = GiftStore::replay($owner->id, $op, $fingerprint)) {
                return $prior;
            }
            $old = $id ? DB::table('gift_occasions')->where('id', $id)->lockForUpdate()->first() : null;
            abort_if($id && ! $old, 404);
            abort_unless((string) ($old->lock_version ?? '0') === $version, 409, 'تغيرت المناسبة؛ حدّثي الصفحة.');
            abort_if(! $old && $action !== 'save', 409);
            abort_if($old && ($action === 'restore' ? $old->archived_at === null : $old->archived_at !== null), 409);
            $next = array_replace(['name' => $old->name ?? null, 'description' => $old->description ?? null, 'sort_order' => $old->sort_order ?? 0, 'is_active' => $old->is_active ?? 0], $values);
            abort_if(empty($next['name']) || $next['sort_order'] === null || $next['is_active'] === null, 422);
            if ($action !== 'save') {
                $next['is_active'] = 0;
            }
            $target = $old->id ?? (string) Str::uuid7();
            $next += ['lock_version' => GiftRules::next($version), 'archived_at' => $action === 'archive' ? now() : null, 'updated_at' => now()];
            if ($old) {
                DB::table('gift_occasions')->where('id', $target)->update($next);
            } else {
                DB::table('gift_occasions')->insert($next + ['id' => $target, 'created_at' => now()]);
            }
            GiftStore::audit($owner->id, $op, $fingerprint, 'occasion', $target, $action, $version, $next['lock_version'], array_keys($values));

            return $target;
        }, 3);
    }
}
