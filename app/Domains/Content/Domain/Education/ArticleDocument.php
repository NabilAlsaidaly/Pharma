<?php

namespace App\Domains\Content\Domain\Education;

use App\Support\BusinessInput;
use Illuminate\Support\Str;

final class ArticleDocument
{
    public static function empty(): array
    {
        return ['schema_version' => 1, 'blocks' => []];
    }

    public static function only(array $value, array $keys): void
    {
        abort_if(array_diff(array_keys($value), $keys) !== [], 422, 'حقل غير معتمد.');
    }

    public static function text(mixed $value, int $max): string
    {
        abort_unless(is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= $max, 422, 'النص أطول من الحد أو غير صالح.');
        abort_if(preg_match('/[\p{Cc}\p{Cf}]/u', str_replace(["\n", "\r", "\t"], '', $value)) || preg_match('/<\/?[a-z!]|&#(?:x0*3c|0*60);?/iu', $value), 422, 'استخدمي نصاً آمناً دون HTML أو محارف تحكم.');

        return $value;
    }

    public static function url(mixed $value): string
    {
        $url = self::text($value, 2048);
        abort_unless(str_starts_with($url, 'https://'), 422, 'الرابط يجب أن يكون HTTPS مطلقاً.');
        BusinessInput::link($url);
        $parts = parse_url($url);

        // Canonical host/scheme only; query/path case and meaning remain unchanged.
        return 'https://'.strtolower($parts['host']).($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public static function normalize(mixed $input): array
    {
        abort_unless(is_array($input), 422);
        self::only($input, ['schema_version', 'blocks']);
        abort_unless(($input['schema_version'] ?? null) === 1 && isset($input['blocks']) && is_array($input['blocks']) && array_is_list($input['blocks']) && count($input['blocks']) <= 100, 422, 'صيغة أو عدد كتل المقال غير صالح.');
        $blocks = [];
        $length = 0;
        $seen = [];
        foreach ($input['blocks'] as $b) {
            abort_unless(is_array($b), 422);
            $type = $b['type'] ?? null;
            abort_unless(in_array($type, ['paragraph', 'heading', 'list', 'image'], true), 422, 'نوع كتلة غير معتمد.');
            if ($type === 'image') {
                self::only($b, ['type', 'media_id', 'caption']);
                abort_unless(is_string($b['media_id'] ?? null) && Str::isUuid($b['media_id']), 422);
                $id = strtolower($b['media_id']);
                abort_if(isset($seen[$id]), 422, 'الصورة مكررة في المقال.');
                $seen[$id] = true;
                $caption = isset($b['caption']) ? self::text($b['caption'], 500) : null;
                $length += mb_strlen($caption ?? '');
                $blocks[] = ['type' => 'image', 'media_id' => $id, 'caption' => $caption];
            } elseif ($type === 'list') {
                self::only($b, ['type', 'style', 'items']);
                abort_unless(in_array($b['style'] ?? null, ['ordered', 'unordered'], true) && isset($b['items']) && is_array($b['items']) && array_is_list($b['items']) && count($b['items']) >= 1 && count($b['items']) <= 50, 422, 'القائمة غير صالحة.');
                $items = [];
                foreach ($b['items'] as $item) {
                    $items[] = self::runs($item, $length);
                }
                $blocks[] = ['type' => 'list', 'style' => $b['style'], 'items' => $items];
            } else {
                self::only($b, $type === 'heading' ? ['type', 'level', 'runs'] : ['type', 'runs']);
                if ($type === 'heading') {
                    abort_unless(in_array($b['level'] ?? null, [2, 3], true), 422);
                }
                $blocks[] = ['type' => $type] + ($type === 'heading' ? ['level' => $b['level']] : []) + ['runs' => self::runs($b['runs'] ?? null, $length)];
            }
        }
        abort_if($length > 40000 || count($seen) > 5, 422, 'المقال يتجاوز حد النص أو الصور.');
        $out = ['schema_version' => 1, 'blocks' => $blocks];
        abort_if(strlen(self::encode($out)) > 192 * 1024, 422, 'المستند المنظم أكبر من الحد.');

        return $out;
    }

    private static function runs(mixed $input, int &$length): array
    {
        abort_unless(is_array($input) && array_is_list($input) && count($input) >= 1 && count($input) <= 20, 422, 'عدد المقاطع غير صالح.');
        $out = [];
        foreach ($input as $run) {
            abort_unless(is_array($run), 422);
            self::only($run, ['text', 'bold', 'italic', 'href']);
            abort_unless(is_bool($run['bold'] ?? null) && is_bool($run['italic'] ?? null), 422);
            $text = self::text($run['text'] ?? null, 40000);
            $length += mb_strlen($text);
            abort_if($length > 40000, 422);
            $href = isset($run['href']) ? self::url($run['href']) : null;
            $next = ['text' => $text, 'bold' => $run['bold'], 'italic' => $run['italic'], 'href' => $href];
            $last = array_key_last($out);
            if ($last !== null && $out[$last]['bold'] === $next['bold'] && $out[$last]['italic'] === $next['italic'] && $out[$last]['href'] === $href) {
                $out[$last]['text'] .= $text;
            } else {
                $out[] = $next;
            }
        }

        return $out;
    }

    public static function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function readable(array $document): bool
    {
        foreach ($document['blocks'] as $b) {
            if ($b['type'] === 'image') {
                continue;
            }
            foreach ($b['type'] === 'list' ? $b['items'] : [$b['runs']] as $runs) {
                foreach ($runs as $run) {
                    if (trim($run['text']) !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
