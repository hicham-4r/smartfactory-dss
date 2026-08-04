<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\KpiFormulaService;
use Tests\TestCase;

class KpiFormulaServiceTest extends TestCase
{
    public function test_percentage_returns_null_for_zero_denominator(): void
    {
        $formulas = new KpiFormulaService();

        $this->assertNull(
            $formulas->percentage(1000, 0)
        );
    }

    public function test_percentage_is_rounded_to_two_decimals(): void
    {
        $formulas = new KpiFormulaService();

        $this->assertSame(
            33.33,
            $formulas->percentage(1, 3)
        );
    }

    public function test_quantity_rate_is_calculated_per_hour(): void
    {
        $formulas = new KpiFormulaService();

        $this->assertSame(
            '120.000',
            $formulas->quantityPerHour(
                $formulas->toMilliUnits('60.000'),
                30
            )
        );
    }

    public function test_quantity_rate_is_not_applicable_without_runtime(): void
    {
        $formulas = new KpiFormulaService();

        $this->assertNull(
            $formulas->quantityPerHour(
                $formulas->toMilliUnits('60.000'),
                0
            )
        );
    }

    public function test_observed_utilization_uses_runtime_plus_downtime(): void
    {
        $formulas = new KpiFormulaService();

        $this->assertSame(
            87.5,
            $formulas->observedUtilization(
                420,
                60
            )
        );
    }
}
