<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http\Controllers;

use App\Domains\ManagementIdentity\Application\UpdateOwnerAccount;
use App\Domains\ManagementIdentity\Interfaces\Http\Requests\OwnerFormRequest;
use Illuminate\Support\Facades\Auth;

class OwnerAccountController
{
    public function home()
    {
        return view('owner.home', ['owner' => Auth::guard('owner')->user()]);
    }

    public function profile()
    {
        return view('owner.profile', ['owner' => Auth::guard('owner')->user()]);
    }

    public function update(OwnerFormRequest $request, UpdateOwnerAccount $update)
    {
        $update->name(Auth::guard('owner')->id(), $request->session()->get('owner_credential_version'), $request->validated('display_name'));

        return redirect()->route('owner.profile')->with('status', 'تم تحديث الاسم.');
    }

    public function password(OwnerFormRequest $request, UpdateOwnerAccount $update)
    {
        $input = $request->validated();
        $owner = $update->password(Auth::guard('owner')->id(), $request->session()->get('owner_credential_version'), $input['current_password'], $input['password']);
        Auth::guard('owner')->login($owner, false);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
        $request->session()->put(['owner_credential_version' => $owner->credential_version, 'owner_last_activity' => now()->timestamp]);

        return redirect()->route('owner.profile')->with('status', 'تم تغيير كلمة المرور وإلغاء صلاحية الجلسات السابقة.');
    }
}
