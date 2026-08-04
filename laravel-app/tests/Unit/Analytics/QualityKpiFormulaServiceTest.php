<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\QualityKpiFormulaService;
use PHPUnit\Framework\TestCase;

class QualityKpiFormulaServiceTest extends TestCase
{
    public function test_percentage_and_zero_denominator_behavior(): void
    {
        $service = new QualityKpiFormulaService();

        $this->assertSame(8.0, $service->percentage(12, 150));
        $this->assertSame(50.0, $service->per100(1, 2));
        $this->assertNull($service->percentage(10, 0));
        $this->assertSame('12.346', $service->decimalString('12.3456'));
    }
}
