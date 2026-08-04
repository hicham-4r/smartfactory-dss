<?php

namespace Tests\Feature\Alerts;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DeterministicAlertEvaluationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_job_alert_is_idempotent(): void
    {
        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $administrator =
            User::factory()->create([
                'is_active' =>
                    true,
                'must_change_password' =>
                    false,
                'password_changed_at' =>
                    now(),
            ]);

        $administrator->assignRole(
            RoleName::Administrator->value
        );

        DB::table(
            'failed_jobs'
        )->insert([
            'uuid' =>
                '11111111-1111-4111-8111-111111111111',
            'connection' =>
                'database',
            'queue' =>
                'default',
            'payload' =>
                '{}',
            'exception' =>
                'Sanitized test exception',
            'failed_at' =>
                now(),
        ]);

        $this
            ->artisan(
                'alerts:evaluate'
            )
            ->assertExitCode(0);

        $firstCount =
            $administrator
                ->fresh()
                ->notifications()
                ->count();

        $this->assertGreaterThanOrEqual(
            1,
            $firstCount
        );

        $failedJobNotification =
            $administrator
                ->fresh()
                ->notifications()
                ->get()
                ->first(
                    static fn (
                        $notification
                    ): bool =>
                        (
                            $notification
                                ->data['title']
                            ?? null
                        )
                        ===
                        'Failed queue jobs require review'
                );

        $this->assertNotNull(
            $failedJobNotification
        );

        $this
            ->artisan(
                'alerts:evaluate'
            )
            ->assertExitCode(0);

        $this->assertSame(
            $firstCount,
            $administrator
                ->fresh()
                ->notifications()
                ->count()
        );
    }
}
