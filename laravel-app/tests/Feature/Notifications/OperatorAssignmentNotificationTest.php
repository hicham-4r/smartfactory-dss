<?php

namespace Tests\Feature\Notifications;

use App\Enums\RoleName;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperatorAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_linked_operator_receives_assignment_created_notification(): void
    {
        $user = User::factory()->create([
            'is_active' =>
                true,
            'must_change_password' =>
                false,
            'password_changed_at' =>
                now(),
        ]);

        $user->assignRole(
            RoleName::Operator->value
        );

        $operator =
            Operator::query()
                ->firstOrFail();

        $operator->forceFill([
            'user_id' =>
                $user->getKey(),
        ])->saveQuietly();

        $line =
            ProductionLine::query()
                ->firstOrFail();

        $shift =
            Shift::query()
                ->firstOrFail();

        $assignment =
            new OperatorAssignment();

        $assignment->forceFill([
            'operator_id' =>
                $operator->getKey(),
            'production_line_id' =>
                $line->getKey(),
            'shift_id' =>
                $shift->getKey(),
            'assigned_by' =>
                null,
            'starts_on' =>
                now()->toDateString(),
            'ends_on' =>
                null,
            'is_primary' =>
                true,
            'is_active' =>
                true,
            'source_system' =>
                'manual_dss',
        ])->save();

        $notification =
            $user
                ->notifications()
                ->firstOrFail();

        $this->assertSame(
            'Production assignment created',
            $notification->data['title']
        );

        $this->assertSame(
            $assignment->getKey(),
            $notification->data[
                'metadata'
            ][
                'operator_assignment_id'
            ]
        );
    }
}
