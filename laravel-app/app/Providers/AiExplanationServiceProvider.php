<?php

namespace App\Providers;

use App\Contracts\AI\Explanations\AiExplanationClientInterface;
use App\DTOs\AI\Explanations\AiExplanationConfig;
use App\Exceptions\AI\AiConfigurationException;
use App\Services\AI\Explanations\AiExplanationSnapshotStore;
use App\Services\AI\Explanations\DisabledAiExplanationClient;
use App\Services\AI\Explanations\FastApiAiExplanationClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class AiExplanationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            AiExplanationClientInterface::class,
            function ($app): AiExplanationClientInterface {
                $driver = strtolower(trim((string) config(
                    'ai-explanations.default',
                    'disabled',
                )));

                if ($driver === 'disabled') {
                    return new DisabledAiExplanationClient;
                }

                if ($driver !== 'fastapi') {
                    return new DisabledAiExplanationClient(
                        'The configured explanation driver is unsupported.',
                    );
                }

                try {
                    $settings = config(
                        'ai-explanations.service',
                        [],
                    );
                    $allowInsecureTransport = $app->environment(
                        'local',
                        'testing',
                    ) || (bool) config(
                        'ai-explanations.allow_internal_http',
                        false,
                    );

                    $configuration = AiExplanationConfig::fromArray(
                        settings: is_array($settings) ? $settings : [],
                        allowInsecureTransport: $allowInsecureTransport,
                    );
                } catch (AiConfigurationException) {
                    return new DisabledAiExplanationClient(
                        'The FastAPI explanation configuration is invalid.',
                    );
                }

                return new FastApiAiExplanationClient(
                    http: $app->make(Factory::class),
                    config: $configuration,
                );
            },
        );

        $this->app->singleton(
            AiExplanationSnapshotStore::class,
            fn (): AiExplanationSnapshotStore =>
                new AiExplanationSnapshotStore(
                    ttlMinutes: max(
                        1,
                        min(
                            60,
                            (int) config(
                                'ai-explanations.snapshot_ttl_minutes',
                                15,
                            ),
                        ),
                    ),
                    maximumSnapshots: max(
                        1,
                        min(
                            25,
                            (int) config(
                                'ai-explanations.maximum_session_snapshots',
                                10,
                            ),
                        ),
                    ),
                ),
        );
    }
}
