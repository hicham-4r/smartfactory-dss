<?php

namespace App\Enums\AI;

enum AnalyticsContractValidationStatus: string
{
    case Accepted = 'accepted';

    case Rejected = 'rejected';

    case Unavailable = 'unavailable';

    case NotConfigured = 'not_configured';

    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    public function isOperationalFailure(): bool
    {
        return in_array(
            $this,
            [
                self::Unavailable,
                self::NotConfigured,
            ],
            true
        );
    }
}
