<?php

namespace Tests\Feature\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\SmartFactoryAlertNotification;
use App\Services\Notifications\NotificationDeliveryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationCenterHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_authenticated_user_can_view_only_their_notifications(): void
    {
        $user = $this->user(
            RoleName::ProductionSupervisor
        );

        $otherUser = $this->user(
            RoleName::ProductionSupervisor
        );

        $this->send(
            $user,
            title: 'Visible notification',
            fingerprint: 'visible'
        );

        $this->send(
            $otherUser,
            title: 'Private notification',
            fingerprint: 'private'
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'notifications.index'
                )
            )
            ->assertOk()
            ->assertSeeText(
                'Visible notification'
            )
            ->assertDontSeeText(
                'Private notification'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            );
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = $this->user(
            RoleName::ProductionSupervisor
        );

        $this->send(
            $user,
            title: 'Read test',
            fingerprint: 'read-test'
        );

        $notification =
            $user
                ->notifications()
                ->firstOrFail();

        $this
            ->actingAs($user)
            ->patch(
                route(
                    'notifications.read',
                    $notification->getKey()
                )
            )
            ->assertRedirect();

        $this->assertNotNull(
            $notification
                ->fresh()
                ->read_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $user->getKey(),
                'action' =>
                    'notifications.read',
            ]
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = $this->user(
            RoleName::ProductionSupervisor
        );

        $otherUser = $this->user(
            RoleName::ProductionSupervisor
        );

        $this->send(
            $otherUser,
            title: 'Not owned',
            fingerprint: 'not-owned'
        );

        $notification =
            $otherUser
                ->notifications()
                ->firstOrFail();

        $this
            ->actingAs($user)
            ->patch(
                route(
                    'notifications.read',
                    $notification->getKey()
                )
            )
            ->assertNotFound();

        $this->assertNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_mark_all_read_is_scoped_to_authenticated_user(): void
    {
        $user = $this->user(
            RoleName::ProductionSupervisor
        );

        $otherUser = $this->user(
            RoleName::ProductionSupervisor
        );

        $this->send(
            $user,
            title: 'First',
            fingerprint: 'first'
        );

        $this->send(
            $user,
            title: 'Second',
            fingerprint: 'second'
        );

        $this->send(
            $otherUser,
            title: 'Other',
            fingerprint: 'other'
        );

        $this
            ->actingAs($user)
            ->patch(
                route(
                    'notifications.read-all'
                )
            )
            ->assertRedirect();

        $this->assertSame(
            0,
            $user
                ->fresh()
                ->unreadNotifications()
                ->count()
        );

        $this->assertSame(
            1,
            $otherUser
                ->fresh()
                ->unreadNotifications()
                ->count()
        );
    }

    private function user(
        RoleName $role
    ): User {
        $user = User::factory()->create([
            'is_active' =>
                true,
            'must_change_password' =>
                false,
            'password_changed_at' =>
                now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }

    private function send(
        User $user,
        string $title,
        string $fingerprint
    ): void {
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
                    $title,
                message:
                    'Test notification message.',
                actionUrl:
                    '/dashboard',
                actionLabel:
                    'Open dashboard',
                fingerprint:
                    $fingerprint
            )
        );

        $this->assertTrue(
            $created
        );
    }
}
