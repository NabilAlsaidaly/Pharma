<?php

namespace App\Domains\ManagementIdentity\Infrastructure;

use Illuminate\Support\Facades\Log;

class OwnerAudit
{
    public static function record(string $event, ?string $ownerId = null): void
    {
        Log::info('owner_security', ['event' => $event, 'owner_id' => $ownerId,
            'request_id' => app()->bound('request') ? request()->attributes->get('request_id') : null]);
    }
}
