<?php

namespace App\Domains\Delivery\Application;

use App\Domains\Delivery\Infrastructure\DeliveryWorkers;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManageDeliveryWorker
{
    public function input(array $command): array
    {
        abort_if(array_diff(array_keys($command), ['operation_id', 'expected_version', 'action', 'values', 'confirm']) !== [], 422);
        $data = validator($command, ['operation_id' => ['required', 'string', 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[47][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D'], 'expected_version' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,19})$/D'], 'action' => ['required', 'in:save,disable,enable'], 'values' => ['sometimes', 'array:name,phone,notes,is_active'], 'confirm' => ($command['action'] ?? '') === 'save' ? ['prohibited'] : ['required', 'accepted']])->validate();
        abort_unless(BigInteger::of($data['expected_version'])->isLessThan('18446744073709551615'), 422);
        $values = $data['values'] ?? [];
        abort_if($data['action'] !== 'save' && $values !== [], 422);
        foreach (['name' => 150, 'phone' => 40, 'notes' => 1000] as $key => $max) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            if ($key === 'notes' && $values[$key] === null) {
                continue;
            }
            abort_unless(is_string($values[$key]) && mb_check_encoding($values[$key], 'UTF-8'), 422);
            $text = preg_replace('/^\s+|\s+$/u', '', $values[$key]);
            abort_if(mb_strlen($text) > $max || preg_match('/[\p{Cc}\p{Cf}<>]/u', $text), 422, 'النص غير صالح أو أطول من الحد المسموح.');
            abort_if($key !== 'notes' && ! preg_match('/[\p{L}\p{N}]/u', $text), 422, 'أكملي الاسم والهاتف.');
            $values[$key] = $key === 'notes' && $text === '' ? null : $text;
        }
        if (isset($values['phone'])) {
            abort_unless(preg_match('/^\+?[\p{Nd} ().\/-]+$/uD', $values['phone']) && preg_match('/(?:\p{Nd}[^\p{Nd}]*){3}/u', $values['phone']), 422, 'صيغة الهاتف غير صالحة.');
        }
        if (array_key_exists('is_active', $values)) {
            abort_unless(is_bool($values['is_active']), 422);
        }
        ksort($values);

        return ['operation_id' => $data['operation_id'], 'expected_version' => $data['expected_version'], 'action' => $data['action'], 'values' => $values];
    }

    public function execute(?string $id, ManagementUser $owner, array $command): string
    {
        DeliveryWorkers::ready();
        abort_if($id !== null && ! Str::isUuid($id), 422);
        $id = $id === null ? null : strtolower($id);
        $data = $this->input($command);
        $fingerprint = hash('sha256', json_encode([$id, $data['expected_version'], $data['action'], $data['values']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($id, $owner, $data, $fingerprint): string {
            // Same Owner -> target order as Settings; no Customer/Catalog/Order locks.
            $actor = ManagementUser::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'owner' && $actor->status === 'active' && $actor->credential_version === $owner->credential_version, 403);
            $prior = DB::table('delivery_worker_changes')->where('owner_id', $owner->id)->where('operation_id', $data['operation_id'])->lockForUpdate()->first();
            if ($prior !== null) {
                abort_unless(hash_equals($prior->payload_fingerprint, $fingerprint), 409, 'معرّف العملية مستخدم لبيانات مختلفة.');

                return $prior->worker_id;
            }
            $record = $id === null ? null : DB::table('delivery_workers')->where('id', $id)->lockForUpdate()->first();
            abort_if($id !== null && $record === null, 404);
            abort_unless((string) ($record->lock_version ?? '0') === $data['expected_version'], 409, 'تغير السجل؛ حدّثي الصفحة قبل الحفظ.');
            abort_if($record === null && $data['action'] !== 'save', 409);
            $values = $record ? array_intersect_key((array) $record, array_flip(['name', 'phone', 'notes', 'is_active'])) : ['notes' => null];
            $values = array_replace($values, $data['values']);
            if ($record !== null) {
                $values['is_active'] = (bool) $values['is_active'];
            }
            abort_unless(isset($values['name'], $values['phone'], $values['is_active']), 422, 'أكملي الاسم والهاتف وحالة النشاط.');
            if ($data['action'] !== 'save') {
                $active = $data['action'] === 'enable';
                abort_if($values['is_active'] === $active, 409, 'حالة النشاط تغيرت بالفعل.');
                $values['is_active'] = $active;
            }
            $target = $id ?? (string) Str::uuid7();
            $version = (string) BigInteger::of($data['expected_version'])->plus(1);
            $changed = array_values(array_filter(array_keys($values), fn ($key) => ($key === 'is_active' && $record ? (bool) $record->$key : ($record->$key ?? null)) !== $values[$key]));
            $row = $values + ['lock_version' => $version, 'updated_at' => now()];
            if ($record === null) {
                DB::table('delivery_workers')->insert($row + ['id' => $target, 'created_at' => now()]);
            } else {
                DB::table('delivery_workers')->where('id', $target)->update($row);
            }
            DB::table('delivery_worker_changes')->insert(['id' => (string) Str::uuid7(), 'worker_id' => $target, 'owner_id' => $owner->id, 'operation_id' => $data['operation_id'], 'payload_fingerprint' => $fingerprint, 'action' => $data['action'], 'previous_version' => $data['expected_version'], 'new_version' => $version, 'changed_fields' => json_encode($changed, JSON_THROW_ON_ERROR), 'request_id' => substr((string) request()->attributes->get('request_id'), 0, 100), 'created_at' => now()]);

            return $target;
        }, 3);
    }
}
