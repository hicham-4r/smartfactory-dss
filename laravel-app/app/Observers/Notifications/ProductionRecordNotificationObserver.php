<?php

namespace App\Observers\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use App\Enums\PermissionName;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationLinkFactory;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProductionRecordNotificationObserver
{
    public function __construct(
        private readonly NotificationDeliveryService $delivery,
        private readonly NotificationRecipientResolver $recipients,
        private readonly NotificationLinkFactory $links,
    ) {
    }

    public function updated(
        ProductionRecord $record
    ): void {
        if (
            $record->wasChanged('status')
            && $record->status
                === ProductionRecordStatus::Submitted
        ) {
            $this->notifySubmittedRecord(
                $record
            );
        }

        if (
            $record->wasChanged(
                'validation_status'
            )
            && $record->validation_status
                === ProductionValidationStatus::Rejected
        ) {
            $this->notifyRejectedRecord(
                $record
            );
        }
    }

    private function notifySubmittedRecord(
        ProductionRecord $record
    ): void {
        $record->loadMissing([
            'productionLine',
            'operator',
        ]);

        $lineName =
            $record->productionLine?->name
            ?? 'Unknown production line';

        $notification =
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Warning,
                category:
                    'production-record',
                title:
                    'Production record awaiting validation',
                message:
                    "Record {$record->record_number} from {$lineName} was submitted and requires a supervisor decision.",
                actionUrl:
                    $this->links->route(
                        'production.supervisor.records.show',
                        $record
                    ),
                actionLabel:
                    'Review record',
                fingerprint:
                    'production-record-submitted:'
                    .$record->getKey()
                    .':version:'
                    .$record->lock_version,
                metadata: [
                    'production_record_id' =>
                        (int) $record->getKey(),
                    'production_line_id' =>
                        (int) $record
                            ->production_line_id,
                    'operator_id' =>
                        $record->operator_id,
                    'lock_version' =>
                        (int) $record->lock_version,
                ]
            );

        $this->sendManySafely(
            recipients:
                $this->recipients
                    ->usersWithPermission(
                        PermissionName
                            ::ValidateProductionRecords
                    ),
            notification:
                $notification,
            record:
                $record,
            recipientGroup:
                'production-record-validator'
        );
    }

    private function notifyRejectedRecord(
        ProductionRecord $record
    ): void {
        $record->loadMissing([
            'operator.user',
            'productionLine',
        ]);

        $operatorUser =
            $record->operator?->user;

        if ($operatorUser === null) {
            return;
        }

        $lineName =
            $record->productionLine?->name
            ?? 'the assigned production line';

        $notification =
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Warning,
                category:
                    'production-record',
                title:
                    'Production record rejected',
                message:
                    "Record {$record->record_number} for {$lineName} was rejected and returned for correction.",
                actionUrl:
                    $this->links->route(
                        'production.operator.records.show',
                        $record
                    ),
                actionLabel:
                    'Correct record',
                fingerprint:
                    'production-record-rejected:'
                    .$record->getKey()
                    .':version:'
                    .$record->lock_version,
                metadata: [
                    'production_record_id' =>
                        (int) $record->getKey(),
                    'production_line_id' =>
                        (int) $record
                            ->production_line_id,
                    'lock_version' =>
                        (int) $record->lock_version,
                ]
            );

        $this->sendOneSafely(
            recipient:
                $operatorUser,
            notification:
                $notification,
            record:
                $record,
            recipientGroup:
                'reporting-operator'
        );
    }

    /**
     * @param iterable<User> $recipients
     */
    private function sendManySafely(
        iterable $recipients,
        SmartFactoryAlertNotification $notification,
        ProductionRecord $record,
        string $recipientGroup
    ): void {
        try {
            $this->delivery->sendToMany(
                $recipients,
                $notification
            );
        } catch (Throwable $exception) {
            $this->logDeliveryFailure(
                record:
                    $record,
                recipientGroup:
                    $recipientGroup,
                exception:
                    $exception
            );
        }
    }

    private function sendOneSafely(
        User $recipient,
        SmartFactoryAlertNotification $notification,
        ProductionRecord $record,
        string $recipientGroup
    ): void {
        try {
            $this->delivery->send(
                $recipient,
                $notification
            );
        } catch (Throwable $exception) {
            $this->logDeliveryFailure(
                record:
                    $record,
                recipientGroup:
                    $recipientGroup,
                exception:
                    $exception
            );
        }
    }

    private function logDeliveryFailure(
        ProductionRecord $record,
        string $recipientGroup,
        Throwable $exception
    ): void {
        Log::warning(
            'A production-record notification failed safely.',
            [
                'production_record_id' =>
                    (int) $record->getKey(),

                'recipient_group' =>
                    $recipientGroup,

                'exception_class' =>
                    $exception::class,
            ]
        );
    }
}