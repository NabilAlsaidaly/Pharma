<?php

namespace App\Domains\ManagementDashboard\Application;

class SearchTerm
{
    public static function normalize(string $value): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $value) ?? $value;
    }

    public static function pattern(string $value, bool $prefix = false): string
    {
        // Literal user %, _ and ! never acquire SQL wildcard meaning.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);

        return ($prefix ? '' : '%').$escaped.'%';
    }
}
