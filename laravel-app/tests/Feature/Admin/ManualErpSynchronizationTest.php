<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Jobs\ERP\RunManualErpSyncJob;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ManualErpSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cache.default',
            'array'
        );

        config()->set(
            'erp-manual-sync.queue',
            'erp-sync'
        );

        config()->set(
            'erp-manual-sync.cooldown_seconds',
            300
        );

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this
            ->post(
                route(
                    'admin.erp-monitoring.synchronize'
                ),
                [
                    'per_page' => 100,
                    'max_pages' => 100,
                ]
            )
            ->assertRedirect(
                route('login')
            );
    }

    public function test_operator_cannot_request_manual_synchronization(): void
    {
        $operator = $this->userWithRole(
            RoleName::Operator
        );

        $this
            ->actingAs($operator)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->post(
                route(
                    'admin.erp-monitoring.synchronize'
                ),
                [
                    'per_page' => 100,
                    'max_pages' => 100,
                ]
            )
            ->assertForbidden();
    }

    public function test_password_confirmation_is_required(): void
    {
        $admin = $this->administrator();

        $this
            ->actingAs($admin)
            ->post(
                route(
                    'admin.erp-monitoring.synchronize'
                ),
                [
                    'per_page' => 100,
                    'max_pages' => 100,
                ]
            )
            ->assertRedirect(
                route('password.confirm')
            );
    }

    public function test_administrator_can_queue_manual_incremental_synchronization(): void
    {
        Queue::fake();

        $admin = $this->administrator();

        RateLimiter::clear(
            'erp-manual-sync:user:'
            .$admin->getKey()
        );

        $this
            ->actingAs($admin)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->post(
                route(
                    'admin.erp-monitoring.synchronize'
                ),
                [
                    'per_page' => 100,
                    'max_pages' => 75,
                ]
            )
            ->assertRedirect(
                route(
                    'admin.erp-monitoring.index'
                )
            )
            ->assertSessionHas(
                'erp_sync_status'
            );

        Queue::assertPushedOn(
            'erp-sync',
            RunManualErpSyncJob::class,

            function (
                RunManualErpSyncJob $job
            ) use ($admin): bool {
                return $job
                    ->initiatedByUserId
                    === (int) $admin
                        ->getKey()
                    && $job->perPage === 100
                    && $job
                        ->maximumPagesPerResource
                        === 75
                    && $job->requestId !== '';
            }
        );
    }

    public function test_invalid_synchronization_limits_are_rejected(): void
    {
        Queue::fake();

        $admin = $this->administrator();

        $this
            ->actingAs($admin)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->post(
                route(
                    'admin.erp-monitoring.synchronize'
                ),
                [
                    'per_page' => 999,
                    'max_pages' => 0,
                ]
            )
            ->assertSessionHasErrors([
                'per_page',
                'max_pages',
            ]);

        Queue::assertNothingPushed();
    }

    public function test_repeated_manual_request_is_rate_limited(): void
    {
        Queue::fake();

        $admin = $this->administrator();

        $key =
            'erp-manual-sync:user:'
            .$admin->getKey();

        RateLimiter::clear(
            $key
        );

        $session = [
            'auth.password_confirmed_at' =>
                time(),
        ];

        $payload = [
            'per_page' => 100,
            'max_pages' => 100,
        ];

        $this
            ->actingAs($admin)
            ->withSession($session)
            ->post(
                route(
                    'admin.erp-monitoring.synchronize'
                ),
                $payload
            )
            ->assertRedirect();

        $this
            ->actingAs($admin)
            ->withSession($session)
            ->post(
                route(
                    'admin.erp-monitoring.synchronize'
                ),
                $payload
            )
            ->assertSessionHasErrors([
                'manual_sync',
            ]);

        Queue::assertPushed(
            RunManualErpSyncJob::class,
            1
        );
    }

    public function test_manual_synchronization_endpoint_is_post_only(): void
    {
        $admin = $this->administrator();

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.erp-monitoring.synchronize'
                )
            )
            ->assertMethodNotAllowed();
    }

    private function administrator(): User
    {
        $admin = $this->userWithRole(
            RoleName::Administrator
        );

        return $this
            ->enableConfirmedTwoFactorAuthentication(
                $admin
            );
    }

    private function userWithRole(
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
}
