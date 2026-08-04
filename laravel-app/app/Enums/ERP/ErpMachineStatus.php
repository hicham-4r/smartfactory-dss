<?php

namespace App\Enums\ERP;

enum ErpMachineStatus: string
{
    case Running = 'running';
    case Idle = 'idle';
    case Stopped = 'stopped';
    case Fault = 'fault';
    case Maintenance = 'maintenance';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Idle => 'Idle',
            self::Stopped => 'Stopped',
            self::Fault => 'Fault',
            self::Maintenance => 'Maintenance',
            self::Offline => 'Offline',
        };
    }
}