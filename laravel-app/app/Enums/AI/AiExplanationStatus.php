<?php

namespace App\Enums\AI;

enum AiExplanationStatus: string
{
    case Success = 'success';

    case Rejected = 'rejected';

    case Unavailable = 'unavailable';

    case InvalidResponse = 'invalid_response';

    case RateLimited = 'rate_limited';

    case NotConfigured = 'not_configured';

    public function succeeded(): bool
    {
        return $this === self::Success;
    }
}
