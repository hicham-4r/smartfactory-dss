<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\ErpSyncRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ErpMonitoringDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-08-01 02:00:00'
            )
        );

        config()->set(
            'erp-monitoring.source_system',
            'simulated_sage'
        );

        config()->set(
            'erp-monitoring.stale_after_minutes',
            45
        );

        config()->set(
            'erp-monitoring.window_hours',
            24
        );

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this
            ->get(
                route(
                    'admin.erp-monitoring.index'
                )
            )
            ->assertRedirect(
                route('login')
            );
    }

    public function test_operator_cannot_open_erp_monitoring(): void
    {
        $operator = $this->userWithRole(
            RoleName::Operator
        );

        $this
            ->actingAs($operator)
            ->get(
                route(
                    'admin.erp-monitoring.index'
                )
            )
            ->assertForbidden();
    }

    public function test_administrator_can_open_read_only_monitoring_dashboard(): void
    {
        $admin = $this->administrator();

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.erp-monitoring.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'ERP synchronization monitoring'
            )
            ->assertSee(
                'Resource freshness'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'private'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'max-age=0'
            );
    }

    public function test_run_history_can_be_filtered_by_status(): void
    {
        $admin = $this->administrator();

        $completed = $this->createSyncRun(
            status:
                'completed',

            trigger:
                'manual'
        );

        $failed = $this->createSyncRun(
            status:
                'failed',

            trigger:
                'manual'
        );

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.erp-monitoring.index',
                    [
                        'status' =>
                            'failed',

                        'trigger' =>
                            'all',

                        'source_system' =>
                            'simulated_sage',

                        'per_page' =>
                            10,
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $failed->run_uuid
            )
            ->assertDontSee(
                $completed->run_uuid
            );
    }

    public function test_run_detail_never_displays_safe_context_or_secrets(): void
    {
        $admin = $this->administrator();

        $run = $this->createSyncRun(
            status:
                'completed_with_errors',

            trigger:
                'manual'
        );

        $resource = $run
            ->resources()
            ->create([
                'resource' =>
                    'products',

                'status' =>
                    'failed',

                'pages_processed' =>
                    1,

                'records_fetched' =>
                    1,

                'records_mapped' =>
                    0,

                'records_created' =>
                    0,

                'records_updated' =>
                    0,

                'records_skipped' =>
                    0,

                'records_failed' =>
                    1,

                'error_code' =>
                    'ERP_MAPPING_EXCEPTION',

                'error_message' =>
                    'A sanitized mapping error.',

                'started_at' =>
                    now()->subMinutes(2),

                'finished_at' =>
                    now()->subMinute(),
            ]);

        $run->failures()->create([
            'erp_sync_run_resource_id' =>
                $resource->getKey(),

            'resource' =>
                'products',

            'stage' =>
                'mapping',

            'external_id' =>
                'PRODUCT-001',

            'page' =>
                1,

            'error_code' =>
                'ERP_MAPPING_EXCEPTION',

            'error_message' =>
                'A sanitized mapping error.',

            'retryable' =>
                false,

            'safe_context' => [
                'authorization' =>
                    'Bearer TOP-SECRET-TOKEN',

                'payload' => [
                    'secret' =>
                        'HIDDEN-PAYLOAD',
                ],
            ],

            'occurred_at' =>
                now()->subMinute(),
        ]);

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.erp-monitoring.runs.show',
                    $run
                )
            )
            ->assertOk()
            ->assertSee(
                'A sanitized mapping error.'
            )
            ->assertDontSee(
                'TOP-SECRET-TOKEN'
            )
            ->assertDontSee(
                'HIDDEN-PAYLOAD'
            )
            ->assertDontSee(
                'safe_context'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            );
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $admin = $this->administrator();

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.erp-monitoring.index',
                    [
                        'status' =>
                            'not-a-real-status',
                    ]
                )
            )
            ->assertRedirect()
            ->assertSessionHasErrors([
                'status',
            ]);
    }

    public function test_monitoring_routes_are_read_only(): void
    {
        $admin = $this->administrator();

        $this
            ->actingAs($admin)
            ->post(
                route(
                    'admin.erp-monitoring.index'
                )
            )
            ->assertStatus(405);
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

    private function createSyncRun(
        string $status,
        string $trigger
    ): ErpSyncRun {
        return ErpSyncRun::query()
            ->create([
                'run_uuid' =>
                    (string) Str::uuid(),

                'source_system' =>
                    'simulated_sage',

                'trigger' =>
                    $trigger,

                'status' =>
                    $status,

                'requested_resources' => [
                    'products',
                ],

                'pages_processed' =>
                    1,

                'records_fetched' =>
                    1,

                'records_mapped' =>
                    $status === 'failed'
                        ? 0
                        : 1,

                'records_created' =>
                    $status === 'completed'
                        ? 1
                        : 0,

                'records_updated' =>
                    0,

                'records_skipped' =>
                    0,

                'records_failed' =>
                    $status === 'completed'
                        ? 0
                        : 1,

                'started_at' =>
                    now()->subMinutes(2),

                'finished_at' =>
                    now()->subMinute(),
            ]);
    }
}
