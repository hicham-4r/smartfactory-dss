<?php

namespace App\Observers\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use App\Enums\PermissionName;
use App\Models\ErpSyncRun;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationLinkFactory;
use App\Services\Notifications\NotificationRecipientResolver;

final class ErpSyncRunNotificationObserver
{
    public function __construct(
        private readonly NotificationDeliveryService $delivery,
        private readonly NotificationRecipientResolver $recipients,
        private readonly NotificationLinkFactory $links,
    ) {
    }

    public function updated(
        ErpSyncRun $run
    ): void {
        if (! $run->wasChanged('status')) {
            return;
        }

        $status = $run->status->value;

        if (! in_array(
            $status,
            [
                'failed',
                'completed_with_errors',
            ],
            true
        )) {
            return;
        }

        $failed =
            $status === 'failed';

        $notification =
            new SmartFactoryAlertNotification(
                severity: $failed
                    ? NotificationSeverity::Critical
                    : NotificationSeverity::Warning,
                category:
                    'erp-synchronization',
                title: $failed
                    ? 'ERP synchronization failed'
                    : 'ERP synchronization completed with errors',
                message:
                    "ERP run {$run->run_uuid} ended with status {$status}. Review the sanitized synchronization log.",
                actionUrl:
                    $this->links->route(
                        'admin.erp-monitoring.runs.show',
                        $run
                    ),
                actionLabel:
                    'Review synchronization',
                fingerprint:
                    'erp-sync-run:'
                    .$run->getKey()
                    .':'
                    .$status,
                metadata: [
                    'erp_sync_run_id' =>
                        (int) $run->getKey(),
                    'records_failed' =>
                        (int) $run->records_failed,
                    'status' =>
                        $status,
                ]
            );

        $this->delivery->sendToMany(
            $this->recipients
                ->usersWithPermission(
                    PermissionName
                        ::ViewSynchronizationLogs
                ),
            $notification
        );
    }
}
