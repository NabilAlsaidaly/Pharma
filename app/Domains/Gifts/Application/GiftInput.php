<?php

namespace App\Domains\Gifts\Application;

use App\Domains\Gifts\Domain\GiftRules;
use App\Support\BusinessInput;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GiftInput
{
    public const PACKAGE_FIELDS = [
        'name' => ['اسم الباقة', 160], 'short_description' => ['الوصف المختصر', 500],
        'description' => ['الوصف الكامل', 12000], 'suitability' => ['الملاءمة', 1000],
        'price_usd' => ['سعر USD', 19, 'money'], 'price_syp' => ['سعر SYP', 19, 'money'],
        'availability' => ['التوفر', 20], 'sort_order' => ['الترتيب', 9, 'order'],
    ];

    public static function only(array $data, array $keys): void
    {
        abort_if(array_diff(array_keys($data), $keys) !== [], 422, 'حقل غير معتمد.');
    }

    public static function version(mixed $value, bool $advancing = true): string
    {
        try {
            return GiftRules::version($value, $advancing);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['expected_version' => 'الإصدار يجب أن يكون نصاً عشرياً صحيحاً.']);
        }
    }

    public static function operation(mixed $value): string
    {
        abort_unless(is_string($value) && Str::isUuid($value) && in_array(strtolower($value[14]), ['4', '7'], true), 422, 'معرّف العملية غير صالح.');

        return strtolower($value);
    }

    public static function text(array $input, array $fields): array
    {
        return BusinessInput::fields($input, ['fields' => $fields]);
    }

    public static function components(array $rows): array
    {
        abort_unless(array_is_list($rows) && count($rows) <= 50, 422, 'الحد الأقصى 50 مكوناً.');
        $out = [];
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422);
            self::only($row, ['type', 'product_id', 'variant_id', 'name', 'description', 'quantity', 'sort_order']);
            $v = validator($row, ['type' => 'required|in:catalog,manual', 'quantity' => ['required', 'integer', 'between:1,100'], 'sort_order' => ['required', 'integer', 'between:0,999999999'], 'product_id' => 'nullable|uuid', 'variant_id' => 'nullable|uuid'])->validate();
            // JSON floats/bools/strings are not quantities; web adapters normalize canonical digit fields explicitly.
            abort_unless(is_int($row['quantity']) && is_int($row['sort_order']), 422);
            $manual = $v['type'] === 'manual';
            abort_if($manual ? isset($row['product_id']) || isset($row['variant_id']) : ! isset($row['product_id']) || isset($row['name']) || isset($row['description']), 422, 'نوع المكون وحقوله متعارضة.');
            $text = $manual ? self::text(array_intersect_key($row, array_flip(['name', 'description'])), ['name' => ['اسم العنصر', 160], 'description' => ['وصف العنصر', 1000]]) : [];
            abort_if($manual && empty($text['name']), 422, 'أدخلي اسم العنصر.');
            $product = isset($v['product_id']) ? strtolower($v['product_id']) : null;
            $variant = isset($v['variant_id']) ? strtolower($v['variant_id']) : null;
            $key = $manual ? 'm:'.GiftRules::key($text['name']) : 'c:'.$product.':'.($variant ?? '-');
            abort_if(isset($out[$key]), 422, 'المكون مكرر؛ عدّلي كمية السطر الموجود.');
            $out[$key] = ['type' => $v['type'], 'product_id' => $product, 'variant_id' => $variant, 'name' => $text['name'] ?? '', 'description' => $text['description'] ?? null, 'quantity' => $row['quantity'], 'sort_order' => $row['sort_order'], 'selection_key' => $key];
        }

        return array_values($out);
    }

    public static function fingerprint(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
