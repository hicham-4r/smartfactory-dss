<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpRecordMapperInterface;
use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\BatchErpData;
use App\DTOs\ERP\Mapped\ProductionRecordErpData;
use App\DTOs\ERP\Mapped\WorkOrderErpData;
use App\Enums\ERP\ErpPersistenceAction;
use App\Enums\ERP\ErpResource;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Services\ERP\Sync\ErpMappedEntityPersister;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatedSageProductionPayloadContractTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT_EXTERNAL_ID =
        '2a351008-6eda-4d03-9ff7-71ba70bb3334';

    private const LINE_EXTERNAL_ID =
        '29ff89fb-8410-4ece-89d0-15538ca761d3';

    private const SHIFT_EXTERNAL_ID =
        '7060f95b-f00e-4ab8-85f2-b233bc44bda5';

    private const ORDER_EXTERNAL_ID =
        '9c2f6ddc-475d-4145-b2a9-7b770e78e55a';

    private const BATCH_EXTERNAL_ID =
        '9c99ecf5-95c3-481d-8b85-66f7372e9fda';

    private const RECORD_EXTERNAL_ID =
        '9af8f465-82ef-4521-8e77-349c0f55f96a';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            ProductionMasterDataSeeder::class
        );

        Product::query()
            ->firstOrFail()
            ->forceFill([
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    self::PRODUCT_EXTERNAL_ID,
            ])
            ->save();

        ProductionLine::query()
            ->firstOrFail()
            ->forceFill([
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    self::LINE_EXTERNAL_ID,
            ])
            ->save();

        Shift::query()
            ->firstOrFail()
            ->forceFill([
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    self::SHIFT_EXTERNAL_ID,
            ])
            ->save();
    }

    public function test_nested_simulator_payloads_map_to_dss_contracts(): void
    {
        $mapper = app(
            ErpRecordMapperInterface::class
        );

        $order = $mapper->map(
            $this->orderRecord()
        );

        $this->assertInstanceOf(
            WorkOrderErpData::class,
            $order
        );

        $this->assertSame(
            self::PRODUCT_EXTERNAL_ID,
            $order->productExternalId
        );

        $this->assertSame(
            self::LINE_EXTERNAL_ID,
            $order->productionLineExternalId
        );

        $this->assertSame(
            'bottles',
            $order->quantityUnit
        );

        $batch = $mapper->map(
            $this->batchRecord()
        );

        $this->assertInstanceOf(
            BatchErpData::class,
            $batch
        );

        $this->assertSame(
            self::ORDER_EXTERNAL_ID,
            $batch->workOrderExternalId
        );

        $this->assertSame(
            3,
            $batch->sequenceNumber
        );

        $this->assertSame(
            '59078.000',
            $batch->actualGoodQuantity
        );

        $record = $mapper->map(
            $this->productionRecord()
        );

        $this->assertInstanceOf(
            ProductionRecordErpData::class,
            $record
        );

        $this->assertSame(
            self::BATCH_EXTERNAL_ID,
            $record->batchExternalId
        );

        $this->assertSame(
            self::LINE_EXTERNAL_ID,
            $record->productionLineExternalId
        );

        $this->assertSame(
            self::SHIFT_EXTERNAL_ID,
            $record->shiftExternalId
        );

        $this->assertNull(
            $record->operatorExternalId
        );

        $this->assertSame(
            ProductionRecordStatus::Locked,
            $record->status
        );

        $this->assertSame(
            ProductionValidationStatus::Validated,
            $record->validationStatus
        );

        $this->assertSame(
            '2026-07-24',
            $record->productionDate
                ->format('Y-m-d')
        );

        $this->assertSame(
            '12595.000',
            $record->producedQuantity
        );
    }

    public function test_nested_simulator_payloads_persist_idempotently(): void
    {
        $mapper = app(
            ErpRecordMapperInterface::class
        );

        $persister = app(
            ErpMappedEntityPersister::class
        );

        $orderResult = $persister->persist(
            $mapper->map(
                $this->orderRecord()
            )
        );

        $batchResult = $persister->persist(
            $mapper->map(
                $this->batchRecord()
            )
        );

        $recordResult = $persister->persist(
            $mapper->map(
                $this->productionRecord()
            )
        );

        $this->assertSame(
            ErpPersistenceAction::Created,
            $orderResult->action
        );

        $this->assertSame(
            ErpPersistenceAction::Created,
            $batchResult->action
        );

        $this->assertSame(
            ErpPersistenceAction::Created,
            $recordResult->action
        );

        $this->assertDatabaseHas(
            'production_orders',
            [
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    self::ORDER_EXTERNAL_ID,

                'quantity_unit' =>
                    'bottles',

                'status' =>
                    'completed',

                'import_status' =>
                    'imported',
            ]
        );

        $orderId = (int) $orderResult
            ->recordId;

        $this->assertDatabaseHas(
            'production_batches',
            [
                'production_order_id' =>
                    $orderId,

                'external_id' =>
                    self::BATCH_EXTERNAL_ID,

                'sequence_number' =>
                    3,

                'actual_good_quantity' =>
                    59078,

                'actual_rejected_quantity' =>
                    928,

                'import_status' =>
                    'imported',
            ]
        );

        $batchId = (int) $batchResult
            ->recordId;

        $this->assertDatabaseHas(
            'production_records',
            [
                'production_batch_id' =>
                    $batchId,

                'external_id' =>
                    self::RECORD_EXTERNAL_ID,

                'status' =>
                    'locked',

                'validation_status' =>
                    'validated',

                'production_date' =>
                    '2026-07-24',

                'produced_quantity' =>
                    12595,

                'good_quantity' =>
                    12343,

                'rejected_quantity' =>
                    252,

                'import_status' =>
                    'imported',
            ]
        );

        $this->assertSame(
            ErpPersistenceAction::Skipped,
            $persister->persist(
                $mapper->map(
                    $this->orderRecord()
                )
            )->action
        );

        $this->assertSame(
            ErpPersistenceAction::Skipped,
            $persister->persist(
                $mapper->map(
                    $this->batchRecord()
                )
            )->action
        );

        $this->assertSame(
            ErpPersistenceAction::Skipped,
            $persister->persist(
                $mapper->map(
                    $this->productionRecord()
                )
            )->action
        );
    }

    private function orderRecord(): ErpSourceRecord
    {
        return $this->record(
            ErpResource::WorkOrders,
            self::ORDER_EXTERNAL_ID,
            [
                'order_number' =>
                    'PO-20260723-SIM_LINE_250ML',

                'planned_start_at' =>
                    '2026-07-23T06:00:00+00:00',

                'planned_end_at' =>
                    '2026-07-24T06:00:00+00:00',

                'planned_quantity' =>
                    192000,

                'priority' => 4,
                'status' => 'completed',

                'notes' =>
                    'Synthetic production order generated by the ERP simulator.',

                'product' => [
                    'external_id' =>
                        self::PRODUCT_EXTERNAL_ID,

                    'code' =>
                        'SIM_PLAISIR_MANGO_250ML',
                ],

                'production_line' => [
                    'external_id' =>
                        self::LINE_EXTERNAL_ID,

                    'code' =>
                        'SIM_LINE_250ML',
                ],
            ]
        );
    }

    private function batchRecord(): ErpSourceRecord
    {
        return $this->record(
            ErpResource::Batches,
            self::BATCH_EXTERNAL_ID,
            [
                'batch_number' =>
                    'BATCH-20260723-SIM_LINE_250ML-SHIFT_NIGHT',

                'lot_number' =>
                    'LOT-20260723-3-3',

                'scheduled_start_at' =>
                    '2026-07-23T22:00:00+00:00',

                'scheduled_end_at' =>
                    '2026-07-24T06:00:00+00:00',

                'actual_start_at' =>
                    '2026-07-23T22:01:00+00:00',

                'actual_end_at' =>
                    '2026-07-24T06:08:00+00:00',

                'planned_quantity' =>
                    69600,

                'gross_quantity' =>
                    60006,

                'good_quantity' =>
                    59078,

                'rejected_quantity' =>
                    928,

                'status' =>
                    'completed',

                'production_order' => [
                    'external_id' =>
                        self::ORDER_EXTERNAL_ID,
                ],

                'shift' => [
                    'external_id' =>
                        self::SHIFT_EXTERNAL_ID,

                    'code' =>
                        'SHIFT_NIGHT',
                ],
            ]
        );
    }

    private function productionRecord(): ErpSourceRecord
    {
        return $this->record(
            ErpResource::MachineRuns,
            self::RECORD_EXTERNAL_ID,
            [
                'record_number' =>
                    'REC-20260723-SIM_LINE_250ML-SHIFT_NIGHT-04',

                'interval_start_at' =>
                    '2026-07-24T04:00:00+00:00',

                'interval_end_at' =>
                    '2026-07-24T06:00:00+00:00',

                'recorded_at' =>
                    '2026-07-24T06:09:00+00:00',

                'target_quantity' =>
                    17400,

                'gross_quantity' =>
                    12595,

                'good_quantity' =>
                    12343,

                'rejected_quantity' =>
                    252,

                'runtime_minutes' =>
                    86,

                'downtime_minutes' =>
                    34,

                'notes' =>
                    'Synthetic interval record from the ERP simulator.',

                'batch' => [
                    'external_id' =>
                        self::BATCH_EXTERNAL_ID,

                    'production_line' => [
                        'external_id' =>
                            self::LINE_EXTERNAL_ID,
                    ],

                    'shift' => [
                        'external_id' =>
                            self::SHIFT_EXTERNAL_ID,
                    ],
                ],

                'production_line' => [
                    'external_id' =>
                        self::LINE_EXTERNAL_ID,
                ],

                'shift' => [
                    'external_id' =>
                        self::SHIFT_EXTERNAL_ID,
                ],

                'machine' =>
                    null,
            ]
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function record(
        ErpResource $resource,
        string $externalId,
        array $attributes
    ): ErpSourceRecord {
        return new ErpSourceRecord(
            identity:
                new ErpSourceIdentity(
                    sourceSystem:
                        'simulated_sage',

                    resource:
                        $resource,

                    externalId:
                        $externalId
                ),

            attributes:
                $attributes,

            sourceVersion:
                1,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-24T06:28:00+00:00'
                ),

            receivedAt:
                CarbonImmutable::parse(
                    '2026-07-31T22:00:00+00:00'
                )
        );
    }
}
