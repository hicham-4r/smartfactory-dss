<?php

namespace App\Enums\Notifications;

enum NotificationSeverity: string
{
    case Information = 'information';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Information => 'Information',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    public function bootstrapClass(): string
    {
        return match ($this) {
            self::Information => 'primary',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }
}
