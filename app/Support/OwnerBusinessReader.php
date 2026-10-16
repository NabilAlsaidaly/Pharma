<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OwnerBusinessReader
{
    public function pagination(Request $request): array
    {
        abort_if(array_diff(array_keys($request->query()), ['page', 'revision']) !== [], 422);

        return validator($request->query(), ['page' => ['sometimes', 'integer', 'min:1', 'max:1000'], 'revision' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,19}$/D']])->validate();
    }

    public function listing(array $spec, int $page)
    {
        RevisionedStore::requireSchema($spec);
        $title = array_values(array_intersect(['name', 'title', 'question', 'mode', 'first_currency'], array_keys($spec['fields'])))[0];

        $summary = ['id', $title, 'lock_version', 'published_version', 'archived_at', 'created_at'];
        if ($spec['media'] ?? false) {
            $summary[] = 'image_path';
        }
        if (isset($spec['fields']['fee_usd'])) {
            $summary = [...$summary, 'governorate', 'city', 'fee_usd', 'fee_syp'];
        }

        return DB::table($spec['table'])->select($summary)
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25, ['*'], 'page', $page)->withQueryString();
    }

    public function detail(array $spec, ?string $id, array $filters): array
    {
        RevisionedStore::requireSchema($spec);

        return DB::transaction(function () use ($spec, $id, $filters): array {
            $query = DB::table($spec['table']);
            $record = $spec['singleton'] ? $query->where('singleton_key', 1)->first() : ($id === null ? null : $query->where('id', $id)->first());
            abort_if(! $spec['singleton'] && $id !== null && $record === null, 404);
            $history = DB::table($spec['table'].'_revisions as r')->join('management_users as u', 'u.id', '=', 'r.owner_id')->where('r.record_id', $record->id ?? '')
                ->select(['r.id', 'r.version', 'r.action', 'r.owner_id', 'r.created_at', 'u.display_name'])->orderByDesc('r.version')->paginate(25, ['*'], 'page', (int) ($filters['page'] ?? 1))->withQueryString();
            $snapshot = null;
            if (isset($filters['revision'])) {
                $snapshot = DB::table($spec['table'].'_revisions')->where('record_id', $record->id ?? '')->where('version', $filters['revision'])->first();
                abort_if($snapshot === null, 404);
            }

            return compact('record', 'history', 'snapshot');
        });
    }
}
