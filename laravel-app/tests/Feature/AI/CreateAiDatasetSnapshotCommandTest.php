<?php

namespace Tests\Feature\AI;

use App\Contracts\AI\Datasets\DatasetSnapshotRepositoryInterface;
use App\DTOs\AI\Datasets\DatasetSnapshotRequest;
use App\Enums\AI\DatasetType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

final class CreateAiDatasetSnapshotCommandTest extends
    TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root =
            sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'smartfactory-dataset-command-'
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

        app()->instance(
            DatasetSnapshotRepositoryInterface::class,
            new class implements
                DatasetSnapshotRepositoryInterface {
                public function rows(
                    DatasetType $dataset,
                    DatasetSnapshotRequest $request
                ): LazyCollection {
                    unset(
                        $dataset,
                        $request
                    );

                    return LazyCollection::make(
                        static function (): iterable {
                            yield [
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
                    );
                }
            }
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

    public function test_command_creates_snapshot_receipt(): void
    {
        $this->artisan(
            'ai:dataset:snapshot',
            [
                '--start' =>
                    '2026-08-01',
                '--end' =>
                    '2026-08-02',
                '--timezone' =>
                    'Africa/Casablanca',
                '--datasets' =>
                    'production_records',
            ]
        )
            ->expectsOutputToContain(
                'The simulated-prototype dataset snapshot was created.'
            )
            ->assertSuccessful();

        $this->assertFileExists(
            $this->root
            .DIRECTORY_SEPARATOR
            .'LATEST'
        );
    }

    public function test_command_rejects_unknown_dataset(): void
    {
        $this->artisan(
            'ai:dataset:snapshot',
            [
                '--start' =>
                    '2026-08-01',
                '--end' =>
                    '2026-08-02',
                '--datasets' =>
                    'unknown',
            ]
        )
            ->expectsOutputToContain(
                'Unknown dataset type'
            )
            ->assertExitCode(
                2
            );
    }
}
