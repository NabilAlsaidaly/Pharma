<?php

namespace App\Domains\Content\Application;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Support\RevisionedStore;
use Illuminate\Http\UploadedFile;

final class ManageStoreContent
{
    public function execute(string $type, ?string $id, ManagementUser $owner, array $command, ?UploadedFile $file = null): string
    {
        return app(RevisionedStore::class)->mutate(ContentTypes::get($type), $id, $owner, $command, $file);
    }
}
