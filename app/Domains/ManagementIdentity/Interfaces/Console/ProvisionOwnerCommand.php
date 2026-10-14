<?php

namespace App\Domains\ManagementIdentity\Interfaces\Console;

use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProvisionOwnerCommand extends Command
{
    protected $signature = 'owner:provision';

    protected $description = 'Interactively provision the single owner using hidden password prompts';

    public function handle(ProvisionOwner $provision): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Interactive trusted terminal access is required.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('management_users') || ManagementUser::query()->where('role', 'owner')->exists()) {
            $this->error('Owner schema missing or owner already provisioned. No account created.');

            return self::FAILURE;
        }
        $name = $this->ask('اسم المالكة');
        $email = $this->ask('البريد الإلكتروني');
        $password = $this->secret('كلمة المرور (12 محرفاً على الأقل)', false);
        $confirmation = $this->secret('تأكيد كلمة المرور', false);
        try {
            $provision->execute((string) $name, (string) $email, (string) $password, (string) $confirmation);
        } catch (ValidationException) {
            $this->error('القيم غير صالحة أو الحساب موجود مسبقاً. لم يُنشأ حساب إضافي.');

            return self::FAILURE;
        } catch (\Throwable) {
            // Do not render database bindings containing credential hashes or submitted identity.
            $this->error('تعذر إنشاء الحساب بأمان. راجع إعداد البيئة وقاعدة البيانات.');

            return self::FAILURE;
        }
        $this->info('تم إنشاء حساب المالكة بأمان.');

        return self::SUCCESS;
    }
}
