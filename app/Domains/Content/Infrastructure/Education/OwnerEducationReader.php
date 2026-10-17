<?php

namespace App\Domains\Content\Infrastructure\Education;

use App\Domains\Content\Application\Education\ArticleInput;
use App\Domains\Content\Domain\Education\ArticleDocument;
use Illuminate\Support\Facades\DB;

final class OwnerEducationReader
{
    public static function filters(array $input): array
    {
        ArticleDocument::only($input, ['page', 'revision', 'state', 'q', 'product_q', 'product_page', 'concern_q', 'concern_page', 'category_q', 'category_page']);
        $out = validator($input, ['page' => 'sometimes|integer|between:1,1000', 'state' => 'sometimes|in:published,draft,archived', 'q' => 'sometimes|nullable|string|max:100', 'revision' => 'sometimes|string'])->validate();
        if (isset($out['revision'])) {
            $out['revision'] = ArticleInput::version($out['revision'], false);
        }
        foreach (['product', 'concern', 'category'] as $kind) {
            $v = validator($input, [$kind.'_page' => 'sometimes|integer|between:1,1000', $kind.'_q' => 'sometimes|nullable|string|max:100'])->validate();
            $out = array_replace($out, $v);
        }
        foreach (['q', 'product_q', 'concern_q', 'category_q'] as $key) {
            if (isset($out[$key])) {
                $out[$key] = trim(ArticleDocument::text($out[$key], 100));
            }
        }

        return $out;
    }

    public function articles(array $f)
    {
        EducationalStore::ready();
        $q = DB::table('educational_articles as a')->join('educational_article_revisions as r', function ($j): void {
            $j->on('r.article_id', '=', 'a.id')->on('r.version', '=', 'a.lock_version');
        });
        if (isset($f['state'])) {
            match ($f['state']) {
                'archived' => $q->whereNotNull('a.archived_at'),
                'published' => $q->whereNull('a.archived_at')->whereNotNull('a.published_version'),
                default => $q->whereNull('a.archived_at')->whereNull('a.published_version'),
            };
        }
        if (! empty($f['q'])) {
            self::search($q, 'r.title', $f['q']);
        }

        return $q->orderByDesc('a.created_at')->orderBy('a.id')->paginate(25, ['a.id', 'a.slug', 'a.lock_version', 'a.published_version', 'a.archived_at', 'r.title'], 'page', $f['page'] ?? 1);
    }

    public function article(?string $id, array $f): array
    {
        EducationalStore::ready();

        return DB::transaction(function () use ($id, $f): array {
            $record = $id ? DB::table('educational_articles')->where('id', $id)->first() : null;
            abort_if($id && ! $record, 404);
            $draft = $record ? EducationalStore::revision($id, (string) $record->lock_version) : ['title' => '', 'excerpt' => null, 'seo_title' => null, 'meta_description' => null, 'category_id' => null, 'editorial_scope' => null, 'is_featured' => false, 'sort_order' => 0, 'body' => ArticleDocument::empty(), 'products' => [], 'concerns' => [], 'sources' => [], 'images' => []];
            $history = DB::table('educational_article_revisions as r')->join('management_users as o', 'o.id', '=', 'r.owner_id')->where('article_id', $id)->orderByDesc('version')->paginate(25, ['version', 'action', 'r.created_at', 'o.display_name'], 'page', $f['page'] ?? 1);
            $snapshot = isset($f['revision']) && $record ? EducationalStore::revision($id, $f['revision']) : null;
            $published = $record?->published_version ? EducationalStore::revision($id, (string) $record->published_version) : null;
            $choices = [];
            foreach (['product', 'concern', 'category'] as $kind) {
                $table = $kind === 'category' ? 'educational_categories' : $kind.'s';
                $query = DB::table($table);
                if ($kind === 'product') {
                    $query->join('brands as b', 'b.id', '=', 'products.brand_id')->where('b.status', 'active')->whereNull('b.deleted_at');
                }
                $query->whereNull($table.'.'.($kind === 'category' ? 'archived_at' : 'deleted_at'))->where($table.'.'.($kind === 'category' ? 'is_active' : 'status'), $kind === 'category' ? true : 'active');
                if (! empty($f[$kind.'_q'])) {
                    self::search($query, $table.'.name', $f[$kind.'_q']);
                }
                $rows = $query->orderBy($table.'.name')->orderBy($table.'.id')->offset((($f[$kind.'_page'] ?? 1) - 1) * 50)->limit(50)->get([$table.'.id', $table.'.name'])->keyBy('id');
                $selected = $kind === 'category' ? array_filter([$draft['category_id']]) : array_column($draft[$kind.'s'], 'id');
                $retained = DB::table($table)->whereIn('id', $selected)->get(['id', 'name'])->keyBy('id');
                $choices[$kind] = $rows->union($retained)->values();
            }

            return compact('record', 'draft', 'history', 'snapshot', 'published', 'choices');
        });
    }

    public static function search($query, string $column, string $text): void
    {
        $query->whereRaw($column." LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%']);
    }
}
