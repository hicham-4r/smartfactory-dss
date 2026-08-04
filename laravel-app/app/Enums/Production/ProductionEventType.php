<?php

namespace App\Enums\Production;

enum ProductionEventType: string
{
    case Production = 'production';
    case Downtime = 'downtime';
    case MachineIncident = 'machine_incident';
    case Quality = 'quality';
    case Comment = 'comment';

    public function requiresMachine(): bool
    {
        return $this === self::MachineIncident;
    }

    public function affectsAvailability(): bool
    {
        return in_array(
            $this,
            [
                self::Downtime,
                self::MachineIncident,
            ],
            true
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Downtime => 'Downtime',
            self::MachineIncident => 'Machine incident',
            self::Quality => 'Quality observation',
            self::Comment => 'Comment',
        };
    }
}