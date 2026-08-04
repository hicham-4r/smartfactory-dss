<?php

namespace App\Console\Commands;

use App\Contracts\AI\Inference\AiInferenceClientInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class CheckAiInferenceCommand extends Command
{
    protected $signature = 'ai:inference:check';

    protected $description = 'Verify the authenticated FastAPI model registry from Laravel';

    public function handle(
        AiInferenceClientInterface $client,
    ): int {
        $requestId = 'laravel-ai-live-'.Str::uuid()->toString();
        $result = $client->models($requestId);

        if (! $result->succeeded()) {
            $this->error(
                $result->message
                ?? 'The inference registry check failed.',
            );

            $this->line('Status: '.$result->status->value);
            $this->line('Request ID: '.$result->requestId);

            return self::FAILURE;
        }

        $tasks = $result->data['tasks'] ?? [];

        $this->info('STEP 21M LARAVEL AI INFERENCE CHECK PASSED');
        $this->line(
            'Model run: '.(string) $result->data['model_run_id'],
        );
        $this->line(
            'Source feature run: '
            .(string) $result->data['source_feature_run_id'],
        );
        $this->line(
            'Classification: '
            .(string) $result->data['data_classification'],
        );
        $this->newLine();
        $this->table(
            ['Available task'],
            array_map(
                static fn (string $task): array => [$task],
                is_array($tasks) ? $tasks : [],
            ),
        );

        return self::SUCCESS;
    }
}
