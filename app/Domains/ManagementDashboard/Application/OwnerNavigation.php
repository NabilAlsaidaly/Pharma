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

            return $entry;
        }, $allowed);
    }
}
