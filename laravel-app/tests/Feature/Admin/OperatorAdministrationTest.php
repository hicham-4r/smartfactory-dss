<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperatorAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_operator_cannot_open_operator_administration(): void
    {
        $operatorAccount =
            $this->userWithRole(
                RoleName::Operator
            );

        $this
            ->actingAs(
                $operatorAccount
            )
            ->get(
                route(
                    'admin.operator-administration.index'
                )
            )
            ->assertForbidden();
    }

    public function test_administrator_can_link_account_and_create_assignment(): void
    {
        $admin =
            $this->administrator();

        $account =
            $this->userWithRole(
                RoleName::Operator
            );

        $operator =
            $this->operator();

        $line =
            $this->productionLine();

        $shift =
            $this->shift();

        $this
            ->actingAs($admin)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->post(
                route(
                    'admin.operator-administration.account.link',
                    $operator
                ),
                [
                    'user_id' =>
                        $account->getKey(),
                ]
            )
            ->assertRedirect(
                route(
                    'admin.operator-administration.show',
                    $operator
                )
            );

        $this->assertDatabaseHas(
            'operators',
            [
                'id' =>
                    $operator->getKey(),

                'user_id' =>
                    $account->getKey(),
            ]
        );

        $this
            ->actingAs($admin)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->post(
                route(
                    'admin.operator-administration.assignments.store',
                    $operator
                ),
                [
                    'production_line_id' =>
                        $line->getKey(),

                    'shift_id' =>
                        $shift->getKey(),

                    'starts_on' =>
                        now()->toDateString(),

                    'ends_on' =>
                        null,

                    'is_primary' =>
                        '1',
                ]
            )
            ->assertRedirect(
                route(
                    'admin.operator-administration.show',
                    $operator
                )
            );

        $this->assertDatabaseHas(
            'operator_assignments',
            [
                'operator_id' =>
                    $operator->getKey(),

                'production_line_id' =>
                    $line->getKey(),

                'shift_id' =>
                    $shift->getKey(),

                'is_primary' =>
                    true,

                'is_active' =>
                    true,

                'source_system' =>
                    'manual_dss',
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction
                        ::OperatorAccountLinked
                        ->value,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction
                        ::OperatorAssignmentCreated
                        ->value,
            ]
        );
    }

    public function test_duplicate_overlapping_assignment_is_rejected(): void
    {
        $admin =
            $this->administrator();

        $operator =
            $this->operator();

        $line =
            $this->productionLine();

        $shift =
            $this->shift();

        $this->manualAssignment(
            operator:
                $operator,

            line:
                $line,

            shift:
                $shift,

            actor:
                $admin
        );

        $this
            ->actingAs($admin)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->from(
                route(
                    'admin.operator-administration.show',
                    $operator
                )
            )
            ->post(
                route(
                    'admin.operator-administration.assignments.store',
                    $operator
                ),
                [
                    'production_line_id' =>
                        $line->getKey(),

                    'shift_id' =>
                        $shift->getKey(),

                    'starts_on' =>
                        now()->toDateString(),

                    'ends_on' =>
                        null,

                    'is_primary' =>
                        '0',
                ]
            )
            ->assertRedirect(
                route(
                    'admin.operator-administration.show',
                    $operator
                )
            )
            ->assertSessionHasErrors([
                'assignment',
            ]);

        $this->assertSame(
            1,
            OperatorAssignment::query()->count()
        );
    }

    public function test_erp_assignment_cannot_be_changed_by_manual_interface(): void
    {
        $admin =
            $this->administrator();

        $operator =
            $this->operator();

        $line =
            $this->productionLine();

        $shift =
            $this->shift();

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
                $admin->getKey(),

            'starts_on' =>
                now()->toDateString(),

            'ends_on' =>
                null,

            'is_primary' =>
                true,

            'is_active' =>
                true,

            'source_system' =>
                'simulated_sage',

            'external_id' =>
                'ERP-ASG-001',
        ])->save();

        $this
            ->actingAs($admin)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->from(
                route(
                    'admin.operator-administration.show',
                    $operator
                )
            )
            ->patch(
                route(
                    'admin.operator-administration.assignments.end',
                    [
                        $operator,
                        $assignment,
                    ]
                ),
                [
                    'ends_on' =>
                        now()->toDateString(),
                ]
            )
            ->assertRedirect(
                route(
                    'admin.operator-administration.show',
                    $operator
                )
            )
            ->assertSessionHasErrors([
                'assignment',
            ]);

        $this->assertTrue(
            $assignment
                ->fresh()
                ->is_active
        );
    }

    private function administrator(): User
    {
        return $this
            ->enableConfirmedTwoFactorAuthentication(
                $this->userWithRole(
                    RoleName::Administrator
                )
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

    private function operator(): Operator
    {
        $operator = new Operator();

        $operator->forceFill([
            'employee_code' =>
                'OP-TEST-001',

            'first_name' =>
                'Test',

            'last_name' =>
                'Operator',

            'is_active' =>
                true,

            'source_system' =>
                'simulated_sage',

            'external_id' =>
                'ERP-OP-001',
        ])->save();

        return $operator;
    }

    private function productionLine(): ProductionLine
    {
        $line = new ProductionLine();

        $line->forceFill([
            'code' =>
                'LINE-TEST-01',

            'name' =>
                'Test Filling Line',

            'is_active' =>
                true,

            'source_system' =>
                'simulated_sage',

            'external_id' =>
                'ERP-LINE-001',
        ])->save();

        return $line;
    }

    private function shift(): Shift
    {
        $shift = new Shift();

        $shift->forceFill([
            'code' =>
                'SHIFT-TEST-A',

            'name' =>
                'Test Morning Shift',

            'starts_at' =>
                '06:00:00',

            'ends_at' =>
                '14:00:00',

            'crosses_midnight' =>
                false,

            'is_active' =>
                true,

            'source_system' =>
                'simulated_sage',

            'external_id' =>
                'ERP-SHIFT-001',
        ])->save();

        return $shift;
    }

    private function manualAssignment(
        Operator $operator,
        ProductionLine $line,
        Shift $shift,
        User $actor
    ): OperatorAssignment {
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
                $actor->getKey(),

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

        return $assignment;
    }
}
