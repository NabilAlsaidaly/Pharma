<?php

namespace App\Domains\ManagementDashboard\Interfaces\Http\Controllers;

use App\Domains\ManagementDashboard\Application\DashboardDateRange;
use App\Domains\ManagementDashboard\Application\DashboardResources;
use App\Domains\ManagementDashboard\Application\GlobalSearch;
use App\Domains\ManagementDashboard\Infrastructure\ReadModels\DashboardMetrics;
use App\Domains\ManagementDashboard\Infrastructure\ReadModels\OwnerResourceReader;
use App\Domains\ManagementDashboard\Interfaces\Http\Requests\OwnerDashboardRequest;

class OwnerDashboardController
{
    public function overview(OwnerDashboardRequest $request, DashboardMetrics $metrics)
    {
        return view('owner.dashboard.overview', $metrics->read(DashboardDateRange::from($request->validated())) + ['filters' => $request->validated()]);
    }

    public function search(OwnerDashboardRequest $request, GlobalSearch $search)
    {
        $term = $request->validated('q');

        return view('owner.dashboard.search', ['term' => $term, 'groups' => $term ? $search->execute($term) : []]);
    }

    public function index(OwnerDashboardRequest $request, OwnerResourceReader $reader, string $resource)
    {
        $input = array_filter($request->validated(), fn ($value) => $value !== null && $value !== '');

        return view('owner.dashboard.index', ['resource' => $resource, 'definition' => DashboardResources::DEFINITIONS[$resource],
            'records' => $reader->listing($resource, $input), 'filters' => $input,
            'brands' => $resource === 'products' ? $reader->brandOptions($input['brand'] ?? null) : []]);
    }

    public function show(OwnerDashboardRequest $request, OwnerResourceReader $reader, string $id, string $resource)
    {
        return view('owner.dashboard.detail', $reader->detail($resource, $id, (int) $request->validated('page', 1))
            + ['resource' => $resource, 'definition' => DashboardResources::DEFINITIONS[$resource]]);
    }
}
