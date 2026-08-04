<?php

namespace App\Services\Alerts;

use App\Enums\Notifications\NotificationSeverity;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Operator;
use App\Models\ProductionEvent;
use App\Models\User;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Audit\AuditLogService;
use App\Services\ERP\Monitoring\ErpSyncHealthService;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationLinkFactory;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DeterministicAlertEvaluationService
{
    public function __construct(
        private readonly NotificationDeliveryService $delivery,
        private readonly NotificationRecipientResolver $recipients,
        private readonly NotificationLinkFactory $links,
        private readonly ErpSyncHealthService $erpHealth,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * @return array{
     *     conditions_evaluated:int,
     *     notifications_created:int,
     *     duplicate_notifications_skipped:int,
     *     errors:list<string>
     * }
     */
    public function evaluate(): array
    {
        $report = [
            'conditions_evaluated' => 0,
            'notifications_created' => 0,
            'duplicate_notifications_skipped' => 0,
            'errors' => [],
        ];

        $administrators =
            $this->recipients->administrators();

        $evaluators = [
            fn (): array =>
                $this->evaluateErpHealth(
                    $administrators
                ),
            fn (): array =>
                $this->evaluateFailedJobs(
                    $administrators
                ),
            fn (): array =>
                $this->evaluateQueueBacklog(
                    $administrators
                ),
            fn (): array =>
                $this->evaluateUnlinkedOperatorAccounts(
                    $administrators
                ),
            fn (): array =>
                $this->evaluateOperatorsWithoutAssignments(
                    $administrators
                ),
            fn (): array =>
                $this->evaluateCriticalProductionEvents(),
        ];

        foreach ($evaluators as $evaluate) {
            $report['conditions_evaluated']++;

            try {
                $result = $evaluate();

                $report['notifications_created'] +=
                    $result['created'];

                $report[
                    'duplicate_notifications_skipped'
                ] += $result['duplicates'];
            } catch (Throwable $exception) {
                report($exception);

                $report['errors'][] =
                    $exception::class;
            }
        }

        $this->audit->record(
            action:
                'notifications.alerts.evaluated',
            metadata: [
                'conditions_evaluated' =>
                    $report['conditions_evaluated'],
                'notifications_created' =>
                    $report['notifications_created'],
                'duplicate_notifications_skipped' =>
                    $report[
                        'duplicate_notifications_skipped'
                    ],
                'error_count' =>
                    count($report['errors']),
            ]
        );

        return $report;
    }

    /**
     * @param Collection<int, User> $administrators
     *
     * @return array{created:int,duplicates:int}
     */
    private function evaluateErpHealth(
        Collection $administrators
    ): array {
        $snapshot = $this->erpHealth->snapshot(
            (string) config(
                'erp-monitoring.source_system',
                'simulated_sage'
            ),
            (int) config(
                'erp-monitoring.stale_after_minutes',
                45
            )
        );

        if ($snapshot->status === 'healthy') {
            return $this->emptyResult();
        }

        $critical =
            $snapshot->status === 'unhealthy';

        $latestRunUuid =
            $snapshot->latestRun['run_uuid']
            ?? 'none';

        $reason =
            $snapshot->reasons[0]
            ?? 'ERP synchronization health requires review.';

        return $this->dispatch(
            recipients: $administrators,
            notification:
                new SmartFactoryAlertNotification(
                    severity: $critical
                        ? NotificationSeverity::Critical
                        : NotificationSeverity::Warning,
                    category:
                        'erp-health',
                    title: $critical
                        ? 'ERP synchronization unhealthy'
                        : 'ERP synchronization degraded',
                    message:
                        $reason,
                    actionUrl:
                        $this->links->route(
                            'admin.erp-monitoring.index'
                        ),
                    actionLabel:
                        'Open ERP monitoring',
                    fingerprint:
                        'erp-health:'
                        .$snapshot->status
                        .':'
                        .$latestRunUuid
                        .':'
                        .now()->toDateString(),
                    metadata: [
                        'status' =>
                            $snapshot->status,
                        'latest_run_uuid' =>
                            (string) $latestRunUuid,
                    ]
                )
        );
    }

    /**
     * @param Collection<int, User> $administrators
     *
     * @return array{created:int,duplicates:int}
     */
    private function evaluateFailedJobs(
        Collection $administrators
    ): array {
        if (! Schema::hasTable('failed_jobs')) {
            return $this->emptyResult();
        }

        $count =
            (int) DB::table(
                'failed_jobs'
            )->count();

        if ($count < 1) {
            return $this->emptyResult();
        }

        $latest = DB::table(
            'failed_jobs'
        )
            ->latest('id')
            ->first([
                'id',
                'uuid',
            ]);

        $latestIdentity =
            $latest?->uuid
            ?? (string) ($latest?->id ?? 'unknown');

        return $this->dispatch(
            recipients: $administrators,
            notification:
                new SmartFactoryAlertNotification(
                    severity:
                        NotificationSeverity::Warning,
                    category:
                        'queue',
                    title:
                        'Failed queue jobs require review',
                    message:
                        "{$count} failed queue job(s) are recorded.",
                    actionUrl:
                        $this->links->route(
                            'admin.dashboard'
                        ),
                    actionLabel:
                        'Open administrator operations',
                    fingerprint:
                        'failed-jobs:'
                        .$latestIdentity
                        .':'
                        .$count,
                    metadata: [
                        'failed_job_count' =>
                            $count,
                    ]
                )
        );
    }

    /**
     * @param Collection<int, User> $administrators
     *
     * @return array{created:int,duplicates:int}
     */
    private function evaluateQueueBacklog(
        Collection $administrators
    ): array {
        if (! Schema::hasTable('jobs')) {
            return $this->emptyResult();
        }

        $count =
            (int) DB::table('jobs')->count();

        $threshold = max(
            1,
            (int) config(
                'admin-operations.queue_backlog_warning_threshold',
                50
            )
        );

        if ($count < $threshold) {
            return $this->emptyResult();
        }

        $oldestId =
            DB::table('jobs')->min('id')
            ?? 'unknown';

        return $this->dispatch(
            recipients: $administrators,
            notification:
                new SmartFactoryAlertNotification(
                    severity:
                        NotificationSeverity::Warning,
                    category:
                        'queue',
                    title:
                        'Queue backlog threshold reached',
                    message:
                        "{$count} queued job(s) are waiting.",
                    actionUrl:
                        $this->links->route(
                            'admin.dashboard'
                        ),
                    actionLabel:
                        'Open administrator operations',
                    fingerprint:
                        'queue-backlog:'
                        .$oldestId
                        .':'
                        .$count
                        .':'
                        .now()->toDateString(),
                    metadata: [
                        'queued_job_count' =>
                            $count,
                        'threshold' =>
                            $threshold,
                    ]
                )
        );
    }

    /**
     * @param Collection<int, User> $administrators
     *
     * @return array{created:int,duplicates:int}
     */
    private function evaluateUnlinkedOperatorAccounts(
        Collection $administrators
    ): array {
        $operatorUsers =
            User::query()
                ->where('is_active', true)
                ->role(
                    RoleName::Operator->value
                )
                ->orderBy('users.id')
                ->pluck('users.id')
                ->map(
                    static fn (mixed $id): int =>
                        (int) $id
                )
                ->all();

        if ($operatorUsers === []) {
            return $this->emptyResult();
        }

        $linkedIds =
            Operator::query()
                ->whereIn(
                    'user_id',
                    $operatorUsers
                )
                ->pluck('user_id')
                ->map(
                    static fn (mixed $id): int =>
                        (int) $id
                )
                ->all();

        $missingIds = array_values(
            array_diff(
                $operatorUsers,
                $linkedIds
            )
        );

        if ($missingIds === []) {
            return $this->emptyResult();
        }

        sort($missingIds);

        return $this->dispatch(
            recipients: $administrators,
            notification:
                new SmartFactoryAlertNotification(
                    severity:
                        NotificationSeverity::Warning,
                    category:
                        'operator-readiness',
                    title:
                        'Unlinked Operator accounts',
                    message:
                        count($missingIds)
                        .' active Operator account(s) are not linked to ERP Operator records.',
                    actionUrl:
                        $this->links->route(
                            'admin.operator-administration.index'
                        ),
                    actionLabel:
                        'Review Operator accounts',
                    fingerprint:
                        'unlinked-operator-users:'
                        .hash(
                            'sha256',
                            implode(
                                ',',
                                $missingIds
                            )
                        ),
                    metadata: [
                        'account_count' =>
                            count($missingIds),
                    ]
                )
        );
    }

    /**
     * @param Collection<int, User> $administrators
     *
     * @return array{created:int,duplicates:int}
     */
    private function evaluateOperatorsWithoutAssignments(
        Collection $administrators
    ): array {
        $today =
            now()->toDateString();

        $operatorIds =
            Operator::query()
                ->where('is_active', true)
                ->whereNotNull('user_id')
                ->whereDoesntHave(
                    'assignments',
                    function ($query) use (
                        $today
                    ): void {
                        $query
                            ->where(
                                'is_active',
                                true
                            )
                            ->whereDate(
                                'starts_on',
                                '<=',
                                $today
                            )
                            ->where(
                                function (
                                    $query
                                ) use (
                                    $today
                                ): void {
                                    $query
                                        ->whereNull(
                                            'ends_on'
                                        )
                                        ->orWhereDate(
                                            'ends_on',
                                            '>=',
                                            $today
                                        );
                                }
                            );
                    }
                )
                ->orderBy('operators.id')
                ->pluck('operators.id')
                ->map(
                    static fn (mixed $id): int =>
                        (int) $id
                )
                ->all();

        if ($operatorIds === []) {
            return $this->emptyResult();
        }

        return $this->dispatch(
            recipients: $administrators,
            notification:
                new SmartFactoryAlertNotification(
                    severity:
                        NotificationSeverity::Warning,
                    category:
                        'operator-readiness',
                    title:
                        'Operators without active assignments',
                    message:
                        count($operatorIds)
                        .' linked active Operator(s) have no current production assignment.',
                    actionUrl:
                        $this->links->route(
                            'admin.operator-administration.index'
                        ),
                    actionLabel:
                        'Review assignments',
                    fingerprint:
                        'operators-without-assignment:'
                        .hash(
                            'sha256',
                            implode(
                                ',',
                                $operatorIds
                            )
                        ),
                    metadata: [
                        'operator_count' =>
                            count($operatorIds),
                    ]
                )
        );
    }

    /**
     * @return array{created:int,duplicates:int}
     */
    private function evaluateCriticalProductionEvents(): array
    {
        $events =
            ProductionEvent::query()
                ->where(
                    'severity',
                    'critical'
                )
                ->where(
                    'is_resolved',
                    false
                )
                ->where(
                    'created_at',
                    '>=',
                    now()->subDays(
                        max(
                            1,
                            (int) config(
                                'smartfactory-notifications.critical_event_lookback_days',
                                30
                            )
                        )
                    )
                )
                ->with([
                    'productionLine',
                    'machine',
                ])
                ->orderBy('id')
                ->get();

        if ($events->isEmpty()) {
            return $this->emptyResult();
        }

        $productionRecipients =
            $this->recipients
                ->usersWithPermission(
                    PermissionName
                        ::ViewProductionEvents
                );

        $maintenanceRecipients =
            $this->recipients
                ->usersWithRole(
                    RoleName::MaintenanceManager
                );

        $created = 0;
        $duplicates = 0;

        foreach ($events as $event) {
            $lineName =
                $event->productionLine?->name
                ?? 'Unknown production line';

            $result = $this->dispatch(
                recipients:
                    $productionRecipients,
                notification:
                    new SmartFactoryAlertNotification(
                        severity:
                            NotificationSeverity::Critical,
                        category:
                            'production-event',
                        title:
                            'Critical unresolved production event',
                        message:
                            "{$event->event_number}: {$event->title} on {$lineName}.",
                        actionUrl:
                            $this->links->route(
                                'production.supervisor.events.show',
                                $event
                            ),
                        actionLabel:
                            'Review event',
                        fingerprint:
                            'critical-production-event:'
                            .$event->getKey()
                            .':production',
                        metadata: [
                            'production_event_id' =>
                                (int) $event->getKey(),
                            'production_line_id' =>
                                (int) $event
                                    ->production_line_id,
                            'machine_id' =>
                                $event->machine_id,
                        ]
                    )
            );

            $created += $result['created'];
            $duplicates +=
                $result['duplicates'];

            if (! in_array(
                $event->event_type->value,
                [
                    'downtime',
                    'machine_incident',
                ],
                true
            )) {
                continue;
            }

            $result = $this->dispatch(
                recipients:
                    $maintenanceRecipients,
                notification:
                    new SmartFactoryAlertNotification(
                        severity:
                            NotificationSeverity::Critical,
                        category:
                            'maintenance-incident',
                        title:
                            'Critical unresolved machine incident',
                        message:
                            "{$event->event_number}: {$event->title} on {$lineName}.",
                        actionUrl:
                            $this->links->route(
                                'dashboard'
                            ),
                        actionLabel:
                            'Open maintenance dashboard',
                        fingerprint:
                            'critical-production-event:'
                            .$event->getKey()
                            .':maintenance',
                        metadata: [
                            'production_event_id' =>
                                (int) $event->getKey(),
                            'production_line_id' =>
                                (int) $event
                                    ->production_line_id,
                            'machine_id' =>
                                $event->machine_id,
                        ]
                    )
            );

            $created += $result['created'];
            $duplicates +=
                $result['duplicates'];
        }

        return [
            'created' => $created,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param iterable<User> $recipients
     *
     * @return array{created:int,duplicates:int}
     */
    private function dispatch(
        iterable $recipients,
        SmartFactoryAlertNotification $notification
    ): array {
        $recipientList = [];

        foreach ($recipients as $recipient) {
            $recipientList[
                (int) $recipient->getKey()
            ] = $recipient;
        }

        $created =
            $this->delivery->sendToMany(
                $recipientList,
                $notification
            );

        return [
            'created' =>
                $created,
            'duplicates' =>
                max(
                    0,
                    count($recipientList)
                    - $created
                ),
        ];
    }

    /**
     * @return array{created:int,duplicates:int}
     */
    private function emptyResult(): array
    {
        return [
            'created' => 0,
            'duplicates' => 0,
        ];
    }
}
