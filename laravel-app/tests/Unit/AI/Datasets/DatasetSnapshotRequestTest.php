<?php

namespace Tests\Unit\AI\Datasets;

use App\DTOs\AI\Datasets\DatasetSnapshotRequest;
use App\Enums\AI\DatasetType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DatasetSnapshotRequestTest extends
    TestCase
{
    public function test_it_normalizes_dataset_order(): void
    {
        $request = new DatasetSnapshotRequest(
            startDate:
                CarbonImmutable::parse(
                    '2026-08-01',
                    'Africa/Casablanca'
                ),
            endDate:
                CarbonImmutable::parse(
                    '2026-08-02',
                    'Africa/Casablanca'
                ),
            timezone:
                'Africa/Casablanca',
            datasets: [
                DatasetType::Nonconformities,
                DatasetType::ProductionRecords,
                DatasetType::Nonconformities,
            ]
        );

        $this->assertSame(
            [
                DatasetType::ProductionRecords,
                DatasetType::Nonconformities,
            ],
            $request->datasets
        );

        $this->assertSame(
            '2026-07-31T23:00:00+00:00',
            $request
                ->utcStart()
                ->toIso8601String()
        );
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new DatasetSnapshotRequest(
            startDate:
                CarbonImmutable::parse(
                    '2026-08-02'
                ),
            endDate:
                CarbonImmutable::parse(
                    '2026-08-01'
                ),
            timezone:
                'UTC',
            datasets: [
                DatasetType::ProductionRecords,
            ]
        );
    }

    public function test_dataset_option_parser_is_strict(): void
    {
        $this->assertSame(
            DatasetType::cases(),
            DatasetType::parseList('all')
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        DatasetType::parseList(
            'production_records,unknown'
        );
    }
}
