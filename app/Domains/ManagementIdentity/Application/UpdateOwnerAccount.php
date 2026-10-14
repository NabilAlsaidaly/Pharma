<?php

namespace App\Domains\ManagementIdentity\Application;

use App\Domains\ManagementIdentity\Infrastructure\OwnerAudit;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateOwnerAccount
{
    private function locked(string $id, string $version): ManagementUser
    {
        $user = ManagementUser::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($user->role !== 'owner' || $user->status !== 'active' || $user->credential_version !== $version) {
            abort(403, 'الجلسة غير صالحة لهذه العملية.');
        }

        return $user;
    }

    public function name(string $id, string $version, string $name): void
    {
        DB::transaction(function () use ($id, $version, $name): void {
            $this->locked($id, $version)->forceFill(['display_name' => $name])->save();
        }, 3);
        OwnerAudit::record('profile_name_changed', $id);
    }

    public function password(string $id, string $version, string $current, string $new): ManagementUser
    {
        $user = DB::transaction(function () use ($id, $version, $current, $new) {
            $user = $this->locked($id, $version);
            if (! Hash::check($current, $user->password) || Hash::check($new, $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'كلمة المرور الحالية أو الجديدة غير صالحة.']);
            }
            $next = BigInteger::of($version)->plus(1);
            if ($next->isGreaterThan('18446744073709551615')) {
                abort(403, 'تعذر تحديث بيانات الأمان.');
            }
            $user->forceFill(['password' => Hash::make($new), 'credential_version' => (string) $next])->save();

            return $user;
        }, 3);
        OwnerAudit::record('password_changed', $id);

        return $user;
    }
}
