<?php

namespace App\Enums\ERP;

enum ErpPersistenceAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
}