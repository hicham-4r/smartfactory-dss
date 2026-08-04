<?php

namespace App\Providers;

use App\Contracts\AI\AiServiceClientInterface;
use App\DTOs\AI\AiServiceConfig;
use App\Exceptions\AI\AiConfigurationException;
use App\Services\AI\DisabledAiServiceClient;
use App\Services\AI\FastApiAiServiceClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            AiServiceClientInterface::class,
            function (
                $app
            ): AiServiceClientInterface {
                $driver = strtolower(
                    trim(
                        (string) config(
                            'ai.default',
                            'disabled'
                        )
                    )
                );

                if ($driver === 'disabled') {
                    return new DisabledAiServiceClient();
                }

                if ($driver !== 'fastapi') {
                    return new DisabledAiServiceClient(
                        'The configured AI service driver is unsupported.'
                    );
                }

                try {
                    $settings = config(
                        'ai.service',
                        []
                    );

                    $allowInsecureTransport =
                        $app->environment(
                            'local',
                            'testing'
                        )
                        || (bool) config(
                            'ai.allow_internal_http',
                            false
                        );

                    $configuration =
                        AiServiceConfig::fromArray(
                            settings:
                                is_array(
                                    $settings
                                )
                                    ? $settings
                                    : [],

                            allowInsecureTransport:
                                $allowInsecureTransport
                        );
                } catch (
                    AiConfigurationException
                ) {
                    return new DisabledAiServiceClient(
                        'The FastAPI service configuration is invalid.'
                    );
                }

                return new FastApiAiServiceClient(
                    http:
                        $app->make(
                            Factory::class
                        ),
                    config:
                        $configuration
                );
            }
        );
    }
}
