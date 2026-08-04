<?php

namespace App\Services\ERP;

use App\Contracts\ERP\ErpConnectorInterface;
use App\DTOs\ERP\ErpConnectorHealth;
use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\DTOs\ERP\SimulatedSageConnectorConfig;
use App\Enums\ERP\ErpConnectorHealthStatus;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpAuthenticationException;
use App\Exceptions\ERP\ErpConfigurationException;
use App\Exceptions\ERP\ErpConnectorException;
use App\Exceptions\ERP\ErpInvalidResponseException;
use App\Exceptions\ERP\ErpRateLimitException;
use App\Exceptions\ERP\ErpTransportException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class SimulatedSageRestConnector implements ErpConnectorInterface
{
    public function __construct(
        private readonly Factory $http,
        private readonly SimulatedSageConnectorConfig $config,
        private readonly SimulatedSageResponseNormalizer $normalizer
    ) {
    }

    public function name(): string
    {
        return 'Simulated Sage HTTPS REST connector';
    }

    public function sourceSystem(): string
    {
        return $this->config->sourceSystem;
    }

    public function supports(
        ErpResource $resource
    ): bool {
        return array_key_exists(
            $resource->value,
            $this->config->endpoints
        );
    }

    public function health(): ErpConnectorHealth
    {
        $startedAt = microtime(true);

        try {
            $this->performGet(
                endpoint: $this->config->healthEndpoint,
                query: [],
                resource: null
            );

            return new ErpConnectorHealth(
                status:
                    ErpConnectorHealthStatus::Healthy,

                checkedAt:
                    CarbonImmutable::now(),

                latencyMilliseconds:
                    $this->elapsedMilliseconds(
                        $startedAt
                    ),

                message:
                    'The Simulated Sage ERP API is available.'
            );
        } catch (ErpRateLimitException) {
            return new ErpConnectorHealth(
                status:
                    ErpConnectorHealthStatus::Degraded,

                checkedAt:
                    CarbonImmutable::now(),

                latencyMilliseconds:
                    $this->elapsedMilliseconds(
                        $startedAt
                    ),

                message:
                    'The ERP API is available but rate limited.'
            );
        } catch (ErpConnectorException) {
            return new ErpConnectorHealth(
                status:
                    ErpConnectorHealthStatus::Unavailable,

                checkedAt:
                    CarbonImmutable::now(),

                latencyMilliseconds:
                    $this->elapsedMilliseconds(
                        $startedAt
                    ),

                message:
                    'The Simulated Sage ERP API is unavailable.'
            );
        }
    }

    public function fetchPage(
        ErpResource $resource,
        ErpPageRequest $request
    ): ErpPage {
        if (! $this->supports($resource)) {
            throw ErpConfigurationException
                ::unsupportedResource(
                    $resource
                );
        }

        if (
            $request->perPage
            > $this->config->maximumPageSize
        ) {
            throw ErpConfigurationException
                ::invalidSetting(
                    'per_page',
                    'The requested page size exceeds the connector maximum.'
                );
        }

        $endpoint =
            $this->config->endpointFor(
                $resource
            );

        $response = $this->performGet(
            endpoint: $endpoint,
            query: $request
                ->toQueryParameters(),
            resource: $resource
        );

        try {
            return $this->normalizer
                ->normalizePage(
                    resource: $resource,
                    request: $request,
                    response: $response
                );
        } catch (ErpConnectorException $exception) {
            $this->logFailure(
                exception: $exception,
                endpoint: $endpoint
            );

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function performGet(
        string $endpoint,
        array $query,
        ?ErpResource $resource
    ): Response {
        $requestId = (string) Str::uuid();

        try {
            $response = $this
                ->pendingRequest(
                    $requestId
                )
                ->get(
                    $endpoint,
                    $query
                );
        } catch (ConnectionException $exception) {
            $transport =
                ErpTransportException::unreachable(
                    resource: $resource,

                    safeContext: [
                        'endpoint' =>
                            $endpoint,

                        'request_id' =>
                            $requestId,
                    ],

                    previous:
                        $exception
                );

            $this->logFailure(
                exception: $transport,
                endpoint: $endpoint,
                requestId: $requestId
            );

            throw $transport;
        } catch (RequestException $exception) {
            $response =
                $exception->response;
        }

        $statusCode =
            $response->status();

        /*
         * Never follow redirects automatically. Otherwise, the
         * X-ERP-Token header could be forwarded to another host.
         */
        if (
            $statusCode >= 300
            && $statusCode < 400
        ) {
            $invalid =
                ErpInvalidResponseException
                    ::forResource(
                        resource:
                            $resource
                            ?? ErpResource::Products,

                        reason:
                            'redirect responses are not permitted',

                        safeContext: [
                            'status_code' =>
                                $statusCode,

                            'endpoint' =>
                                $endpoint,

                            'request_id' =>
                                $requestId,
                        ]
                    );

            $this->logFailure(
                exception: $invalid,
                endpoint: $endpoint,
                requestId: $requestId
            );

            throw $invalid;
        }

        if (
            $statusCode === 401
            || $statusCode === 403
        ) {
            $authentication =
                ErpAuthenticationException::rejected(
                    resource: $resource,
                    statusCode: $statusCode
                );

            $this->logFailure(
                exception: $authentication,
                endpoint: $endpoint,
                requestId: $requestId
            );

            throw $authentication;
        }

        if ($statusCode === 429) {
            $rateLimit =
                ErpRateLimitException
                    ::forResource(
                        resource: $resource,

                        retryAfterSeconds:
                            $this->retryAfterSeconds(
                                $response
                            )
                    );

            $this->logFailure(
                exception: $rateLimit,
                endpoint: $endpoint,
                requestId: $requestId
            );

            throw $rateLimit;
        }

        if (
            $statusCode === 408
            || $statusCode === 425
            || $statusCode >= 500
        ) {
            $transport =
                ErpTransportException::unreachable(
                    resource: $resource,

                    safeContext: [
                        'status_code' =>
                            $statusCode,

                        'endpoint' =>
                            $endpoint,

                        'request_id' =>
                            $requestId,
                    ]
                );

            $this->logFailure(
                exception: $transport,
                endpoint: $endpoint,
                requestId: $requestId
            );

            throw $transport;
        }

        if (! $response->successful()) {
            $invalid =
                ErpInvalidResponseException
                    ::forResource(
                        resource:
                            $resource
                            ?? ErpResource::Products,

                        reason:
                            'the ERP API returned an unexpected HTTP status',

                        safeContext: [
                            'status_code' =>
                                $statusCode,

                            'endpoint' =>
                                $endpoint,

                            'request_id' =>
                                $requestId,
                        ]
                    );

            $this->logFailure(
                exception: $invalid,
                endpoint: $endpoint,
                requestId: $requestId
            );

            throw $invalid;
        }

        return $response;
    }

    private function pendingRequest(
        string $requestId
    ): PendingRequest {
        return $this->http
            ->baseUrl(
                $this->config->baseUrl
            )
            ->acceptJson()
            ->withHeaders([
                /*
                 * The separate Sage simulator authenticates using a
                 * dedicated ERP header, not HTTP bearer authentication.
                 */
                'X-ERP-Token' =>
                    $this->config->token,

                'User-Agent' =>
                    $this->config->userAgent,

                'X-Request-ID' =>
                    $requestId,

                'X-ERP-Source' =>
                    $this->config->sourceSystem,
            ])
            ->connectTimeout(
                $this->config
                    ->connectTimeoutSeconds
            )
            ->timeout(
                $this->config
                    ->timeoutSeconds
            )
            ->withOptions(
                $this->httpClientOptions()
            )
            ->retry(
                $this->config->retryAttempts,

                function (
                    int $attempt,
                    mixed $exception
                ): int {
                    if (
                        $this->config
                            ->retryDelayMilliseconds
                        === 0
                    ) {
                        return 0;
                    }

                    $delay =
                        $this->config
                            ->retryDelayMilliseconds
                        * (
                            2 ** max(
                                0,
                                $attempt - 1
                            )
                        );

                    return min(
                        $delay,
                        $this->config
                            ->retryMaximumDelayMilliseconds
                    );
                },

                function (
                    mixed $exception,
                    PendingRequest $request
                ): bool {
                    if ($exception === null) {
                        return false;
                    }

                    if (
                        $exception
                        instanceof ConnectionException
                    ) {
                        return true;
                    }

                    if (
                        ! $exception
                        instanceof RequestException
                    ) {
                        return false;
                    }

                    $statusCode =
                        $exception
                            ->response
                            ->status();

                    return $statusCode === 408
                        || $statusCode === 425
                        || $statusCode === 429
                        || $statusCode >= 500;
                },

                /*
                 * Return the final response after retries are
                 * exhausted. performGet() converts the response into
                 * the appropriate ERP-domain exception.
                 */
                throw: false
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function httpClientOptions(): array
    {
        $options = [
            /*
             * Prevent token forwarding through redirects.
             */
            'allow_redirects' =>
                false,

            /*
             * Keep TLS peer and hostname verification enabled.
             */
            'verify' =>
                $this->config
                    ->verifyTls,

            /*
             * HTTP status handling is performed by performGet().
             */
            'http_errors' =>
                false,
        ];

        $curlOptions =
            $this->windowsTlsCurlOptions();

        if ($curlOptions !== []) {
            $options['curl'] =
                $curlOptions;
        }

        return $options;
    }

    /**
     * Allow OpenSSL-backed PHP cURL on Windows to use the native
     * certificate store that contains the trusted Herd root CA.
     *
     * @return array<int, int>
     */
    private function windowsTlsCurlOptions(): array
    {
        if (
            PHP_OS_FAMILY !== 'Windows'
            || ! $this->config->verifyTls
            || ! defined(
                'CURLOPT_SSL_OPTIONS'
            )
        ) {
            return [];
        }

        $sslOptions = 0;

        if (
            defined(
                'CURLSSLOPT_NATIVE_CA'
            )
        ) {
            $sslOptions |=
                (int) constant(
                    'CURLSSLOPT_NATIVE_CA'
                );
        }

        $curlInformation =
            curl_version();

        $sslBackend = strtolower(
            (string) (
                $curlInformation[
                    'ssl_version'
                ]
                ?? ''
            )
        );

        /*
         * Best-effort revocation handling applies only when cURL is
         * using the Windows Schannel backend.
         */
        if (
            str_contains(
                $sslBackend,
                'schannel'
            )
            && defined(
                'CURLSSLOPT_REVOKE_BEST_EFFORT'
            )
        ) {
            $sslOptions |=
                (int) constant(
                    'CURLSSLOPT_REVOKE_BEST_EFFORT'
                );
        }

        if ($sslOptions === 0) {
            return [];
        }

        return [
            (int) constant(
                'CURLOPT_SSL_OPTIONS'
            ) => $sslOptions,
        ];
    }

    private function retryAfterSeconds(
        Response $response
    ): ?int {
        $header = trim(
            $response->header(
                'Retry-After'
            )
        );

        if ($header === '') {
            return null;
        }

        if (
            preg_match(
                '/^\d+$/',
                $header
            ) === 1
        ) {
            return max(
                0,
                (int) $header
            );
        }

        $timestamp =
            strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(
            0,
            $timestamp - time()
        );
    }

    private function elapsedMilliseconds(
        float $startedAt
    ): int {
        return max(
            0,
            (int) round(
                (
                    microtime(true)
                    - $startedAt
                ) * 1000
            )
        );
    }

    private function logFailure(
        ErpConnectorException $exception,
        string $endpoint,
        ?string $requestId = null
    ): void {
        /*
         * Never log the ERP token, X-ERP-Token header, response
         * body, or complete source payload.
         */
        Log::channel(
            $this->config->logChannel
        )->warning(
            'Simulated Sage ERP request failed.',
            [
                'connector' =>
                    $this->name(),

                'source_system' =>
                    $this->sourceSystem(),

                'endpoint' =>
                    $endpoint,

                'request_id' =>
                    $requestId,

                ...$exception->context(),
            ]
        );
    }
}