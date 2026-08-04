<?php

namespace App\Contracts\AI\Explanations;

use App\DTOs\AI\Explanations\AiExplanationResult;

interface AiExplanationClientInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function generate(
        array $payload,
        string $requestId,
    ): AiExplanationResult;
}
