<?php

namespace App\Domains\Settings\Application;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Support\RevisionedStore;
use Illuminate\Http\UploadedFile;

final class ManageStoreSettings
{
    public function execute(string $group, ?string $id, ManagementUser $owner, array $command, ?UploadedFile $file = null): string
    {
        return app(RevisionedStore::class)->mutate(SettingGroups::get($group), $id, $owner, $command, $file);
    }
}
