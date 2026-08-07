<?php

namespace Tests\Unit\AI\Datasets;

use App\Contracts\AI\Datasets\DatasetSnapshotRepositoryInterface;
use App\DTOs\AI\Datasets\DatasetSnapshotRequest;
use App\Enums\AI\DatasetType;
use App\Services\AI\Datasets\DatasetRootGuard;
use App\Services\AI\Datasets\DatasetSchemaRegistry;
use App\Services\AI\Datasets\DatasetSnapshotService;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

final class DatasetSnapshotServiceTest extends
    TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root =
            sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'smartfactory-dataset-test-'
            .bin2hex(
                random_bytes(8)
            );

        config()->set(
            'ai-datasets.root',
            $this->root
        );

        config()->set(
            'ai-datasets.audit_enabled',
            false
        );

        config()->set(
            'ai-datasets.maximum_rows_per_file',
            100
        );

        config()->set(
            'ai-datasets.maximum_bytes_per_file',
            1048576
        );
    }

    protected function tearDown(): void
    {
        if (
            isset($this->root)
            && File::isDirectory(
                $this->root
            )
        ) {
            File::deleteDirectory(
                $this->root
            );
        }

        parent::tearDown();
    }

    public function test_it_creates_atomic_checksummed_snapshot(): void
    {
        $service =
            $this->service(
                [
                    $this->productionRow(),
                ]
            );

        $result = $service->create(
            $this->request()
        );

        $this->assertDirectoryExists(
            $result->snapshotDirectory
        );

        $this->assertFileExists(
            $result->manifestPath
        );

        $this->assertFileExists(
            $result->snapshotDirectory
            .DIRECTORY_SEPARATOR
            .'manifest.sha256'
        );

        $this->assertFileExists(
            $result->snapshotDirectory
            .DIRECTORY_SEPARATOR
            .'data'
            .DIRECTORY_SEPARATOR
            .'production_records.csv'
        );

        $manifest = json_decode(
            file_get_contents(
                $result->manifestPath
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            'simulated_prototype',
            $manifest[
                'data_classification'
            ]
        );

        $this->assertSame(
            1,
            $manifest['total_rows']
        );

        $this->assertSame(
            $result->snapshotId,
            trim(
                file_get_contents(
                    $this->root
                    .DIRECTORY_SEPARATOR
                    .'LATEST'
                )
            )
        );

        $dataset =
            $manifest['datasets'][0];

        $file =
            $result->snapshotDirectory
            .DIRECTORY_SEPARATOR
            .str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $dataset['file']
            );

        $this->assertSame(
            hash_file(
                'sha256',
                $file
            ),
            $dataset['sha256']
        );

        $contents = file_get_contents(
            $file
        );

        $this->assertStringNotContainsString(
            'operator',
            strtolower($contents)
        );

        $this->assertStringNotContainsString(
            'notes',
            strtolower($contents)
        );
    }

    public function test_csv_formula_prefixes_are_neutralized(): void
    {
        $row =
            $this->productionRow();

        $row['product_code'] =
            '=DANGEROUS()';

        $result = $this
            ->service([$row])
            ->create(
                $this->request()
            );

        $contents = file_get_contents(
            $result->snapshotDirectory
            .DIRECTORY_SEPARATOR
            .'data'
            .DIRECTORY_SEPARATOR
            .'production_records.csv'
        );

        $this->assertStringContainsString(
            "'=DANGEROUS()",
            $contents
        );
    }

    /**
     * @param list<array<string, int|string|null>> $rows
     */
    public function test_published_snapshot_permissions_allow_a_read_only_consumer(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped(
                'POSIX filesystem permissions are verified in the Linux runtime.'
            );
        }

        $result = $this
            ->service(
                [
                    $this->productionRow(),
                ]
            )
            ->create(
                $this->request()
            );

        $paths = [
            $this->root => '0755',
            $this->root
                .DIRECTORY_SEPARATOR
                .'snapshots' => '0755',
            $result->snapshotDirectory =>
                '0755',
            $result->snapshotDirectory
                .DIRECTORY_SEPARATOR
                .'data' => '0755',
            $this->root
                .DIRECTORY_SEPARATOR
                .'LATEST' => '0644',
            $result->manifestPath => '0644',
            $result->snapshotDirectory
                .DIRECTORY_SEPARATOR
                .'manifest.sha256' => '0644',
            $result->snapshotDirectory
                .DIRECTORY_SEPARATOR
                .'data'
                .DIRECTORY_SEPARATOR
                .'production_records.csv' => '0644',
        ];

        foreach (
            $paths
            as $path => $expectedMode
        ) {
            clearstatcache(
                true,
                $path
            );

            $permissions =
                fileperms(
                    $path
                );

            $this->assertIsInt(
                $permissions
            );

            $this->assertSame(
                $expectedMode,
                substr(
                    sprintf(
                        '%o',
                        $permissions
                    ),
                    -4
                ),
                $path
            );
        }
    }

    private function service(
        array $rows
    ): DatasetSnapshotService {
        $repository =
            new class(
                $rows
            ) implements
                DatasetSnapshotRepositoryInterface {
                /**
                 * @param list<array<string, int|string|null>> $rows
                 */
                public function __construct(
                    private readonly array $rows
                ) {
                }

                public function rows(
                    DatasetType $dataset,
                    DatasetSnapshotRequest $request
                ): LazyCollection {
                    unset(
                        $dataset,
                        $request
                    );

                    return LazyCollection::make(
                        fn (): iterable =>
                            yield from $this->rows
                    );
                }
            };

        return new DatasetSnapshotService(
            repository:
                $repository,
            schemas:
                new DatasetSchemaRegistry(),
            rootGuard:
                new DatasetRootGuard(),
            audit:
                app(
                    AuditLogService::class
                )
        );
    }

    private function request(): DatasetSnapshotRequest
    {
        return new DatasetSnapshotRequest(
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
                DatasetType::ProductionRecords,
            ]
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function productionRow(): array
    {
        return [
            'production_date' =>
                '2026-08-01',
            'started_at_utc' =>
                '2026-08-01T06:00:00Z',
            'ended_at_utc' =>
                '2026-08-01T14:00:00Z',
            'production_line_code' =>
                'LINE-01',
            'product_family_code' =>
                'VALENCIA-PREMIUM',
            'product_code' =>
                'ORANGE-1L',
            'shift_code' =>
                'SHIFT-A',
            'production_order_status' =>
                'completed',
            'production_order_priority' =>
                2,
            'record_status' =>
                'locked',
            'validation_status' =>
                'validated',
            'quantity_unit' =>
                'bottles',
            'target_quantity' =>
                '1000.000',
            'produced_quantity' =>
                '980.000',
            'good_quantity' =>
                '970.000',
            'rejected_quantity' =>
                '10.000',
            'runtime_minutes' =>
                420,
            'downtime_minutes' =>
                20,
            'is_validated' =>
                1,
            'import_status' =>
                'imported',
            'source_version' =>
                7,
            'source_updated_at_utc' =>
                '2026-08-01T15:00:00Z',
        ];
    }
}
