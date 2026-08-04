<?php

namespace App\Observers\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use App\Enums\PermissionName;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\RoleName;
use App\Models\ProductionEvent;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationLinkFactory;
use App\Services\Notifications\NotificationRecipientResolver;

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
        if (
            $event->severity
            !== ProductionEventSeverity::Critical
        ) {
            return;
        }

        $event->loadMissing([
            'productionLine',
            'machine',
        ]);

        $lineName =
            $event->productionLine?->name
            ?? 'Unknown production line';

        $machineSuffix =
            $event->machine?->name !== null
                ? ' on '.$event->machine->name
                : '';

        $productionNotification =
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Critical,
                category:
                    'production-event',
                title:
                    'Critical production event',
                message:
                    "{$event->event_number}: {$event->title} on {$lineName}{$machineSuffix}.",
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
            );

        $this->delivery->sendToMany(
            $this->recipients
                ->usersWithPermission(
                    PermissionName
                        ::ViewProductionEvents
                ),
            $productionNotification
        );

        if (! in_array(
            $event->event_type,
            [
                ProductionEventType::Downtime,
                ProductionEventType::MachineIncident,
            ],
            true
        )) {
            return;
        }

        $maintenanceNotification =
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Critical,
                category:
                    'maintenance-incident',
                title:
                    'Critical machine or downtime incident',
                message:
                    "{$event->event_number}: {$event->title} on {$lineName}{$machineSuffix}.",
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
            );

        $this->delivery->sendToMany(
            $this->recipients
                ->usersWithRole(
                    RoleName::MaintenanceManager
                ),
            $maintenanceNotification
        );
    }
}
