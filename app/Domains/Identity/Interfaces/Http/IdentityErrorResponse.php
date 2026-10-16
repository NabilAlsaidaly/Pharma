<?php

namespace App\Domains\Identity\Interfaces\Http;

use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentityErrorResponse
{
    public static function render(AccountSuspended $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => 'ACCOUNT_SUSPENDED', 'message' => 'هذا الحساب معلّق.', 'fields' => (object) []],
            'request_id' => $request->attributes->get('request_id'),
        ], 403, $request->is('api/v1/gifts/*') ? ['Cache-Control' => 'no-store, private'] : []);
    }
}
