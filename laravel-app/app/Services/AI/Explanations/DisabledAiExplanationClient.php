<?php

namespace App\Services\AI\Explanations;

use App\Contracts\AI\Explanations\AiExplanationClientInterface;
use App\DTOs\AI\Explanations\AiExplanationResult;

final readonly class DisabledAiExplanationClient implements
    AiExplanationClientInterface
{
    public function __construct(
        private string $message =
            'The guarded explanation service is not configured.',
    ) {}

    public function generate(
        array $payload,
        string $requestId,
    ): AiExplanationResult {
        unset($payload);

        return AiExplanationResult::notConfigured(
            requestId: $requestId,
            message: $this->message,
        );
    }
}
