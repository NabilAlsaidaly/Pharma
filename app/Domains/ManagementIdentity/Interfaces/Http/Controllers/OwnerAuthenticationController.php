<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http\Controllers;

use App\Domains\ManagementIdentity\Application\OwnerLogin;
use App\Domains\ManagementIdentity\Infrastructure\OwnerAudit;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\RequireOwner;
use App\Domains\ManagementIdentity\Interfaces\Http\Requests\OwnerFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerAuthenticationController
{
    public function form(Request $request)
    {
        if (Auth::guard('owner')->check()) {
            return app(RequireOwner::class)->handle($request, fn () => redirect()->route('owner.home'));
        }

        return view('owner.login');
    }

    public function login(OwnerFormRequest $request, OwnerLogin $login)
    {
        $input = $request->validated();
        $login->execute($request, $input['email'], $input['password']);

        return redirect()->route('owner.home');
    }

    public function logout(Request $request)
    {
        $id = Auth::guard('owner')->id();
        Auth::guard('owner')->logout();
        $request->session()->forget(['owner_credential_version', 'owner_last_activity']);
        // Rotate ID/token while retaining unrelated Customer authentication on the shared session.
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
        OwnerAudit::record('logout', $id);

        return redirect()->route('owner.login');
    }
}
