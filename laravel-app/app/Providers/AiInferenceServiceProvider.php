<?php

namespace App\Providers;

use App\Contracts\AI\Inference\AiInferenceClientInterface;
use App\DTOs\AI\Inference\AiInferenceConfig;
use App\Exceptions\AI\AiConfigurationException;
use App\Services\AI\Inference\DisabledAiInferenceClient;
use App\Services\AI\Inference\FastApiAiInferenceClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class AiInferenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            AiInferenceClientInterface::class,
            function ($app): AiInferenceClientInterface {
                $driver = strtolower(trim((string) config(
                    'ai-inference.default',
                    'disabled',
                )));

                if ($driver === 'disabled') {
                    return new DisabledAiInferenceClient;
                }

                if ($driver !== 'fastapi') {
                    return new DisabledAiInferenceClient(
                        'The configured AI inference driver is unsupported.',
                    );
                }

                try {
                    $settings = config('ai-inference.service', []);
                    $allowInsecureTransport = $app->environment(
                        'local',
                        'testing',
                    ) || (bool) config(
                        'ai-inference.allow_internal_http',
                        false,
                    );

                    $configuration = AiInferenceConfig::fromArray(
                        settings: is_array($settings) ? $settings : [],
                        allowInsecureTransport: $allowInsecureTransport,
                    );
                } catch (AiConfigurationException) {
                    return new DisabledAiInferenceClient(
                        'The FastAPI inference configuration is invalid.',
                    );
                }

                return new FastApiAiInferenceClient(
                    http: $app->make(Factory::class),
                    config: $configuration,
                );
            },
        );
    }
}
