<?php

namespace App\Domains\ManagementDashboard\Application;

use App\Domains\ManagementDashboard\Infrastructure\ReadModels\OwnerResourceReader;

class GlobalSearch
{
    public function __construct(private OwnerResourceReader $reader) {}

    public function execute(string $term): array
    {
        $groups = [];
        foreach (DashboardResources::DEFINITIONS as $key => $resource) {
            $groups[$key] = $resource + ['results' => $this->reader->search($key, $term)];
        }

        return $groups;
    }
}
