<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class BusinessInput
{
    public static function fields(array $input, array $spec): array
    {
        abort_if(array_diff(array_keys($input), array_keys($spec['fields'])) !== [], 422, 'حقل غير معتمد.');
        $rules = [];
        foreach ($spec['fields'] as $field => $definition) {
            $type = $definition[2] ?? 'text';
            $rules[$field] = ['sometimes', 'nullable', 'string', 'max:'.$definition[1]];
            if (isset($input[$field]) && is_string($input[$field])) {
                $value = trim($input[$field]);
                abort_if(! mb_check_encoding($value, 'UTF-8') || preg_match('/[\p{Cc}\p{Cf}]/u', str_replace(["\r", "\n", "\t"], '', $value)), 422, 'النص يتضمن محارف غير مسموحة.');
                abort_if(preg_match('/<\/?[a-z!]|&#(?:x0*3c|0*60);?/iu', $value), 422, 'استخدمي نصاً بسيطاً دون HTML.');
                $input[$field] = $value === '' ? null : $value;
            }
            $rules[$field] = [...$rules[$field], ...match ($type) {
                'email' => ['email:rfc'], 'phone' => ['regex:/^\+?[0-9][0-9 ()-]{5,28}$/D'],
                'currency' => [Rule::in(['USD', 'SYP'])], 'payment_mode' => [Rule::in(['full', 'deposit'])],
                'percentage' => ['regex:/^(?:0|[1-9][0-9]?|100)(?:\.[0-9]{1,2})?$/D'],
                'money' => ['regex:/^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/D'],
                'order' => ['regex:/^(?:0|[1-9][0-9]{0,8})$/D'],
                'slug' => ['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
                'policy_slug' => [Rule::in(['privacy', 'terms', 'returns'])], default => [],
            }];
            if (isset($input[$field]) && in_array($type, ['link', 'facebook', 'instagram', 'whatsapp', 'tiktok', 'youtube'], true)) {
                self::link($input[$field], $type);
            }
        }

        $data = validator($input, $rules, ['string' => 'يجب أن يكون حقل :attribute نصاً.', 'max' => 'حقل :attribute أطول من الحد المسموح.', 'email' => 'أدخلي بريداً صالحاً في :attribute.', 'regex' => 'صيغة :attribute غير صالحة.', 'in' => 'اختاري قيمة معتمدة لحقل :attribute.'], array_map(fn ($definition) => $definition[0], $spec['fields']))->validate();
        foreach ($data as $key => $value) {
            if ($value !== null && in_array($spec['fields'][$key][2] ?? '', ['money', 'percentage'], true)) {
                $parts = explode('.', $value);
                $data[$key] = $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');
            }
        }

        return $data;
    }

    public static function link(mixed $url, string $type = 'link'): void
    {
        abort_unless(is_string($url), 422);
        // No fetching. Reject encoded controls, protocol-relative URLs and backslashes as well.
        abort_if(preg_match('/[\s\\\\\x00-\x1f\x7f]|%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $url), 422, 'الرابط غير آمن.');
        if ($type === 'link' && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            abort_if(str_contains(substr($url, 1), '//'), 422, 'المسار الداخلي غير صالح.');
            abort_unless(preg_match('#^/[a-zA-Z0-9/_-]*(?:\?[a-zA-Z0-9=&_-]+)?$#D', $url), 422, 'المسار الداخلي غير صالح.');

            return;
        }
        $parts = parse_url($url);
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port']), 422, 'يجب استخدام رابط HTTPS صالح دون بيانات دخول.');
        $hosts = ['facebook' => ['facebook.com', 'www.facebook.com'], 'instagram' => ['instagram.com', 'www.instagram.com'], 'whatsapp' => ['wa.me', 'www.whatsapp.com', 'api.whatsapp.com'], 'tiktok' => ['tiktok.com', 'www.tiktok.com'], 'youtube' => ['youtube.com', 'www.youtube.com', 'youtu.be']];
        abort_if($type !== 'link' && ! in_array(strtolower($parts['host']), $hosts[$type], true), 422, 'الرابط لا يطابق المنصة المحددة.');
    }

    public static function complete(array $values, array $spec): void
    {
        foreach ($spec['required'] as $key) {
            abort_if(! isset($values[$key]) || $values[$key] === '', 422, 'أكملي الحقول المطلوبة قبل الحفظ أو النشر.');
        }
        if (isset($spec['verify'])) {
            ($spec['verify'])($values);
        }
    }
}
