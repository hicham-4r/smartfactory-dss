<?php

namespace App\Services\Admin;

use App\DTOs\Admin\AdministratorOperationsSnapshot;
use App\DTOs\ERP\Monitoring\ErpSyncHealthSnapshot;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Operator;
use App\Models\User;
use App\Services\AI\AiServiceHealthService;
use App\Services\ERP\Monitoring\ErpSyncHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class AdministratorOperationsService
{
    public function __construct(
        private readonly ErpSyncHealthService $erpHealth,
        private readonly AiServiceHealthService $aiHealth
    ) {
    }

    public function build(): AdministratorOperationsSnapshot
    {
        $generatedAt = CarbonImmutable::now();

        $users = $this->userSummary();
        $operators = $this->operatorSummary(
            $generatedAt
        );

        [
            $erpHealth,
            $erpHealthMessage,
        ] = $this->erpHealthSnapshot();

        $queue = $this->queueSummary();
        $applicationHealth =
            $this->applicationHealth(
                $this->currentRequestId()
            );

        $auditItems = $this->recentAuditItems();

        $alerts = $this->alerts(
            users: $users,
            operators: $operators,
            erpHealth: $erpHealth,
            erpHealthMessage: $erpHealthMessage,
            queue: $queue,
            applicationHealth: $applicationHealth,
        );

        return new AdministratorOperationsSnapshot(
            generatedAt: $generatedAt,
            users: $users,
            operators: $operators,
            erpHealth: $erpHealth,
            erpHealthMessage: $erpHealthMessage,
            queue: $queue,
            applicationHealth: $applicationHealth,
            auditItems: $auditItems,
            alerts: $alerts,
            aiStatus:
                (string) (
                    $applicationHealth[
                        'ai_service'
                    ]['status']
                    ?? 'unavailable'
                ),
        );
    }

    /**
     * @return array<string, int>
     */
    private function userSummary(): array
    {
        $total = User::query()->count();
        $active = User::query()
            ->where('is_active', true)
            ->count();

        $operatorAccountsWithoutProfile =
            User::query()
                ->role(RoleName::Operator->value)
                ->where('is_active', true)
                ->whereNotIn(
                    'id',
                    Operator::query()
                        ->select('user_id')
                        ->whereNotNull('user_id')
                )
                ->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => max(0, $total - $active),
            'must_change_password' =>
                User::query()
                    ->where(
                        'must_change_password',
                        true
                    )
                    ->count(),
            'locked' =>
                User::query()
                    ->whereNotNull('locked_until')
                    ->where(
                        'locked_until',
                        '>',
                        now()
                    )
                    ->count(),
            'operator_accounts_without_profile' =>
                $operatorAccountsWithoutProfile,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function operatorSummary(
        CarbonImmutable $referenceDate
    ): array {
        $active = Operator::query()
            ->where('is_active', true)
            ->count();

        return [
            'total' => Operator::query()->count(),
            'active' => $active,
            'inactive' =>
                Operator::query()
                    ->where('is_active', false)
                    ->count(),
            'active_without_account' =>
                Operator::query()
                    ->where('is_active', true)
                    ->whereNull('user_id')
                    ->count(),
            'active_without_current_assignment' =>
                Operator::query()
                    ->where('is_active', true)
                    ->whereDoesntHave(
                        'assignments',
                        fn ($query) =>
                            $query->current(
                                $referenceDate
                            )
                    )
                    ->count(),
        ];
    }

    /**
     * @return array{0:?ErpSyncHealthSnapshot,1:?string}
     */
    private function erpHealthSnapshot(): array
    {
        try {
            $sourceSystem = (string) config(
                'erp-monitoring.source_system',
                'simulated_sage'
            );

            $staleAfterMinutes = max(
                1,
                min(
                    10080,
                    (int) config(
                        'erp-monitoring.stale_after_minutes',
                        45
                    )
                )
            );

            return [
                $this->erpHealth->snapshot(
                    $sourceSystem,
                    $staleAfterMinutes
                ),
                null,
            ];
        } catch (Throwable) {
            return [
                null,
                'ERP synchronization health could not be evaluated safely.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queueSummary(): array
    {
        $connectionName = (string) config(
            'queue.default',
            'sync'
        );

        $summary = [
            'connection' => $connectionName,
            'status' => 'not_monitored',
            'backlog' => null,
            'ready' => null,
            'reserved' => null,
            'delayed' => null,
            'failed' => null,
            'message' =>
                'Queue metrics are available for database-backed queues.',
        ];

        try {
            if ($connectionName === 'database') {
                $queueConfiguration = config(
                    'queue.connections.database',
                    []
                );

                $databaseName =
                    is_array($queueConfiguration)
                    && is_string(
                        $queueConfiguration['connection']
                            ?? null
                    )
                    && trim(
                        $queueConfiguration['connection']
                    ) !== ''
                        ? trim(
                            $queueConfiguration['connection']
                        )
                        : null;

                $table =
                    is_array($queueConfiguration)
                    && is_string(
                        $queueConfiguration['table']
                            ?? null
                    )
                        ? trim(
                            $queueConfiguration['table']
                        )
                        : 'jobs';

                $table = $table !== ''
                    ? $table
                    : 'jobs';

                $connection = DB::connection(
                    $databaseName
                );

                if (
                    ! $connection
                        ->getSchemaBuilder()
                        ->hasTable($table)
                ) {
                    return [
                        ...$summary,
                        'status' => 'unavailable',
                        'message' =>
                            'The configured queue table is missing.',
                    ];
                }

                $timestamp = now()->timestamp;

                $summary = [
                    ...$summary,
                    'status' => 'available',
                    'backlog' =>
                        $connection
                            ->table($table)
                            ->count(),
                    'ready' =>
                        $connection
                            ->table($table)
                            ->whereNull('reserved_at')
                            ->where(
                                'available_at',
                                '<=',
                                $timestamp
                            )
                            ->count(),
                    'reserved' =>
                        $connection
                            ->table($table)
                            ->whereNotNull('reserved_at')
                            ->count(),
                    'delayed' =>
                        $connection
                            ->table($table)
                            ->whereNull('reserved_at')
                            ->where(
                                'available_at',
                                '>',
                                $timestamp
                            )
                            ->count(),
                    'message' => null,
                ];
            }

            $summary['failed'] =
                $this->failedJobCount();
        } catch (Throwable) {
            return [
                ...$summary,
                'status' => 'unavailable',
                'message' =>
                    'Queue health could not be evaluated safely.',
            ];
        }

        return $summary;
    }

    private function failedJobCount(): ?int
    {
        $failedConfiguration = config(
            'queue.failed',
            []
        );

        if (! is_array($failedConfiguration)) {
            return null;
        }

        $driver = (string) (
            $failedConfiguration['driver']
            ?? ''
        );

        if (
            ! in_array(
                $driver,
                [
                    'database-uuids',
                    'database',
                ],
                true
            )
        ) {
            return null;
        }

        $databaseName =
            is_string(
                $failedConfiguration['database']
                    ?? null
            )
            && trim(
                $failedConfiguration['database']
            ) !== ''
                ? trim(
                    $failedConfiguration['database']
                )
                : null;

        $table =
            is_string(
                $failedConfiguration['table']
                    ?? null
            )
                ? trim(
                    $failedConfiguration['table']
                )
                : 'failed_jobs';

        $table = $table !== ''
            ? $table
            : 'failed_jobs';

        $connection = DB::connection(
            $databaseName
        );

        if (
            ! $connection
                ->getSchemaBuilder()
                ->hasTable($table)
        ) {
            return null;
        }

        return $connection
            ->table($table)
            ->count();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function applicationHealth(
        string $requestId
    ): array {
        return [
            'database' =>
                $this->databaseHealth(),
            'cache' =>
                $this->cacheHealth(),
            'ai_service' =>
                $this->aiHealth
                    ->snapshot(
                        $requestId
                    )
                    ->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseHealth(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');

            return [
                'status' => 'available',
                'latency_ms' => max(
                    0,
                    (int) round(
                        (
                            microtime(true)
                            - $startedAt
                        ) * 1000
                    )
                ),
                'message' => null,
            ];
        } catch (Throwable) {
            return [
                'status' => 'unavailable',
                'latency_ms' => null,
                'message' =>
                    'The application database is unavailable.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheHealth(): array
    {
        $key = 'admin-operations-health:'
            .Str::uuid();

        $startedAt = microtime(true);

        try {
            Cache::put(
                $key,
                'available',
                now()->addSeconds(10)
            );

            $available = Cache::get($key)
                === 'available';

            Cache::forget($key);

            return [
                'status' =>
                    $available
                        ? 'available'
                        : 'unavailable',
                'latency_ms' => max(
                    0,
                    (int) round(
                        (
                            microtime(true)
                            - $startedAt
                        ) * 1000
                    )
                ),
                'message' =>
                    $available
                        ? null
                        : 'The cache read/write check failed.',
            ];
        } catch (Throwable) {
            return [
                'status' => 'unavailable',
                'latency_ms' => null,
                'message' =>
                    'The application cache is unavailable.',
            ];
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function recentAuditItems(): array
    {
        $limit = max(
            1,
            min(
                50,
                (int) config(
                    'admin-operations.audit_limit',
                    10
                )
            )
        );

        try {
            return AuditLog::query()
                ->with([
                    'actor:id,name,email',
                ])
                ->latest('occurred_at')
                ->latest('id')
                ->limit($limit)
                ->get()
                ->map(
                    static fn (
                        AuditLog $audit
                    ): array => [
                        'action' =>
                            $audit->action,
                        'actor_name' =>
                            $audit->actor?->name
                            ?? 'System',
                        'actor_email' =>
                            $audit->actor?->email,
                        'subject_type' =>
                            is_string(
                                $audit
                                    ->auditable_type
                            )
                            && trim(
                                $audit
                                    ->auditable_type
                            ) !== ''
                                ? class_basename(
                                    $audit
                                        ->auditable_type
                                )
                                : null,
                        'subject_id' =>
                            $audit->auditable_id,
                        'occurred_at' =>
                            $audit->occurred_at
                                ?->timezone(
                                    (string) config(
                                        'app.timezone',
                                        'UTC'
                                    )
                                )
                                ->format(
                                    'Y-m-d H:i:s'
                                ),
                    ]
                )
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function currentRequestId(): string
    {
        $requestId = request()
            ->attributes
            ->get(
                'request_id'
            );

        if (
            is_string($requestId)
            && preg_match(
                '/^[A-Za-z0-9._:-]{1,100}$/',
                $requestId
            ) === 1
        ) {
            return $requestId;
        }

        return (string) Str::uuid();
    }

    /**
     * @param array<string, int> $users
     * @param array<string, int> $operators
     * @param array<string, mixed> $queue
     * @param array<string, array<string, mixed>> $applicationHealth
     *
     * @return list<array{severity:string,title:string,message:string}>
     */
    private function alerts(
        array $users,
        array $operators,
        ?ErpSyncHealthSnapshot $erpHealth,
        ?string $erpHealthMessage,
        array $queue,
        array $applicationHealth,
    ): array {
        $alerts = [];

        if (
            (
                $applicationHealth['database']['status']
                ?? 'unavailable'
            ) !== 'available'
        ) {
            $alerts[] = [
                'severity' => 'critical',
                'title' => 'Database unavailable',
                'message' =>
                    'The application database health check failed.',
            ];
        }

        if (
            (
                $applicationHealth['cache']['status']
                ?? 'unavailable'
            ) !== 'available'
        ) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Cache unavailable',
                'message' =>
                    'The application cache health check failed.',
            ];
        }

        if ($erpHealth === null) {
            $alerts[] = [
                'severity' => 'critical',
                'title' => 'ERP health unavailable',
                'message' =>
                    $erpHealthMessage
                    ?? 'ERP synchronization health is unavailable.',
            ];
        } elseif ($erpHealth->isUnhealthy()) {
            $alerts[] = [
                'severity' => 'critical',
                'title' => 'ERP synchronization unhealthy',
                'message' =>
                    $erpHealth->reasons[0]
                    ?? 'The ERP synchronization health service reported a critical condition.',
            ];
        } elseif ($erpHealth->isDegraded()) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'ERP synchronization degraded',
                'message' =>
                    $erpHealth->reasons[0]
                    ?? 'The ERP synchronization health service reported a warning.',
            ];
        }

        $failedJobs = $queue['failed'] ?? null;

        if (
            is_int($failedJobs)
            && $failedJobs >= max(
                1,
                (int) config(
                    'admin-operations.failed_jobs_warning_threshold',
                    1
                )
            )
        ) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Failed queue jobs',
                'message' =>
                    "{$failedJobs} failed queue job(s) require review.",
            ];
        }

        $backlog = $queue['backlog'] ?? null;

        if (
            is_int($backlog)
            && $backlog >= max(
                1,
                (int) config(
                    'admin-operations.queue_backlog_warning_threshold',
                    50
                )
            )
        ) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Queue backlog',
                'message' =>
                    "{$backlog} queued job(s) are waiting.",
            ];
        }

        if (
            $users[
                'operator_accounts_without_profile'
            ] > 0
        ) {
            $count = $users[
                'operator_accounts_without_profile'
            ];

            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Unlinked Operator accounts',
                'message' =>
                    "{$count} active Operator account(s) are not linked to ERP Operator records.",
            ];
        }

        if (
            $operators[
                'active_without_current_assignment'
            ] > 0
        ) {
            $count = $operators[
                'active_without_current_assignment'
            ];

            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Operators without assignments',
                'message' =>
                    "{$count} active Operator record(s) have no current line-and-shift assignment.",
            ];
        }

        $aiStatus =
            (string) (
                $applicationHealth[
                    'ai_service'
                ]['status']
                ?? 'unavailable'
            );

        if (
            in_array(
                $aiStatus,
                [
                    'degraded',
                    'unavailable',
                ],
                true
            )
        ) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'AI service unavailable',
                'message' =>
                    (string) (
                        $applicationHealth[
                            'ai_service'
                        ]['message']
                        ?? 'The FastAPI foundation requires review.'
                    ),
            ];
        } elseif (
            $aiStatus ===
                'not_configured'
        ) {
            $alerts[] = [
                'severity' => 'information',
                'title' => 'AI service not configured',
                'message' =>
                    'The FastAPI foundation is installed but not enabled for Laravel.',
            ];
        }

        if (
            $users['must_change_password'] > 0
        ) {
            $count = $users[
                'must_change_password'
            ];

            $alerts[] = [
                'severity' => 'information',
                'title' => 'Password changes pending',
                'message' =>
                    "{$count} account(s) must change their temporary password.",
            ];
        }

        return $alerts;
    }
}