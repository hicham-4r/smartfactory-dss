<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;

final class NotificationCenterController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $validated = validator(
            $request->query(),
            [
                'status' => [
                    'nullable',
                    Rule::in([
                        'all',
                        'unread',
                        'read',
                    ]),
                ],
                'severity' => [
                    'nullable',
                    Rule::in([
                        'all',
                        'information',
                        'warning',
                        'critical',
                    ]),
                ],
            ]
        )->validate();

        $status =
            $validated['status']
            ?? 'all';

        $severity =
            $validated['severity']
            ?? 'all';

        $query = $request
            ->user()
            ->notifications()
            ->where(
                function ($query): void {
                    $query
                        ->whereNull(
                            'expires_at'
                        )
                        ->orWhere(
                            'expires_at',
                            '>',
                            now()
                        );
                }
            )
            ->latest('created_at');

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($severity !== 'all') {
            $query->where(
                'severity',
                $severity
            );
        }

        $notifications =
            $query
                ->paginate(20)
                ->withQueryString();

        return response()
            ->view(
                'notifications.index',
                [
                    'notifications' =>
                        $notifications,
                    'statusFilter' =>
                        $status,
                    'severityFilter' =>
                        $severity,
                    'unreadCount' =>
                        $request
                            ->user()
                            ->unreadNotifications()
                            ->count(),
                ]
            )
            ->withHeaders(
                $this->privateNoStoreHeaders()
            );
    }

    public function markRead(
        Request $request,
        string $notification,
        AuditLogService $audit
    ): RedirectResponse {
        $databaseNotification =
            $this->notificationForUser(
                $request,
                $notification
            );

        if ($databaseNotification->read_at === null) {
            $databaseNotification->markAsRead();

            $audit->record(
                action:
                    'notifications.read',
                actor:
                    $request->user(),
                metadata: [
                    'notification_id' =>
                        $databaseNotification
                            ->getKey(),
                    'notification_type' =>
                        class_basename(
                            $databaseNotification
                                ->type
                        ),
                ],
                request: $request
            );
        }

        return back()->with(
            'status',
            'Notification marked as read.'
        );
    }

    public function markAllRead(
        Request $request,
        AuditLogService $audit
    ): RedirectResponse {
        $count = $request
            ->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($count > 0) {
            $audit->record(
                action:
                    'notifications.read-all',
                actor:
                    $request->user(),
                metadata: [
                    'notification_count' =>
                        $count,
                ],
                request: $request
            );
        }

        return back()->with(
            'status',
            "{$count} notification(s) marked as read."
        );
    }

    private function notificationForUser(
        Request $request,
        string $notification
    ): DatabaseNotification {
        return $request
            ->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function privateNoStoreHeaders(): array
    {
        return [
            'Cache-Control' =>
                'no-store, private, max-age=0',
            'Pragma' =>
                'no-cache',
            'Expires' =>
                '0',
        ];
    }
}
