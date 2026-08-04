<?php

namespace Tests\Feature\Reports;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductionReportHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-08-15 10:00:00',
                'Africa/Casablanca'
            )
        );

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $this->seed(
            ProductionWorkflowPermissionsSeeder::class
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(
            route('reports.index')
        )->assertRedirect(
            route('login')
        );
    }

    public function test_operator_cannot_open_company_reporting(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::Operator
                )
            )
            ->get(
                route('reports.index')
            )
            ->assertForbidden();
    }

    public function test_production_manager_can_preview_report(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route(
                    'reports.index',
                    $this->parameters(
                        'monthly'
                    )
                )
            )
            ->assertOk()
            ->assertSeeText(
                'Production reporting'
            )
            ->assertSeeText(
                'Monthly production report'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            );
    }

    public function test_supervisor_cannot_generate_executive_report(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionSupervisor
                )
            )
            ->get(
                route(
                    'reports.index',
                    $this->parameters(
                        'executive'
                    )
                )
            )
            ->assertForbidden();
    }

    public function test_csv_export_is_downloadable_and_audited(): void
    {
        $user = $this->userWithRole(
            RoleName::ProductionManager
        );

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'reports.production.export',
                    [
                        'format' => 'csv',
                        ...$this->parameters(
                            'production-line'
                        ),
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'text/csv; charset=UTF-8'
            )
            ->assertHeaderContains(
                'Content-Disposition',
                '.csv'
            )
            ->assertSee(
                'SmartFactory DSS production report',
                false
            );

        $this->assertTrue(
            AuditLog::query()
                ->where(
                    'actor_id',
                    $user->getKey()
                )
                ->where(
                    'action',
                    AuditAction
                        ::ProductionReportGenerated
                        ->value
                )
                ->exists()
        );
    }

    public function test_excel_export_is_a_valid_openxml_container(): void
    {
        $response = $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route(
                    'reports.production.export',
                    [
                        'format' => 'xlsx',
                        ...$this->parameters(
                            'product'
                        ),
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            )
            ->assertHeaderContains(
                'Content-Disposition',
                '.xlsx'
            );

        $this->assertStringStartsWith(
            "PK\x03\x04",
            $response->getContent()
        );
    }

    public function test_pdf_export_is_a_real_pdf_document(): void
    {
        $response = $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route(
                    'reports.production.export',
                    [
                        'format' => 'pdf',
                        ...$this->parameters(
                            'shift'
                        ),
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/pdf'
            )
            ->assertHeaderContains(
                'Content-Disposition',
                '.pdf'
            );

        $this->assertStringStartsWith(
            '%PDF-1.4',
            $response->getContent()
        );
    }

    /**
     * @return array<string, string>
     */
    private function parameters(
        string $reportType
    ): array {
        return [
            'report_type' => $reportType,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-15',
            'timezone' => 'Africa/Casablanca',
        ];
    }

    private function userWithRole(
        RoleName $role
    ): User {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }
}
