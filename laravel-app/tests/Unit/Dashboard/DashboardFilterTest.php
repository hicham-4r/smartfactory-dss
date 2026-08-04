<?php

namespace Tests\Unit\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DashboardFilterTest extends TestCase
{
    public function test_filter_serializes_shared_period(): void
    {
        $filter = new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01',
                'Africa/Casablanca'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-15',
                'Africa/Casablanca'
            ),
            timezone: 'Africa/Casablanca',
        );

        $this->assertSame(
            [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'timezone' => 'Africa/Casablanca',
            ],
            $filter->toQuery()
        );
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-15'
            ),
            timezone: 'Unsupported/Timezone',
        );
    }

    public function test_excessive_range_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-01-01'
            ),
            endDate: CarbonImmutable::parse(
                '2026-02-15'
            ),
            timezone: 'UTC',
            maximumRangeDays: 31,
        );
    }
}
