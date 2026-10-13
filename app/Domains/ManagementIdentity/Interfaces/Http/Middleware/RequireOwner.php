<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http\Middleware;

use App\Domains\ManagementIdentity\Infrastructure\OwnerAudit;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('owner');
        $identity = $guard->user();
        if (! $identity instanceof ManagementUser) {
            return redirect()->route('owner.login');
        }
        $owner = ManagementUser::query()->find($identity->getAuthIdentifier());
        $version = $request->session()->get('owner_credential_version');
        $last = $request->session()->get('owner_last_activity');
        if ($owner === null || $owner->role !== 'owner' || $owner->status !== 'active' || ! is_string($version) || $owner->credential_version !== $version
            || ! is_int($last) || now()->timestamp - $last >= (int) config('owner_portal.idle_minutes') * 60) {
            $guard->logout();
            $request->session()->forget(['owner_credential_version', 'owner_last_activity']);
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();
            OwnerAudit::record('access_denied', $identity->getAuthIdentifier());
            abort(403, 'انتهت صلاحية الوصول. يرجى تسجيل الدخول مجدداً.');
        }
        $guard->setUser($owner);
        $request->session()->put('owner_last_activity', now()->timestamp);

        return $next($request);
    }
}
