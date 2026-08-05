<?php

namespace App\Observers\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\RoleName;
use App\Models\ProductionEvent;
use App\Models\User;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationLinkFactory;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProductionEventNotificationObserver
{
    public function __construct(
        private readonly NotificationDeliveryService $delivery,
        private readonly NotificationRecipientResolver $recipients,
        private readonly NotificationLinkFactory $links,
    ) {
    }

    public function created(
        ProductionEvent $event
    ): void {
        $event->loadMissing([
            'productionLine',
            'machine',
        ]);

        $availabilityEvent = in_array(
            $event->event_type,
            [
                ProductionEventType::Downtime,
                ProductionEventType::MachineIncident,
            ],
            true
        );

        $operatorComment =
            $event->event_type
            === ProductionEventType::Comment;

        $critical =
            $event->severity
            === ProductionEventSeverity::Critical;

        if (
            ! $availabilityEvent
            && ! $operatorComment
            && ! $critical
        ) {
            return;
        }

        $this->notifySupervisors(
            $event
        );

        if ($availabilityEvent) {
            $this->notifyMaintenanceManagers(
                $event
            );
        }

        if ($critical) {
            $this->notifyCriticalEscalation(
                $event
            );
        }
    }

    public function updated(
        ProductionEvent $event
    ): void {
        if (
            ! $event->wasChanged(
                'is_resolved'
            )
            || ! $event->is_resolved
        ) {
            return;
        }

        $event->loadMissing([
            'productionLine',
            'machine',
            'operator.user',
            'reportedBy',
            'resolvedBy',
        ]);

        $recipient =
            $this->resolvedOperatorRecipient(
                $event
            );

        if (
            $recipient === null
            || $recipient->getKey()
                === $event->resolved_by
        ) {
            return;
        }

        $lineName =
            $event->productionLine?->name
            ?? 'the production line';

        $notification =
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Information,
                category:
                    'production-event-resolution',
                title:
                    'Reported production event resolved',
                message:
                    "Event {$event->event_number} ({$event->title}) on {$lineName} was marked as resolved.",
                actionUrl:
                    $this->links->route(
                        'production.operator.events.show',
                        $event
                    ),
                actionLabel:
                    'Review resolved event',
                fingerprint:
                    'production-event:'
                    .$event->getKey()
                    .':resolved:version:'
                    .$event->lock_version,
                metadata:
                    $this->metadata($event)
            );

        $this->sendOneSafely(
            recipient: $recipient,
            notification: $notification,
            event: $event,
            recipientGroup: 'reporting-operator'
        );
    }

    private function notifySupervisors(
        ProductionEvent $event
    ): void {
        $notification =
            $this->eventNotification(
                event: $event,
                category: 'production-event',
                title:
                    $this->eventTitle($event),
                actionUrl:
                    $this->links->route(
                        'production.supervisor.events.show',
                        $event
                    ),
                actionLabel:
                    'Review event',
                fingerprintSuffix:
                    'production-supervisor'
            );

        $this->sendManySafely(
            recipients:
                $this->withoutReporter(
                    $this->recipients
                        ->usersWithRole(
                            RoleName::ProductionSupervisor
                        ),
                    $event
                ),
            notification: $notification,
            event: $event,
            recipientGroup: 'production-supervisor'
        );
    }

    private function notifyMaintenanceManagers(
        ProductionEvent $event
    ): void {
        $notification =
            $this->eventNotification(
                event: $event,
                category:
                    'maintenance-incident',
                title:
                    $event->event_type
                    === ProductionEventType::MachineIncident
                        ? 'Machine incident reported'
                        : 'Production downtime reported',
                actionUrl:
                    $this->links->route(
                        'dashboard'
                    ),
                actionLabel:
                    'Open maintenance dashboard',
                fingerprintSuffix:
                    'maintenance-manager'
            );

        $this->sendManySafely(
            recipients:
                $this->withoutReporter(
                    $this->recipients
                        ->usersWithRole(
                            RoleName::MaintenanceManager
                        ),
                    $event
                ),
            notification: $notification,
            event: $event,
            recipientGroup: 'maintenance-manager'
        );
    }

    private function notifyCriticalEscalation(
        ProductionEvent $event
    ): void {
        $managerNotification =
            $this->eventNotification(
                event: $event,
                category:
                    'production-event-escalation',
                title:
                    'Critical production event escalation',
                actionUrl:
                    $this->links->route(
                        'dashboard'
                    ),
                actionLabel:
                    'Open production dashboard',
                fingerprintSuffix:
                    'production-manager'
            );

        $this->sendManySafely(
            recipients:
                $this->withoutReporter(
                    $this->recipients
                        ->usersWithRole(
                            RoleName::ProductionManager
                        ),
                    $event
                ),
            notification:
                $managerNotification,
            event: $event,
            recipientGroup:
                'production-manager'
        );

        $administratorNotification =
            $this->eventNotification(
                event: $event,
                category:
                    'production-event-escalation',
                title:
                    'Critical production event escalation',
                actionUrl:
                    $this->links->route(
                        'production.supervisor.events.show',
                        $event
                    ),
                actionLabel:
                    'Review event',
                fingerprintSuffix:
                    'administrator'
            );

        $this->sendManySafely(
            recipients:
                $this->withoutReporter(
                    $this->recipients
                        ->usersWithRole(
                            RoleName::Administrator
                        ),
                    $event
                ),
            notification:
                $administratorNotification,
            event: $event,
            recipientGroup:
                'administrator'
        );
    }

    private function eventNotification(
        ProductionEvent $event,
        string $category,
        string $title,
        string $actionUrl,
        string $actionLabel,
        string $fingerprintSuffix
    ): SmartFactoryAlertNotification {
        $lineName =
            $event->productionLine?->name
            ?? 'Unknown production line';

        $machineSuffix =
            $event->machine?->name !== null
                ? ' on '.$event->machine->name
                : '';

        return new SmartFactoryAlertNotification(
            severity:
                $this->notificationSeverity(
                    $event->severity
                ),
            category:
                $category,
            title:
                $title,
            message:
                "{$event->event_number}: {$event->title} on {$lineName}{$machineSuffix}. Severity: {$event->severity->label()}.",
            actionUrl:
                $actionUrl,
            actionLabel:
                $actionLabel,
            fingerprint:
                'production-event:'
                .$event->getKey()
                .':created:'
                .$fingerprintSuffix,
            metadata:
                $this->metadata($event)
        );
    }

    private function eventTitle(
        ProductionEvent $event
    ): string {
        return match ($event->event_type) {
            ProductionEventType::Downtime =>
                'Production downtime reported',

            ProductionEventType::MachineIncident =>
                'Machine incident reported',

            ProductionEventType::Comment =>
                'Operator production comment reported',

            default =>
                'Critical production event reported',
        };
    }

    private function notificationSeverity(
        ProductionEventSeverity $severity
    ): NotificationSeverity {
        return match ($severity) {
            ProductionEventSeverity::Information =>
                NotificationSeverity::Information,

            ProductionEventSeverity::Warning =>
                NotificationSeverity::Warning,

            ProductionEventSeverity::Critical =>
                NotificationSeverity::Critical,
        };
    }

    private function resolvedOperatorRecipient(
        ProductionEvent $event
    ): ?User {
        $reportedBy =
            $event->reportedBy;

        if (
            $reportedBy instanceof User
            && $reportedBy->is_active
            && $reportedBy->hasRole(
                RoleName::Operator->value
            )
        ) {
            return $reportedBy;
        }

        $operatorUser =
            $event->operator?->user;

        if (
            $operatorUser instanceof User
            && $operatorUser->is_active
            && $operatorUser->hasRole(
                RoleName::Operator->value
            )
        ) {
            return $operatorUser;
        }

        return null;
    }

    /**
     * @param iterable<User> $recipients
     *
     * @return list<User>
     */
    private function withoutReporter(
        iterable $recipients,
        ProductionEvent $event
    ): array {
        $filtered = [];

        foreach ($recipients as $recipient) {
            if (
                $recipient->getKey()
                === $event->reported_by
            ) {
                continue;
            }

            $filtered[] = $recipient;
        }

        return $filtered;
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    private function metadata(
        ProductionEvent $event
    ): array {
        return [
            'production_event_id' =>
                (int) $event->getKey(),

            'production_line_id' =>
                (int) $event
                    ->production_line_id,

            'machine_id' =>
                $event->machine_id,

            'operator_id' =>
                $event->operator_id,

            'reported_by' =>
                $event->reported_by,

            'event_type' =>
                $event->event_type->value,

            'severity' =>
                $event->severity->value,

            'is_resolved' =>
                (bool) $event->is_resolved,
        ];
    }

    /**
     * @param iterable<User> $recipients
     */
    private function sendManySafely(
        iterable $recipients,
        SmartFactoryAlertNotification $notification,
        ProductionEvent $event,
        string $recipientGroup
    ): void {
        try {
            $this->delivery->sendToMany(
                $recipients,
                $notification
            );
        } catch (Throwable $exception) {
            $this->logDeliveryFailure(
                event: $event,
                recipientGroup:
                    $recipientGroup,
                exception: $exception
            );
        }
    }

    private function sendOneSafely(
        User $recipient,
        SmartFactoryAlertNotification $notification,
        ProductionEvent $event,
        string $recipientGroup
    ): void {
        try {
            $this->delivery->send(
                $recipient,
                $notification
            );
        } catch (Throwable $exception) {
            $this->logDeliveryFailure(
                event: $event,
                recipientGroup:
                    $recipientGroup,
                exception: $exception
            );
        }
    }

    private function logDeliveryFailure(
        ProductionEvent $event,
        string $recipientGroup,
        Throwable $exception
    ): void {
        Log::warning(
            'A production-event notification failed safely.',
            [
                'production_event_id' =>
                    (int) $event->getKey(),

                'recipient_group' =>
                    $recipientGroup,

                'exception_class' =>
                    $exception::class,
            ]
        );
    }
}
