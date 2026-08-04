<?php

namespace App\Enums\ERP;

enum ErpMaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Inspection = 'inspection';
    case Calibration = 'calibration';

    public function label(): string
    {
        return match ($this) {
            self::Preventive => 'Preventive',
            self::Corrective => 'Corrective',
            self::Inspection => 'Inspection',
            self::Calibration => 'Calibration',
        };
    }
}