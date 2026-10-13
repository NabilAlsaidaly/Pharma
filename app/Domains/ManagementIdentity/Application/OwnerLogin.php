<?php

namespace App\Domains\ManagementIdentity\Application;

use App\Domains\ManagementIdentity\Infrastructure\OwnerAudit;
use App\Domains\ManagementIdentity\Infrastructure\OwnerLoginLimiter;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerLogin
{
    public function execute(Request $request, string $email, string $password): void
    {
        // Native random dummy on every attempt keeps missing-account checks on the same
        // hashing algorithm/work factor without a known credential or fast-path bypass.
        $dummyHash = Hash::make(Str::random(32));
        $user = DB::transaction(function () use ($email, $password, $dummyHash) {
            $user = ManagementUser::query()->where('email', OwnerCredentials::email($email))->lockForUpdate()->first();
            // A dummy native hash check avoids the obvious missing-account fast path.
            $hash = $user->password ?? $dummyHash;
            $matches = Hash::check($password, $hash);
            if ($user === null || ! $matches || $user->status !== 'active' || $user->role !== 'owner') {
                return null;
            }
            $user->forceFill(['last_login_at' => now()])->save();

            return $user;
        }, 3);
        if ($user === null) {
            OwnerAudit::record('login_failed');
            throw ValidationException::withMessages(['email' => 'تعذر تسجيل الدخول بهذه البيانات.']);
        }
        Auth::guard('owner')->login($user, false);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
        $request->session()->put(['owner_credential_version' => $user->credential_version, 'owner_last_activity' => now()->timestamp]);
        RateLimiter::clear(OwnerLoginLimiter::identityKey($request));
        // IP spraying counter deliberately survives successful authentication.
        OwnerAudit::record('login_succeeded', $user->id);
    }
}
