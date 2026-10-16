<?php

namespace App\Domains\Gifts\Application;

use App\Domains\Gifts\Domain\GiftRules;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReviewGiftRequest
{
    public function execute(string $id, ManagementUser $owner, array $input): string
    {
        GiftStore::ready();
        GiftInput::only($input, ['operation_id', 'expected_version', 'kind', 'target', 'note']);
        $op = GiftInput::operation($input['operation_id'] ?? null);
        $version = GiftInput::version($input['expected_version'] ?? null);
        $kind = $input['kind'] ?? '';
        abort_unless(in_array($kind, ['transition', 'note', 'follow_up'], true), 422);
        $note = GiftInput::text(array_intersect_key($input, ['note' => true]), ['note' => ['الملاحظة', 2000]])['note'] ?? null;
        $target = $input['target'] ?? null;
        abort_if($kind !== 'transition' && ($target !== null || $note === null), 422);
        abort_if($kind === 'transition' && (! is_string($target) || ReviewStatus::tryFrom($target) === null), 422);
        $fingerprint = GiftInput::fingerprint([$id, $version, $kind, $target, $note]);

        return DB::transaction(function () use ($id, $owner, $op, $version, $kind, $target, $note, $fingerprint): string {
            GiftStore::actor($owner);
            if ($prior = GiftStore::replay($owner->id, $op, $fingerprint)) {
                return $prior;
            }
            abort_unless(DB::table('gift_requests')->where('id', $id)->lockForUpdate()->first(['id']) !== null, 404);
            $r = DB::table('gift_request_reviews')->where('request_id', $id)->lockForUpdate()->first();
            abort_unless((string) ($r->lock_version ?? '0') === $version, 409, 'تغيرت المراجعة؛ حدّثي الصفحة.');
            $before = ReviewStatus::from($r->review_status ?? 'new');
            $after = $kind === 'transition' ? ReviewStatus::from($target) : $before;
            if ($kind === 'transition') {
                abort_unless($before->allows($after), 409, 'الانتقال غير مسموح.');
                abort_if($before === ReviewStatus::Reviewed && $note === null, 422, 'سبب إعادة الفتح مطلوب.');
            }
            $rid = $r->id ?? (string) Str::uuid7();
            $nextVersion = GiftRules::next($version);
            $row = ['review_status' => $after->value, 'lock_version' => $nextVersion, 'last_activity_at' => now(),
                'review_started_at' => $r->review_started_at ?? ($kind === 'transition' ? now() : null),
                'review_completed_at' => $kind === 'transition' ? ($after === ReviewStatus::Reviewed ? now() : null) : ($r->review_completed_at ?? null),
                'last_owner_id' => $kind === 'transition' ? $owner->id : ($r->last_owner_id ?? null)];
            if ($r) {
                DB::table('gift_request_reviews')->where('id', $rid)->update($row);
            } else {
                DB::table('gift_request_reviews')->insert($row + ['id' => $rid, 'request_id' => $id]);
            }
            DB::table('gift_review_events')->insert(['id' => (string) Str::uuid7(), 'request_id' => $id, 'review_id' => $rid, 'owner_id' => $owner->id, 'kind' => $kind, 'previous_status' => $before->value, 'new_status' => $after->value, 'note' => $note, 'resulting_version' => $nextVersion, 'created_at' => now()]);
            GiftStore::audit($owner->id, $op, $fingerprint, 'review', $id, $kind, $version, $nextVersion, $kind === 'transition' ? ['review_status'] : ['private_note']);

            return $id;
        }, 3);
    }
}
