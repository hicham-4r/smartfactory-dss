<?php

namespace App\Services\AI\Datasets;

use App\Enums\AI\DatasetType;

final class DatasetSchemaRegistry
{
    public const CONTRACT_NAME =
        'smartfactory.ml.dataset.snapshot';

    public const MANIFEST_VERSION = 'v1';

    public const SCHEMA_VERSION = 'v1';

    public const SOURCE_APPLICATION =
        'smartfactory-dss-laravel';

    public const DATA_CLASSIFICATION =
        'simulated_prototype';

    /**
     * @return list<string>
     */
    public function columns(
        DatasetType $dataset
    ): array {
        return match ($dataset) {
            DatasetType::ProductionRecords => [
                'production_date',
                'started_at_utc',
                'ended_at_utc',
                'production_line_code',
                'product_family_code',
                'product_code',
                'shift_code',
                'production_order_status',
                'production_order_priority',
                'record_status',
                'validation_status',
                'quantity_unit',
                'target_quantity',
                'produced_quantity',
                'good_quantity',
                'rejected_quantity',
                'runtime_minutes',
                'downtime_minutes',
                'is_validated',
                'import_status',
                'source_version',
                'source_updated_at_utc',
            ],

            DatasetType::DowntimeEvents => [
                'started_at_utc',
                'ended_at_utc',
                'production_line_code',
                'machine_code',
                'machine_type',
                'shift_code',
                'severity',
                'category',
                'downtime_type',
                'duration_minutes',
                'is_resolved',
                'import_status',
                'source_version',
                'source_updated_at_utc',
            ],

            DatasetType::MachineStatusEvents => [
                'occurred_at_utc',
                'ended_at_utc',
                'production_line_code',
                'machine_code',
                'machine_type',
                'is_critical',
                'status',
                'duration_minutes',
                'import_status',
                'source_version',
                'source_updated_at_utc',
            ],

            DatasetType::MaintenanceHistory => [
                'scheduled_at_utc',
                'started_at_utc',
                'completed_at_utc',
                'production_line_code',
                'machine_code',
                'machine_type',
                'is_critical',
                'maintenance_type',
                'status',
                'downtime_minutes',
                'cost',
                'currency',
                'import_status',
                'source_version',
                'source_updated_at_utc',
            ],

            DatasetType::QualityInspections => [
                'inspected_at_utc',
                'production_line_code',
                'product_family_code',
                'product_code',
                'inspection_type',
                'result',
                'sample_size',
                'passed_quantity',
                'failed_quantity',
                'import_status',
                'source_version',
                'source_updated_at_utc',
            ],

            DatasetType::FinishedLots => [
                'produced_at_utc',
                'expiry_date',
                'released_at_utc',
                'production_line_code',
                'product_family_code',
                'product_code',
                'status',
                'quantity_unit',
                'produced_quantity',
                'released_quantity',
                'rejected_quantity',
                'import_status',
                'source_version',
                'source_updated_at_utc',
            ],

            DatasetType::Nonconformities => [
                'detected_at_utc',
                'corrected_at_utc',
                'production_line_code',
                'product_family_code',
                'product_code',
                'severity',
                'status',
                'category',
                'import_status',
                'source_version',
                'source_updated_at_utc',
            ],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        $schemas = [];

        foreach (DatasetType::cases() as $dataset) {
            $schemas[$dataset->value] =
                $this->columns($dataset);
        }

        return $schemas;
    }
}
