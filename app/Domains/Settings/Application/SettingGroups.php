<?php

namespace App\Domains\Settings\Application;

final class SettingGroups
{
    public static function all(): array
    {
        return [
            'profile' => ['table' => 'store_profiles', 'label' => 'المعلومات والتواصل', 'singleton' => true, 'public' => true, 'required' => ['name'], 'fields' => [
                'name' => ['اسم المتجر', 160], 'description' => ['وصف مختصر', 500], 'about' => ['عن المتجر', 6000],
                'email' => ['البريد العام', 254, 'email'], 'phone' => ['الهاتف العام', 30, 'phone'], 'address' => ['العنوان العام', 500], 'hours' => ['ساعات العمل للعرض فقط', 300],
                'facebook' => ['Facebook', 500, 'facebook'], 'instagram' => ['Instagram', 500, 'instagram'], 'whatsapp' => ['WhatsApp', 500, 'whatsapp'], 'tiktok' => ['TikTok', 500, 'tiktok'], 'youtube' => ['YouTube', 500, 'youtube'],
            ], 'media' => true],
            'currency' => ['table' => 'store_currencies', 'label' => 'عرض العملات', 'singleton' => true, 'public' => true, 'required' => ['first_currency', 'usd_label', 'syp_label'], 'fields' => [
                'first_currency' => ['العملة المعروضة أولاً', 3, 'currency'], 'usd_label' => ['اسم الدولار للعرض', 40], 'syp_label' => ['اسم الليرة السورية للعرض', 40], 'usd_symbol' => ['رمز الدولار', 12], 'syp_symbol' => ['رمز الليرة السورية', 12],
            ]],
            'payment' => ['table' => 'store_payment_policies', 'label' => 'تجهيز سياسة الدفع', 'singleton' => true, 'public' => false, 'required' => ['mode', 'percentage'], 'verify' => [self::class, 'verifyPayment'], 'fields' => [
                'mode' => ['نوع السياسة', 7, 'payment_mode'], 'percentage' => ['النسبة المطلوبة', 6, 'percentage'], 'explanation' => ['توضيح داخلي', 1000],
            ]],
            'zones' => ['table' => 'store_delivery_zones', 'label' => 'مناطق ورسوم التوصيل الإرشادية', 'singleton' => false, 'public' => true, 'required' => ['name', 'governorate', 'sort_order'], 'fields' => [
                'name' => ['اسم المنطقة للعرض', 160], 'governorate' => ['المحافظة', 100], 'city' => ['المدينة أو المنطقة (اختياري)', 100], 'fee_usd' => ['رسوم USD — الفارغ غير محدد والصفر مجاني مُعدّ', 19, 'money'], 'fee_syp' => ['رسوم SYP — مستقلة عن الدولار', 19, 'money'], 'sort_order' => ['ترتيب العرض', 10, 'order'], 'conditions' => ['ملاحظات التجهيز', 1000],
            ]],
        ];
    }

    public static function get(string $group): array
    {
        abort_unless(isset(self::all()[$group]), 404);

        return self::all()[$group];
    }

    public static function verifyPayment(array $values): void
    {
        $basisPoints = (int) str_replace('.', '', $values['percentage']);
        abort_unless($values['mode'] === 'full' ? $basisPoints === 10000 : ($basisPoints > 0 && $basisPoints < 10000), 422, 'الدفع الكامل 100%، والعربون أكبر من صفر وأقل من 100%.');
    }
}
