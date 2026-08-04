<?php

namespace Tests\Unit\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MaintenanceManagerDashboardFilterTest extends TestCase
{
    public function test_maintenance_query_preserves_supported_filters(): void
    {
        $filter = new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01',
                'UTC'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-10',
                'UTC'
            ),
            timezone: 'UTC',
            productionLineId: 4,
            machineId: 12,
            maintenanceType: 'corrective',
            downtimeCategory: 'unplanned',
        );

        $this->assertSame(
            [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-10',
                'timezone' => 'UTC',
                'production_line_id' => 4,
                'machine_id' => 12,
                'maintenance_type' => 'corrective',
                'downtime_category' => 'unplanned',
            ],
            $filter->toMaintenanceQuery()
        );
    }

    public function test_invalid_maintenance_type_is_rejected(): void
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
                '2026-08-10',
                'UTC'
            ),
            timezone: 'UTC',
            maintenanceType: 'unsupported',
        );
    }

    public function test_invalid_downtime_category_is_rejected(): void
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
                '2026-08-10',
                'UTC'
            ),
            timezone: 'UTC',
            downtimeCategory: 'unknown',
        );
    }
}
