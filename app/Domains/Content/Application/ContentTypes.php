<?php

namespace App\Domains\Content\Application;

final class ContentTypes
{
    public static function all(): array
    {
        $text = ['title' => ['العنوان', 160], 'slug' => ['الرابط المختصر', 80, 'slug'], 'body' => ['النص — دون HTML', 12000]];

        return [
            'banners' => ['table' => 'content_banners', 'label' => 'البنرات', 'media' => true, 'required' => ['title', 'alt_text', 'sort_order'], 'fields' => [
                'title' => ['العنوان', 160], 'description' => ['وصف مختصر', 500], 'alt_text' => ['وصف الصورة لقارئات الشاشة', 250], 'link' => ['وجهة داخلية أو رابط HTTPS', 500, 'link'], 'sort_order' => ['ترتيب العرض', 10, 'order'],
            ]],
            'pages' => ['table' => 'content_pages', 'label' => 'الصفحات', 'required' => ['title', 'slug', 'body'], 'fields' => $text + ['seo_title' => ['عنوان محركات البحث', 160], 'meta_description' => ['وصف محركات البحث', 300]]],
            'faqs' => ['table' => 'content_faqs', 'label' => 'الأسئلة الشائعة', 'required' => ['question', 'answer', 'sort_order'], 'fields' => ['question' => ['السؤال', 300], 'answer' => ['الإجابة — دون HTML', 6000], 'sort_order' => ['ترتيب العرض', 10, 'order']]],
            'policies' => ['table' => 'content_policies', 'label' => 'سياسات المتجر', 'required' => ['title', 'slug', 'body'], 'fields' => array_replace($text, ['slug' => ['نوع الوثيقة', 80, 'policy_slug']])],
        ];
    }

    public static function get(string $type): array
    {
        abort_unless(isset(self::all()[$type]), 404);

        return self::all()[$type] + ['singleton' => false, 'public' => true];
    }
}
