<?php

namespace App\Domains\Content\Application\Education;

use App\Domains\Content\Domain\Education\ArticleDocument;
use App\Support\BusinessInput;
use Brick\Math\BigInteger;
use Illuminate\Support\Str;

final class ArticleInput
{
    public const FIELDS = ['title' => ['العنوان', 160], 'excerpt' => ['المقتطف', 500], 'seo_title' => ['عنوان SEO', 160], 'meta_description' => ['وصف SEO', 300]];

    public static function version(mixed $v, bool $advancing = true): string
    {
        abort_unless(is_string($v) && preg_match('/^(0|[1-9][0-9]{0,19})$/D', $v) && BigInteger::of($v)->isLessThanOrEqualTo('18446744073709551615') && (! $advancing || $v !== '18446744073709551615'), 422, 'صيغة الإصدار غير صالحة.');

        return $v;
    }

    public static function next(string $v): string
    {
        return (string) BigInteger::of(self::version($v))->plus(1);
    }

    public static function uuid(mixed $id): string
    {
        abort_unless(is_string($id) && Str::isUuid($id), 422, 'المعرّف غير صالح.');

        return strtolower($id);
    }

    public static function envelope(array $command, bool $category = false): array
    {
        abort_if(array_key_exists('values', $command) && ! is_array($command['values']), 422);
        ArticleDocument::only($command, $category ? ['operation_id', 'expected_version', 'action', 'slug', 'values', 'confirm'] : ['operation_id', 'expected_version', 'action', 'slug', 'values', 'body', 'products', 'concerns', 'sources', 'images', 'confirm', 'review_acknowledged', 'health_acknowledged', 'role', 'alt_text']);
        $op = self::uuid($command['operation_id'] ?? null);
        abort_unless(in_array($op[14], ['4', '7'], true), 422);
        $version = self::version($command['expected_version'] ?? null);
        $action = $command['action'] ?? null;
        abort_unless(in_array($action, $category ? ['save', 'archive', 'restore'] : ['save', 'media', 'publish', 'unpublish', 'archive', 'restore'], true), 422);
        if (in_array($action, ['publish', 'unpublish', 'archive', 'restore'], true)) {
            abort_unless(in_array($command['confirm'] ?? null, [true, '1', 1], true), 422, 'تأكيد الإجراء مطلوب.');
        } else {
            abort_if(isset($command['confirm']), 422);
        }
        if ($action !== 'save') {
            abort_if(array_intersect(array_keys($command), ['values', 'body', 'products', 'concerns', 'sources', 'images', 'slug']) !== [], 422);
        }
        if ($action !== 'publish') {
            abort_if(isset($command['review_acknowledged']) || isset($command['health_acknowledged']), 422);
        }
        if ($action !== 'media') {
            abort_if(isset($command['role']) || isset($command['alt_text']), 422);
        }

        return [$op, $version, $action];
    }

    public static function values(array $input): array
    {
        ArticleDocument::only($input, [...array_keys(self::FIELDS), 'category_id', 'editorial_scope', 'is_featured', 'sort_order']);
        $v = BusinessInput::fields(array_intersect_key($input, self::FIELDS), ['fields' => self::FIELDS]);
        if (array_key_exists('category_id', $input)) {
            $v['category_id'] = $input['category_id'] === null ? null : self::uuid($input['category_id']);
        }
        if (array_key_exists('editorial_scope', $input)) {
            abort_unless(in_array($input['editorial_scope'], [null, 'general', 'health_related'], true), 422);
            $v['editorial_scope'] = $input['editorial_scope'];
        }
        if (array_key_exists('is_featured', $input)) {
            abort_unless(is_bool($input['is_featured']), 422);
            $v['is_featured'] = $input['is_featured'];
        }
        if (array_key_exists('sort_order', $input)) {
            $v['sort_order'] = self::order($input['sort_order']);
        }
        ksort($v);

        return $v;
    }

    public static function order(mixed $value): int
    {
        abort_unless(is_int($value) && $value >= 0 && $value <= 999999999, 422, 'الترتيب عدد صحيح ضمن الحد.');

        return $value;
    }

    public static function links(mixed $rows): array
    {
        abort_unless(is_array($rows) && array_is_list($rows) && count($rows) <= 20, 422);
        $out = [];
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422);
            ArticleDocument::only($row, ['id', 'sort_order']);
            $id = self::uuid($row['id'] ?? null);
            abort_if(isset($out[$id]), 422, 'العلاقة مكررة.');
            $out[$id] = ['id' => $id, 'sort_order' => self::order($row['sort_order'] ?? null)];
        }
        uasort($out, fn ($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);

        return array_values($out);
    }

    public static function sources(mixed $rows): array
    {
        abort_unless(is_array($rows) && array_is_list($rows) && count($rows) <= 10, 422);
        $out = [];
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422);
            ArticleDocument::only($row, ['title', 'url', 'note', 'sort_order']);
            $title = trim(ArticleDocument::text($row['title'] ?? null, 200));
            abort_if($title === '', 422);
            $url = ArticleDocument::url($row['url'] ?? null);
            $key = hash('sha256', $url);
            abort_if(isset($out[$key]), 422, 'المصدر مكرر.');
            $out[$key] = ['title' => $title, 'url' => $url, 'url_key' => $key, 'note' => isset($row['note']) ? ArticleDocument::text($row['note'], 500) : null, 'sort_order' => self::order($row['sort_order'] ?? null)];
        }
        uasort($out, fn ($a, $b) => [$a['sort_order'], $a['url_key']] <=> [$b['sort_order'], $b['url_key']]);

        return array_values($out);
    }

    public static function images(mixed $rows): array
    {
        abort_unless(is_array($rows) && array_is_list($rows) && count($rows) <= 6, 422);
        $out = [];
        $inline = 0;
        $cover = false;
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422);
            ArticleDocument::only($row, ['media_id', 'role', 'alt_text']);
            $id = self::uuid($row['media_id'] ?? null);
            abort_if(isset($out[$id]), 422);
            abort_unless(in_array($row['role'] ?? null, ['cover', 'inline'], true), 422);
            if ($row['role'] === 'cover') {
                abort_if($cover, 422, 'غلاف واحد فقط.');
                $cover = true;
            } else {
                abort_if(++$inline > 5, 422, 'حتى خمس صور داخلية.');
            }
            $alt = trim(ArticleDocument::text($row['alt_text'] ?? null, 250));
            abort_if($alt === '', 422, 'الوصف البديل مطلوب.');
            $out[$id] = ['media_id' => $id, 'role' => $row['role'], 'alt_text' => $alt, 'slot' => $row['role'] === 'cover' ? 0 : $inline];
        }

        return array_values($out);
    }

    public static function fingerprint(array $value): string
    {
        return hash('sha256', ArticleDocument::encode($value));
    }
}
