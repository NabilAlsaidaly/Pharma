<?php

namespace App\Domains\ManagementIdentity\Infrastructure;

use App\Domains\ManagementIdentity\Application\OwnerCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OwnerLoginLimiter
{
    public static function identityKey(Request $request): string
    {
        $email = is_string($request->input('email')) ? OwnerCredentials::email($request->input('email')) : '';

        return 'owner:login:'.hash_hmac('sha256', $email.'|'.$request->ip(), (string) config('app.key'));
    }

    public function begin(Request $request): void
    {
        $identityKey = self::identityKey($request);
        $digest = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
        $ipKey = 'owner:ip:'.$digest;
        if (RateLimiter::tooManyAttempts($identityKey, config('owner_portal.identity_attempts_per_minute')) || RateLimiter::tooManyAttempts($ipKey, config('owner_portal.ip_attempts_per_hour'))) {
            if (RateLimiter::attempt('owner:throttle-audit:'.$digest, 1, fn () => true, 3600)) {
                OwnerAudit::record('login_throttled');
            }
            abort(429, 'محاولات كثيرة. يرجى الانتظار ثم المحاولة.', ['Retry-After' => (string) max(RateLimiter::availableIn($identityKey), RateLimiter::availableIn($ipKey))]);
        }
        RateLimiter::hit($identityKey, 60);
        RateLimiter::hit($ipKey, 3600);

    }
}
