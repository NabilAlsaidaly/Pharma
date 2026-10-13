<?php

namespace App\Domains\ManagementIdentity\Application;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProvisionOwner
{
    public function execute(string $name, string $email, string $password, string $confirmation): ManagementUser
    {
        if (! Schema::hasTable('management_users')) {
            throw new \RuntimeException('Owner schema is unavailable. Apply approved migrations separately.');
        }
        $data = ['display_name' => OwnerCredentials::name($name), 'email' => OwnerCredentials::email($email),
            'password' => $password, 'password_confirmation' => $confirmation];
        Validator::make($data, ['display_name' => OwnerCredentials::nameRules(), 'email' => ['required', 'email:rfc', 'max:254'],
            'password' => [...OwnerCredentials::passwordRules(), 'confirmed']], ['password.*' => 'كلمة المرور وتأكيدها غير صالحين.'])->validate();
        try {
            // Unique generated owner_slot is authoritative even for two initially empty-table transactions.
            return DB::transaction(function () use ($data) {
                if (ManagementUser::query()->where('role', 'owner')->exists()) {
                    throw ValidationException::withMessages(['owner' => 'تم إنشاء حساب المالكة مسبقاً.']);
                }
                $user = new ManagementUser;
                $user->forceFill(['display_name' => $data['display_name'], 'email' => $data['email'], 'password' => Hash::make($data['password']),
                    'role' => 'owner', 'status' => 'active', 'credential_version' => '1'])->save();

                return $user;
            }, 3);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['owner' => 'تعذر إنشاء حساب إضافي.']);
            }
            throw $e;
        }
    }
}
