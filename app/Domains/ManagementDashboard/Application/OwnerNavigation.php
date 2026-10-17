<?php

namespace App\Domains\ManagementDashboard\Application;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class OwnerNavigation
{
    public function definitions(): array
    {
        $entries = [
            ['key' => 'workspace', 'label' => 'مساحة العمل', 'route' => 'owner.home', 'group' => 'البداية', 'icon' => 'home', 'order' => 10],
            ['key' => 'overview', 'label' => 'نظرة عامة', 'route' => 'owner.dashboard', 'group' => 'البداية', 'icon' => 'chart', 'order' => 20],
            ['key' => 'search', 'label' => 'البحث الموحد', 'route' => 'owner.search', 'group' => 'البداية', 'icon' => 'search', 'order' => 30],
        ];
        foreach (DashboardResources::DEFINITIONS as $key => $resource) {
            $entries[] = [
                'key' => $key,
                'label' => $resource['label'],
                'route' => 'owner.'.$key.'.index',
                'group' => $key === 'requests' ? 'التواصل' : 'الكتالوج',
                'icon' => $key,
                'order' => 40 + count($entries),
                'description' => $resource['description'],
            ];
        }
        $entries[] = ['key' => 'settings', 'label' => 'إعدادات المتجر', 'route' => 'owner.settings.index', 'group' => 'المتجر والمحتوى', 'icon' => 'home', 'order' => 80];
        foreach (['index' => 'باقات الهدايا', 'occasions' => 'المناسبات', 'requests' => 'طلبات الهدايا'] as $key => $label) {
            $entries[] = ['key' => 'gifts-'.$key, 'label' => $label, 'route' => 'owner.gifts.'.$key, 'group' => 'الهدايا', 'icon' => 'home', 'order' => 90];
        }
        foreach (['index' => 'المقالات والنصائح', 'categories' => 'تصنيفات المقالات', 'history' => 'سجل المحتوى التعليمي'] as $key => $label) {
            $entries[] = ['key' => 'education-'.$key, 'label' => $label, 'route' => 'owner.education.'.$key, 'group' => 'النصائح والمعلومات', 'icon' => 'home', 'order' => 95];
        }
        $entries[] = ['key' => 'delivery', 'label' => 'التوصيل', 'route' => 'owner.delivery.index', 'group' => 'التواصل', 'icon' => 'home', 'order' => 96];
        $entries[] = ['key' => 'profile', 'label' => 'حسابي', 'route' => 'owner.profile', 'group' => 'الحساب', 'icon' => 'profile', 'order' => 100];

        return array_map(fn ($entry) => $entry + ['ability' => null, 'description' => null], $entries);
    }

    public function resolve(array $entries, ?ManagementUser $owner): array
    {
        if ($owner === null || $owner->role !== 'owner' || $owner->status !== 'active') {
            return [];
        }
        $allowed = array_filter($entries, fn ($entry) => Route::has($entry['route'])
            && (($entry['ability'] ?? null) === null || Gate::forUser($owner)->allows($entry['ability'])));
        usort($allowed, fn ($a, $b) => [$a['order'], $a['key']] <=> [$b['order'], $b['key']]);

        return array_map(function ($entry) {
            $current = (string) request()->route()?->getName();
            $entry['active'] = $entry['route'] === $current || (str_ends_with($entry['route'], '.index') && str_starts_with($current, substr($entry['route'], 0, -5)));
            if (str_starts_with($entry['key'], 'gifts-')) {
                $entry['active'] = match ($entry['key']) {
                    'gifts-index' => in_array($current, ['owner.gifts.index', 'owner.gifts.create', 'owner.gifts.edit', 'owner.gifts.mutate', 'owner.gifts.media', 'owner.gifts.history'], true),
                    'gifts-occasions' => $current === 'owner.gifts.occasions' || str_starts_with($current, 'owner.gifts.occasion.'),
                    default => in_array($current, ['owner.gifts.requests', 'owner.gifts.requests.show', 'owner.gifts.review'], true),
                };
            }
            if (str_starts_with($entry['key'], 'education-')) {
                $entry['active'] = match ($entry['key']) {
                    'education-categories' => $current === 'owner.education.categories' || str_starts_with($current, 'owner.education.category.'),
                    'education-history' => $current === 'owner.education.history',
                    default => in_array($current, ['owner.education.index', 'owner.education.create', 'owner.education.edit', 'owner.education.mutate', 'owner.education.media'], true),
                };
            }

            return $entry;
        }, $allowed);
    }
}
