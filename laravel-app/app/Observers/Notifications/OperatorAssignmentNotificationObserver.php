<?php

namespace App\Observers\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use App\Models\OperatorAssignment;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationLinkFactory;

final class OperatorAssignmentNotificationObserver
{
    public function __construct(
        private readonly NotificationDeliveryService $delivery,
        private readonly NotificationLinkFactory $links,
    ) {
    }

    public function created(
        OperatorAssignment $assignment
    ): void {
        $this->notify(
            assignment: $assignment,
            event: 'created',
            title: 'Production assignment created',
            messagePrefix:
                'A new production assignment was created'
        );
    }

    public function updated(
        OperatorAssignment $assignment
    ): void {
        if (! $assignment->wasChanged([
            'production_line_id',
            'shift_id',
            'starts_on',
            'ends_on',
            'is_primary',
            'is_active',
        ])) {
            return;
        }

        $ended =
            $assignment->wasChanged(
                'is_active'
            )
            && ! $assignment->is_active;

        $this->notify(
            assignment: $assignment,
            event: $ended
                ? 'ended'
                : 'updated',
            title: $ended
                ? 'Production assignment ended'
                : 'Production assignment updated',
            messagePrefix: $ended
                ? 'Your production assignment was ended'
                : 'Your production assignment was updated'
        );
    }

    private function notify(
        OperatorAssignment $assignment,
        string $event,
        string $title,
        string $messagePrefix
    ): void {
        $assignment->loadMissing([
            'operator.user',
            'productionLine',
            'shift',
        ]);

        $recipient =
            $assignment->operator?->user;

        if ($recipient === null) {
            return;
        }

        $lineName =
            $assignment->productionLine?->name
            ?? 'Unknown production line';

        $shiftName =
            $assignment->shift?->name
            ?? 'Unknown shift';

        $stateHash = hash(
            'sha256',
            implode('|', [
                $assignment->production_line_id,
                $assignment->shift_id,
                $assignment->starts_on
                    ?->format('Y-m-d')
                    ?? '',
                $assignment->ends_on
                    ?->format('Y-m-d')
                    ?? '',
                $assignment->is_primary
                    ? 'primary'
                    : 'secondary',
                $assignment->is_active
                    ? 'active'
                    : 'inactive',
            ])
        );

        $notification =
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Information,
                category:
                    'operator-assignment',
                title:
                    $title,
                message:
                    "{$messagePrefix}: {$lineName}, {$shiftName}.",
                actionUrl:
                    $this->links->route(
                        'dashboard'
                    ),
                actionLabel:
                    'Open dashboard',
                fingerprint:
                    'operator-assignment:'
                    .$assignment->getKey()
                    .':'
                    .$event
                    .':'
                    .$stateHash,
                metadata: [
                    'operator_assignment_id' =>
                        (int) $assignment->getKey(),
                    'production_line_id' =>
                        (int) $assignment
                            ->production_line_id,
                    'shift_id' =>
                        (int) $assignment->shift_id,
                ]
            );

        $this->delivery->send(
            $recipient,
            $notification
        );
    }
}
