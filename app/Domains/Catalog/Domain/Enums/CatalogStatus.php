<?php

namespace App\Domains\Catalog\Domain\Enums;

enum CatalogStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';
}
