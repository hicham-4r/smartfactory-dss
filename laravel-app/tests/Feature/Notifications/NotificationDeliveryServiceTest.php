<?php

namespace Tests\Feature\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use App\Models\User;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Notifications\NotificationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_fingerprint_is_delivered_only_once_per_recipient(): void
    {
        $user = User::factory()->create([
            'is_active' =>
                true,
            'must_change_password' =>
                false,
        ]);

        $notification =
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Warning,
                category:
                    'test',
                title:
                    'Duplicate prevention',
                message:
                    'The same alert must not be inserted twice.',
                actionUrl:
                    '/dashboard',
                actionLabel:
                    'Open dashboard',
                fingerprint:
                    'same-condition'
            );

        $service = app(
            NotificationDeliveryService::class
        );

        $this->assertTrue(
            $service->send(
                $user,
                $notification
            )
        );

        $this->assertFalse(
            $service->send(
                $user,
                $notification
            )
        );

        $this->assertSame(
            1,
            $user
                ->notifications()
                ->count()
        );
    }

    public function test_inactive_user_does_not_receive_notification(): void
    {
        $user = User::factory()->create([
            'is_active' =>
                false,
        ]);

        $created = app(
            NotificationDeliveryService::class
        )->send(
            $user,
            new SmartFactoryAlertNotification(
                severity:
                    NotificationSeverity::Information,
                category:
                    'test',
                title:
                    'Inactive account',
                message:
                    'This message must not be stored.',
                actionUrl:
                    '/dashboard',
                actionLabel:
                    'Open dashboard',
                fingerprint:
                    'inactive-account'
            )
        );

        $this->assertFalse(
            $created
        );

        $this->assertDatabaseCount(
            'notifications',
            0
        );
    }
}
