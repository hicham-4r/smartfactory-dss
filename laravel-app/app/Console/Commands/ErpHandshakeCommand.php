<?php

namespace App\Console\Commands;

use App\Contracts\ERP\ErpConnectorInterface;
use App\DTOs\ERP\ErpPageRequest;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpConnectorException;
use Illuminate\Console\Command;
use Throwable;

final class ErpHandshakeCommand extends Command
{
    protected $signature =
        'erp:handshake
        {--resource=products : ERP resource to test}
        {--per-page=5 : Maximum records to retrieve}';

    protected $description =
        'Perform a safe HTTPS handshake with the configured ERP connector';

    public function handle(
        ErpConnectorInterface $connector
    ): int {
        $resourceValue = strtolower(
            trim(
                (string) $this->option(
                    'resource'
                )
            )
        );

        $resourceValue = str_replace(
            '-',
            '_',
            $resourceValue
        );

        $resource =
            ErpResource::tryFrom(
                $resourceValue
            );

        if ($resource === null) {
            $this->error(
                'Unknown ERP resource: '
                .$resourceValue
            );

            return self::INVALID;
        }

        $perPage = filter_var(
            $this->option('per-page'),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 200,
                ],
            ]
        );

        if ($perPage === false) {
            $this->error(
                'The --per-page value must be between 1 and 200.'
            );

            return self::INVALID;
        }

        $this->components->info(
            'Checking ERP connector health'
        );

        try {
            $health = $connector->health();

            $this->table(
                [
                    'Property',
                    'Value',
                ],
                [
                    [
                        'Connector',
                        $connector->name(),
                    ],
                    [
                        'Source system',
                        $connector->sourceSystem(),
                    ],
                    [
                        'Health',
                        $health->status->value,
                    ],
                    [
                        'Latency',
                        $health
                            ->latencyMilliseconds
                            !== null
                                ? $health
                                    ->latencyMilliseconds
                                    .' ms'
                                : 'Not available',
                    ],
                ]
            );

            if (! $health->isAvailable()) {
                $this->components->error(
                    'The ERP connector is not available.'
                );

                return self::FAILURE;
            }

            if (! $connector->supports($resource)) {
                $this->components->error(
                    'The connector does not support resource ['
                    .$resource->value
                    .'].'
                );

                return self::FAILURE;
            }

            $this->components->info(
                'Fetching a controlled ERP page'
            );

            $page = $connector->fetchPage(
                $resource,
                new ErpPageRequest(
                    page: 1,
                    perPage: $perPage
                )
            );

            $this->table(
                [
                    'Property',
                    'Value',
                ],
                [
                    [
                        'Resource',
                        $resource->value,
                    ],
                    [
                        'Records received',
                        (string) $page->count(),
                    ],
                    [
                        'Current page',
                        (string) $page->currentPage,
                    ],
                    [
                        'Page size',
                        (string) $page->perPage,
                    ],
                    [
                        'Total',
                        $page->total !== null
                            ? (string) $page->total
                            : 'Not supplied',
                    ],
                    [
                        'More pages',
                        $page->hasMore()
                            ? 'yes'
                            : 'no',
                    ],
                    [
                        'Response ID',
                        $page->responseId
                            ?? 'Not supplied',
                    ],
                ]
            );

            $this->components->success(
                'The protected Simulated Sage HTTPS handshake succeeded.'
            );

            return self::SUCCESS;
        } catch (
            ErpConnectorException $exception
        ) {
            $this->components->error(
                'ERP handshake failed: '
                .$exception->getMessage()
            );

            return self::FAILURE;
        } catch (Throwable) {
            /*
             * Do not print stack traces, configuration values,
             * tokens, or response payloads.
             */
            $this->components->error(
                'ERP handshake failed because of an unexpected internal error.'
            );

            return self::FAILURE;
        }
    }
}