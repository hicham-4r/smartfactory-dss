<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\MaintenanceKpiFormulaService;
use Tests\TestCase;

class MaintenanceKpiFormulaServiceTest extends TestCase
{
    private MaintenanceKpiFormulaService $formula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formula =
            new MaintenanceKpiFormulaService();
    }

    public function test_it_protects_zero_denominators(): void
    {
        $this->assertNull(
            $this->formula->percentage(10, 0)
        );

        $this->assertNull(
            $this->formula->averageMinutes(
                120,
                0
            )
        );

        $this->assertNull(
            $this->formula->mtbfMinutes(
                600,
                0
            )
        );

        $this->assertNull(
            $this->formula
                ->failuresPer100RunningHours(
                    2,
                    0
                )
        );
    }

    public function test_it_calculates_maintenance_formulas(): void
    {
        $this->assertSame(
            80.0,
            $this->formula->percentage(
                480,
                600
            )
        );

        $this->assertSame(
            75.0,
            $this->formula->averageMinutes(
                150,
                2
            )
        );

        $this->assertSame(
            300.0,
            $this->formula->mtbfMinutes(
                600,
                2
            )
        );

        $this->assertSame(
            20.0,
            $this->formula
                ->failuresPer100RunningHours(
                    2,
                    600
                )
        );
    }
}
