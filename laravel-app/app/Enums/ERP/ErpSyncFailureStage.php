<?php

namespace App\Enums\ERP;

enum ErpSyncFailureStage: string
{
    case Connector = 'connector';
    case Response = 'response';
    case Mapping = 'mapping';
    case Persistence = 'persistence';
    case Checkpoint = 'checkpoint';
    case Finalization = 'finalization';

    public function label(): string
    {
        return match ($this) {
            self::Connector => 'Connector',
            self::Response => 'Response normalization',
            self::Mapping => 'ERP mapping',
            self::Persistence => 'Database persistence',
            self::Checkpoint => 'Checkpoint persistence',
            self::Finalization => 'Run finalization',
        };
    }
}