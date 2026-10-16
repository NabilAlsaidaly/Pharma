<?php

namespace App\Domains\Settings\Infrastructure;

use App\Domains\Settings\Application\SettingGroups;
use App\Support\BusinessRaster;
use App\Support\RevisionedStore;
use Illuminate\Support\Facades\DB;

final class PublishedStoreConfiguration
{
    public function read(): array
    {
        return DB::transaction(function (): array {
            $read = function (string $group) {
                $spec = SettingGroups::get($group);
                RevisionedStore::requireSchema($spec);
                $fields = array_keys($spec['fields']);
                if ($spec['media'] ?? false) {
                    $fields[] = 'image_path';
                }

                return DB::table($spec['table'].' as d')->join($spec['table'].'_revisions as r', function ($join): void {
                    $join->on('r.record_id', '=', 'd.id')->on('r.version', '=', 'd.published_version');
                })->whereNull('d.archived_at')->select(array_map(fn ($key) => 'r.'.$key, $fields))->addSelect(['d.id', 'r.version as revision', 'r.created_at as published_at'])->first();
            };
            $profile = $read('profile');
            $currencies = $read('currency');
            $store = $profile === null ? null : (array) $profile;
            if ($store !== null) {
                $store['revision'] = (string) $store['revision'];
                $store['logo_url'] = BusinessRaster::url($store['image_path']);
                unset($store['image_path']);
                $store['text_format'] = 'text/plain';
                $store['social_links'] = [];
                foreach (['facebook', 'instagram', 'whatsapp', 'tiktok', 'youtube'] as $platform) {
                    if ($store[$platform] !== null) {
                        $store['social_links'][] = ['platform' => $platform, 'url' => $store[$platform], 'rel' => 'noopener noreferrer'];
                    }
                    unset($store[$platform]);
                }
            }

            return ['store' => $store, 'currency_presentation' => $currencies === null ? null : array_replace((array) $currencies, ['revision' => (string) $currencies->revision])];
        });
    }
}
