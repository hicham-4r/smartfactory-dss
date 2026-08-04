<?php

namespace App\Enums\AI;

enum AiInferenceStatus: string
{
    case Success = 'success';
    case Rejected = 'rejected';
    case Unavailable = 'unavailable';
    case NotConfigured = 'not_configured';
    case InvalidResponse = 'invalid_response';
}
