<?php

namespace App\Domains\ManagementDashboard\Application;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class DashboardDateRange
{
    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end)
    {
        if ($end <= $start || $start->diffInDays($end) > 93) {
            throw new \InvalidArgumentException('Dashboard date range must contain 1–93 days.');
        }
    }

    public static function from(array $input): self
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $period = $input['period'] ?? '7days';
        $start = match ($period) {
            'today' => $today,
            '30days' => $today->subDays(29),
            'month' => $today->startOfMonth(),
            'custom' => CarbonImmutable::createFromFormat('!Y-m-d', $input['from'], config('app.timezone')),
            default => $today->subDays(6),
        };
        $end = $period === 'custom'
            ? CarbonImmutable::createFromFormat('!Y-m-d', $input['to'], config('app.timezone'))->addDay()
            : $today->addDay();

        return new self($start, $end);
    }

    public function buckets(): array
    {
        $buckets = [];
        foreach (CarbonPeriod::create($this->start, $this->end->subDay()) as $day) {
            $buckets[] = [
                'date' => $day->format('Y-m-d'),
                'start' => $day->copy()->utc()->format('Y-m-d H:i:s'),
                'end' => $day->copy()->addDay()->utc()->format('Y-m-d H:i:s'),
            ];
        }

        return $buckets;
    }
}
