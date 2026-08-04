<?php

namespace Tests\Unit\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProductionSupervisorDashboardFilterTest extends TestCase
{
    public function test_optional_supervisor_filters_are_serialized_without_changing_shared_defaults(): void
    {
        $filter = new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01',
                'UTC'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-15',
                'UTC'
            ),
            timezone: 'UTC',
            productionLineId: 4,
            productId: 8,
            shiftId: 2,
            status: 'in_progress',
        );

        $this->assertSame(
            [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'timezone' => 'UTC',
                'production_line_id' => 4,
                'product_id' => 8,
                'shift_id' => 2,
                'status' => 'in_progress',
            ],
            $filter->toQuery()
        );

        $this->assertSame(
            [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'timezone' => 'UTC',
                'production_line_id' => 4,
                'product_id' => 8,
            ],
            $filter->toQualityQuery()
        );
    }

    public function test_non_execution_status_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01',
                'UTC'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-15',
                'UTC'
            ),
            timezone: 'UTC',
            status: 'planned',
        );
    }
}
