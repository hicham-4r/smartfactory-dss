<?php

namespace App\Enums\ERP;

enum ErpFinishedLotStatus: string
{
    case Pending = 'pending';
    case Released = 'released';
    case Blocked = 'blocked';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Released => 'Released',
            self::Blocked => 'Blocked',
            self::Rejected => 'Rejected',
        };
    }
}