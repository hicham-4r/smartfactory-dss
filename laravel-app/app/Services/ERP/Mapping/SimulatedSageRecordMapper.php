<?php

namespace App\Services\ERP\Mapping;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\Contracts\ERP\ErpRecordMapperInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\BatchErpData;
use App\DTOs\ERP\Mapped\DowntimeEventErpData;
use App\DTOs\ERP\Mapped\FinishedLotErpData;
use App\DTOs\ERP\Mapped\InspectionErpData;
use App\DTOs\ERP\Mapped\MachineErpData;
use App\DTOs\ERP\Mapped\ProductionRecordErpData;
use App\DTOs\ERP\Mapped\MachineStatusEventErpData;
use App\DTOs\ERP\Mapped\MaintenanceHistoryErpData;
use App\DTOs\ERP\Mapped\NonconformityErpData;
use App\DTOs\ERP\Mapped\OperatorAssignmentErpData;
use App\DTOs\ERP\Mapped\OperatorErpData;
use App\DTOs\ERP\Mapped\ProductErpData;
use App\DTOs\ERP\Mapped\ProductFamilyErpData;
use App\DTOs\ERP\Mapped\ProductionLineErpData;
use App\DTOs\ERP\Mapped\RunLogErpData;
use App\DTOs\ERP\Mapped\ShiftErpData;
use App\DTOs\ERP\Mapped\WorkOrderErpData;
use App\Enums\ERP\ErpFinishedLotStatus;
use App\Enums\ERP\ErpInspectionResult;
use App\Enums\ERP\ErpMachineStatus;
use App\Enums\ERP\ErpMaintenanceStatus;
use App\Enums\ERP\ErpMaintenanceType;
use App\Enums\ERP\ErpNonconformitySeverity;
use App\Enums\ERP\ErpNonconformityStatus;
use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpRunLogType;
use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Exceptions\ERP\ErpMappingException;
use Carbon\CarbonImmutable;

final class SimulatedSageRecordMapper implements
    ErpRecordMapperInterface
{
    private const SOURCE_SYSTEM =
        'simulated_sage';

    public function name(): string
    {
        return 'Simulated Sage ERP mapper';
    }

    public function sourceSystem(): string
    {
        return self::SOURCE_SYSTEM;
    }

    public function supports(
        ErpSourceRecord $record
    ): bool {
        return $record
            ->identity
            ->sourceSystem
            === self::SOURCE_SYSTEM;
    }

    public function map(
        ErpSourceRecord $record
    ): ErpMappedEntityInterface {
        if (! $this->supports($record)) {
            throw ErpMappingException::unsupportedRecord(
                $record
            );
        }

        $reader = new ErpPayloadReader(
            $record
        );

        return match (
            $record->identity->resource
        ) {
            ErpResource::ProductFamilies =>
                $this->mapProductFamily(
                    $record,
                    $reader
                ),

            ErpResource::Products =>
                $this->mapProduct(
                    $record,
                    $reader
                ),

            ErpResource::ProductionLines =>
                $this->mapProductionLine(
                    $record,
                    $reader
                ),

            ErpResource::Machines =>
                $this->mapMachine(
                    $record,
                    $reader
                ),

            ErpResource::Shifts =>
                $this->mapShift(
                    $record,
                    $reader
                ),

            ErpResource::Operators =>
                $this->mapOperator(
                    $record,
                    $reader
                ),

            ErpResource::OperatorAssignments =>
                $this->mapOperatorAssignment(
                    $record,
                    $reader
                ),

            ErpResource::WorkOrders =>
                $this->mapWorkOrder(
                    $record,
                    $reader
                ),

            ErpResource::Batches =>
                $this->mapBatch(
                    $record,
                    $reader
                ),

            ErpResource::MachineRuns =>
                $this->mapMachineRun(
                    $record,
                    $reader
                ),

            ErpResource::RunLogs =>
                $this->mapRunLog(
                    $record,
                    $reader
                ),

            ErpResource::DowntimeEvents =>
                $this->mapDowntimeEvent(
                    $record,
                    $reader
                ),

            ErpResource::MachineStatusEvents =>
                $this->mapMachineStatusEvent(
                    $record,
                    $reader
                ),

            ErpResource::MaintenanceHistory =>
                $this->mapMaintenanceHistory(
                    $record,
                    $reader
                ),

            ErpResource::Inspections =>
                $this->mapInspection(
                    $record,
                    $reader
                ),

            ErpResource::Nonconformities =>
                $this->mapNonconformity(
                    $record,
                    $reader
                ),

            ErpResource::FinishedLots =>
                $this->mapFinishedLot(
                    $record,
                    $reader
                ),
        };
    }

    private function mapProductFamily(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): ProductFamilyErpData {
        return new ProductFamilyErpData(
            source: $source,

            code: $reader->requiredString(
                'code',
                [
                    'family_code',
                    'reference',
                ],
                50
            ),

            name: $reader->requiredString(
                'name',
                [
                    'label',
                    'designation',
                ],
                180
            ),

            description: $reader->optionalString(
                'description',
                ['notes'],
                2000
            ),

            isActive: $reader->optionalBoolean(
                'is_active',
                [
                    'active',
                    'enabled',
                    'status',
                ],
                true
            ),
        );
    }

    private function mapProduct(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): ProductErpData {
        return new ProductErpData(
            source: $source,

            code: $reader->requiredString(
                'code',
                [
                    'product_code',
                    'reference',
                ],
                50
            ),

            name: $reader->requiredString(
                'name',
                [
                    'label',
                    'designation',
                ],
                180
            ),

            productFamilyExternalId:
                $reader->requiredReference(
                    'product_family_external_id',
                    [
                        'product_family_id',
                        'family_id',
                        'family_external_id',
                        'family_code',
                    ]
                ),

            sku: $reader->optionalString(
                'sku',
                [
                    'stock_code',
                    'article_code',
                ],
                100
            ),

            quantityUnit:
                $reader->optionalString(
                    'quantity_unit',
                    [
                        'unit',
                        'uom',
                        'unit_of_measure',
                    ],
                    30
                ) ?? 'unit',

            isActive: $reader->optionalBoolean(
                'is_active',
                [
                    'active',
                    'enabled',
                    'status',
                ],
                true
            ),
        );
    }

    private function mapProductionLine(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): ProductionLineErpData {
        return new ProductionLineErpData(
            source: $source,

            code: $reader->requiredString(
                'code',
                [
                    'line_code',
                    'reference',
                ],
                50
            ),

            name: $reader->requiredString(
                'name',
                [
                    'label',
                    'designation',
                ],
                180
            ),

            description: $reader->optionalString(
                'description',
                ['notes'],
                2000
            ),

            isActive: $reader->optionalBoolean(
                'is_active',
                [
                    'active',
                    'enabled',
                    'status',
                ],
                true
            ),
        );
    }

    private function mapMachine(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): MachineErpData {
        return new MachineErpData(
            source: $source,

            code: $reader->requiredString(
                'code',
                [
                    'machine_code',
                    'reference',
                ],
                50
            ),

            name: $reader->requiredString(
                'name',
                [
                    'label',
                    'designation',
                ],
                150
            ),

            /*
             * The separate simulator exposes line membership as a
             * production_lines collection. A machine currently belongs
             * to exactly one line in the simulator configuration.
             */
            productionLineExternalId:
                $reader->requiredReference(
                    'production_line_external_id',
                    [
                        'production_lines.0.external_id',
                        'production_line.external_id',
                        'production_line_id',
                        'line_id',
                        'line_external_id',
                        'line_code',
                    ]
                ),

            machineType:
                $reader->requiredString(
                    'machine_type',
                    [
                        'type',
                        'category',
                    ],
                    100
                ),

            manufacturer:
                $reader->optionalString(
                    'manufacturer',
                    [
                        'brand',
                        'make',
                    ],
                    120
                ),

            model: $reader->optionalString(
                'model',
                [
                    'model_reference',
                    'model_number',
                ],
                120
            ),

            serialNumber:
                $reader->optionalString(
                    'serial_number',
                    [
                        'serial',
                        'serial_no',
                    ],
                    120
                ),

            sequenceNumber:
                $reader->optionalInteger(
                    'sequence_number',
                    [
                        'production_lines.0.sequence_order',
                        'sequence_order',
                        'position',
                    ],
                    null,
                    1,
                    65535
                ),

            isCritical:
                $this->machineIsCritical(
                    $reader
                ),

            isActive: $reader->optionalBoolean(
                'is_active',
                [
                    'active',
                    'enabled',
                    'status',
                ],
                true
            ),
        );
    }

    private function machineIsCritical(
        ErpPayloadReader $reader
    ): bool {
        if (
            $reader->optionalBoolean(
                'is_critical',
                [
                    'critical',
                ],
                false
            )
        ) {
            return true;
        }

        $criticality =
            $reader->optionalString(
                'criticality',
                [
                    'risk_level',
                ],
                30
            );

        if ($criticality === null) {
            return false;
        }

        return in_array(
            strtolower(trim($criticality)),
            [
                'critical',
                'high',
            ],
            true
        );
    }

    private function mapShift(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): ShiftErpData {
        $startTime = $reader->requiredTime(
            'start_time',
            [
                'starts_at',
                'start',
            ]
        );

        $endTime = $reader->requiredTime(
            'end_time',
            [
                'ends_at',
                'end',
            ]
        );

        return new ShiftErpData(
            source: $source,

            code: $reader->requiredString(
                'code',
                [
                    'shift_code',
                    'reference',
                ],
                50
            ),

            name: $reader->requiredString(
                'name',
                [
                    'label',
                    'designation',
                ],
                180
            ),

            startTime: $startTime,
            endTime: $endTime,

            crossesMidnight:
                $endTime <= $startTime,

            isActive: $reader->optionalBoolean(
                'is_active',
                [
                    'active',
                    'enabled',
                    'status',
                ],
                true
            ),
        );
    }

    private function mapOperator(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): OperatorErpData {
        $firstName =
            $reader->optionalString(
                'first_name',
                [
                    'given_name',
                    'forename',
                ],
                100
            );

        $lastName =
            $reader->optionalString(
                'last_name',
                [
                    'family_name',
                    'surname',
                ],
                100
            );

        $displayName =
            $this->operatorName(
                $reader
            );

        [
            $firstName,
            $lastName,
        ] = $this->operatorNameParts(
            displayName: $displayName,
            firstName: $firstName,
            lastName: $lastName
        );

        return new OperatorErpData(
            source: $source,

            employeeNumber:
                $reader->requiredString(
                    'employee_number',
                    [
                        'operator_number',
                        'employee_code',
                        'code',
                        'matricule',
                    ],
                    80
                ),

            name: $displayName,
            firstName: $firstName,
            lastName: $lastName,

            email: $reader->optionalEmail(
                'email',
                ['email_address']
            ),

            phone: $reader->optionalString(
                'phone',
                [
                    'phone_number',
                    'mobile',
                ],
                40
            ),

            hiredOn: $reader->optionalDate(
                'hire_date',
                [
                    'hired_on',
                    'employment_start_date',
                ]
            ),

            jobTitle: $reader->optionalString(
                'job_title',
                [
                    'position',
                    'role',
                ],
                120
            ),

            isActive: $reader->optionalBoolean(
                'is_active',
                [
                    'active',
                    'enabled',
                    'status',
                ],
                true
            ),
        );
    }

    private function operatorName(
        ErpPayloadReader $reader
    ): string {
        $directName = $reader->optionalString(
            'name',
            [
                'full_name',
                'display_name',
            ],
            180
        );

        if ($directName !== null) {
            return $directName;
        }

        $firstName = $reader->optionalString(
            'first_name',
            [
                'given_name',
                'forename',
            ],
            100
        );

        $lastName = $reader->optionalString(
            'last_name',
            [
                'family_name',
                'surname',
            ],
            100
        );

        $combinedName = trim(
            implode(
                ' ',
                array_filter(
                    [
                        $firstName,
                        $lastName,
                    ],
                    static fn (?string $part): bool =>
                        $part !== null
                        && trim($part) !== ''
                )
            )
        );

        if ($combinedName !== '') {
            return $combinedName;
        }

        return $reader->requiredString(
            'name',
            [
                'full_name',
                'display_name',
            ],
            180
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function operatorNameParts(
        string $displayName,
        ?string $firstName,
        ?string $lastName
    ): array {
        if (
            $firstName !== null
            && $lastName !== null
        ) {
            return [
                $firstName,
                $lastName,
            ];
        }

        $parts = preg_split(
            '/\s+/',
            trim($displayName),
            2
        );

        $derivedFirstName =
            trim((string) ($parts[0] ?? ''));

        $derivedLastName =
            trim((string) ($parts[1] ?? ''));

        if ($derivedFirstName === '') {
            $derivedFirstName = 'Unknown';
        }

        if ($derivedLastName === '') {
            $derivedLastName = 'Operator';
        }

        return [
            $firstName
                ?? mb_substr(
                    $derivedFirstName,
                    0,
                    100
                ),

            $lastName
                ?? mb_substr(
                    $derivedLastName,
                    0,
                    100
                ),
        ];
    }

    private function mapOperatorAssignment(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): OperatorAssignmentErpData {
        $validFrom =
            $reader->requiredDate(
                'valid_from',
                [
                    'starts_on',
                    'starts_at',
                    'start_date',
                    'assigned_from',
                    'effective_from',
                ]
            );

        $validUntil =
            $reader->optionalDate(
                'valid_until',
                [
                    'valid_to',
                    'ends_on',
                    'ends_at',
                    'end_date',
                    'assigned_until',
                    'effective_until',
                ]
            );

        $this->assertChronology(
            $source,
            $validFrom,
            $validUntil,
            'valid_from',
            'valid_until'
        );

        return new OperatorAssignmentErpData(
            source: $source,

            operatorExternalId:
                $reader->requiredReference(
                    'operator_external_id',
                    [
                        'operator.external_id',
                        'operator_id',
                        'employee_id',
                        'employee_number',
                    ]
                ),

            productionLineExternalId:
                $reader->requiredReference(
                    'production_line_external_id',
                    [
                        'production_line.external_id',
                        'production_line_id',
                        'line_id',
                        'line_external_id',
                    ]
                ),

            shiftExternalId:
                $reader->requiredReference(
                    'shift_external_id',
                    [
                        'shift.external_id',
                        'shift_id',
                        'shift_code',
                    ]
                ),

            validFrom: $validFrom,
            validUntil: $validUntil,

            isPrimary:
                $reader->optionalBoolean(
                    'is_primary',
                    [
                        'primary',
                    ],
                    false
                ),

            isActive: $reader->optionalBoolean(
                'is_active',
                [
                    'active',
                    'enabled',
                    'status',
                ],
                true
            ),
        );
    }


    private function mapWorkOrder(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): WorkOrderErpData {
        $plannedStart =
            $reader->requiredDateTime(
                'planned_start_at',
                [
                    'planned_start',
                    'scheduled_start_at',
                    'start_date',
                ]
            );

        $plannedEnd =
            $reader->optionalDateTime(
                'planned_end_at',
                [
                    'planned_end',
                    'scheduled_end_at',
                    'end_date',
                ]
            );

        $this->assertChronology(
            $source,
            $plannedStart,
            $plannedEnd,
            'planned_start_at',
            'planned_end_at'
        );

        return new WorkOrderErpData(
            source: $source,

            orderNumber:
                $reader->requiredString(
                    'order_number',
                    [
                        'work_order_number',
                        'number',
                        'reference',
                    ],
                    120
                ),

            productExternalId:
                $reader->requiredReference(
                    'product_external_id',
                    [
                        'product.external_id',
                        'product_id',
                        'article_id',
                        'product_code',
                        'product.code',
                    ]
                ),

            productionLineExternalId:
                $reader->requiredReference(
                    'production_line_external_id',
                    [
                        'production_line.external_id',
                        'production_line_id',
                        'line_id',
                        'line_code',
                        'production_line.code',
                    ]
                ),

            shiftExternalId:
                $reader->optionalReference(
                    'shift_external_id',
                    [
                        'shift.external_id',
                        'shift_id',
                        'shift_code',
                        'shift.code',
                    ]
                ),

            status: $this->orderStatus(
                $source,
                $reader->requiredString(
                    'status',
                    ['state'],
                    50
                )
            ),

            plannedStartAt: $plannedStart,
            plannedEndAt: $plannedEnd,

            targetQuantity:
                $reader->requiredDecimal(
                    'target_quantity',
                    [
                        'planned_quantity',
                        'quantity',
                    ],
                    3,
                    0.001,
                    9999999999999.999
                ),

            /*
             * The simulator expresses quantities as packaged units and
             * does not repeat the unit on every operational record.
             */
            quantityUnit:
                $reader->optionalString(
                    'quantity_unit',
                    [
                        'unit',
                        'uom',
                        'product.base_unit',
                    ],
                    30
                ) ?? 'bottles',

            priority: $reader->optionalInteger(
                'priority',
                [
                    'priority_level',
                ],
                3,
                1,
                5
            ) ?? 3,

            instructions:
                $reader->optionalString(
                    'instructions',
                    [
                        'notes',
                        'production_notes',
                    ],
                    5000
                ),
        );
    }


    private function mapBatch(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): BatchErpData {
        $actualStart =
            $reader->optionalDateTime(
                'actual_start_at',
                [
                    'started_at',
                    'actual_start',
                ]
            );

        $actualEnd =
            $reader->optionalDateTime(
                'actual_end_at',
                [
                    'ended_at',
                    'actual_end',
                ]
            );

        if ($actualStart !== null) {
            $this->assertChronology(
                $source,
                $actualStart,
                $actualEnd,
                'actual_start_at',
                'actual_end_at'
            );
        } elseif ($actualEnd !== null) {
            throw ErpMappingException::invalidField(
                $source,
                'actual_end_at',
                'actual end with an actual start'
            );
        }

        return new BatchErpData(
            source: $source,

            batchNumber:
                $reader->requiredString(
                    'batch_number',
                    [
                        'lot_number',
                        'number',
                        'reference',
                    ],
                    120
                ),

            workOrderExternalId:
                $reader->requiredReference(
                    'work_order_external_id',
                    [
                        'production_order.external_id',
                        'production_order_external_id',
                        'work_order_id',
                        'production_order_id',
                        'order_id',
                    ]
                ),

            sequenceNumber:
                $this->batchSequenceNumber(
                    $reader
                ),

            status: $this->batchStatus(
                $source,
                $reader->requiredString(
                    'status',
                    ['state'],
                    50
                )
            ),

            plannedQuantity:
                $reader->requiredDecimal(
                    'planned_quantity',
                    ['quantity'],
                    3,
                    0.001
                ),

            actualGoodQuantity:
                $reader->optionalDecimal(
                    'actual_good_quantity',
                    [
                        'good_quantity',
                        'accepted_quantity',
                    ],
                    '0.000',
                    3,
                    0
                ) ?? '0.000',

            actualRejectedQuantity:
                $reader->optionalDecimal(
                    'actual_rejected_quantity',
                    [
                        'rejected_quantity',
                        'scrap_quantity',
                    ],
                    '0.000',
                    3,
                    0
                ) ?? '0.000',

            quantityUnit:
                $reader->optionalString(
                    'quantity_unit',
                    [
                        'unit',
                        'uom',
                        'production_order.product.base_unit',
                    ],
                    30
                ) ?? 'bottles',

            scheduledStartAt:
                $reader->optionalDateTime(
                    'scheduled_start_at',
                    [
                        'planned_start_at',
                        'scheduled_start',
                    ]
                ),

            actualStartAt: $actualStart,
            actualEndAt: $actualEnd,
        );
    }



    private function batchSequenceNumber(
        ErpPayloadReader $reader
    ): int {
        $explicit =
            $reader->optionalInteger(
                'sequence_number',
                [
                    'sequence',
                    'batch_sequence',
                ],
                null,
                1,
                65535
            );

        if ($explicit !== null) {
            return $explicit;
        }

        $shiftCode =
            $reader->optionalString(
                'shift_code',
                [
                    'shift.code',
                ],
                80
            );

        if ($shiftCode !== null) {
            $normalized = strtoupper(
                str_replace(
                    [
                        '-',
                        ' ',
                    ],
                    '_',
                    trim($shiftCode)
                )
            );

            if (
                str_contains(
                    $normalized,
                    'MORNING'
                )
                || str_contains(
                    $normalized,
                    'DAY'
                )
            ) {
                return 1;
            }

            if (
                str_contains(
                    $normalized,
                    'AFTERNOON'
                )
                || str_contains(
                    $normalized,
                    'EVENING'
                )
            ) {
                return 2;
            }

            if (
                str_contains(
                    $normalized,
                    'NIGHT'
                )
            ) {
                return 3;
            }

            if (
                preg_match(
                    '/(?:^|_)([1-9]\d*)$/',
                    $normalized,
                    $matches
                ) === 1
            ) {
                return min(
                    65535,
                    (int) $matches[1]
                );
            }
        }

        $scheduled =
            $reader->optionalDateTime(
                'scheduled_start_at',
                [
                    'planned_start_at',
                    'scheduled_start',
                ]
            );

        if ($scheduled === null) {
            return 1;
        }

        $hour = (int) $scheduled
            ->format('G');

        if (
            $hour >= 22
            || $hour < 6
        ) {
            return 3;
        }

        if ($hour >= 14) {
            return 2;
        }

        return 1;
    }
    private function mapMachineRun(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): ProductionRecordErpData {
        $startedAt =
            $reader->optionalDateTime(
                'started_at',
                [
                    'interval_start_at',
                    'start_time',
                    'actual_start_at',
                ]
            );

        $endedAt =
            $reader->optionalDateTime(
                'ended_at',
                [
                    'interval_end_at',
                    'end_time',
                    'actual_end_at',
                ]
            );

        if ($startedAt !== null) {
            $this->assertChronology(
                $source,
                $startedAt,
                $endedAt,
                'started_at',
                'ended_at'
            );
        } elseif ($endedAt !== null) {
            throw ErpMappingException::invalidField(
                $source,
                'ended_at',
                'end timestamp with a start timestamp'
            );
        }

        $produced =
            $reader->requiredDecimal(
                'produced_quantity',
                [
                    'gross_quantity',
                    'total_quantity',
                    'quantity',
                ],
                3,
                0
            );

        $good =
            $reader->requiredDecimal(
                'good_quantity',
                [
                    'accepted_quantity',
                ],
                3,
                0
            );

        $rejected =
            $reader->requiredDecimal(
                'rejected_quantity',
                [
                    'scrap_quantity',
                    'failed_quantity',
                ],
                3,
                0
            );

        if (
            $this->quantityToMilliUnits($produced)
            !== $this->quantityToMilliUnits($good)
                + $this->quantityToMilliUnits($rejected)
        ) {
            throw ErpMappingException::invalidField(
                $source,
                'produced_quantity',
                'good quantity plus rejected quantity'
            );
        }

        $runtime =
            $reader->optionalInteger(
                'runtime_minutes',
                ['runtime'],
                0,
                0
            ) ?? 0;

        $downtime =
            $reader->optionalInteger(
                'downtime_minutes',
                ['downtime'],
                0,
                0
            ) ?? 0;

        if (
            $startedAt !== null
            && $endedAt !== null
        ) {
            $elapsed = max(
                0,
                (int) floor(
                    (
                        $endedAt->getTimestamp()
                        - $startedAt->getTimestamp()
                    ) / 60
                )
            );

            if ($runtime + $downtime > $elapsed) {
                throw ErpMappingException::invalidField(
                    $source,
                    'runtime_minutes',
                    'runtime plus downtime within the recorded timeline'
                );
            }
        }

        $recordedAt =
            $reader->optionalDateTime(
                'recorded_at',
                [
                    'submitted_at',
                    'created_at',
                ]
            );

        $lockedAt =
            $source->sourceUpdatedAt
            ?? $recordedAt
            ?? $source->receivedAt
            ?? CarbonImmutable::now();

        return new ProductionRecordErpData(
            source: $source,

            recordNumber:
                $reader->requiredString(
                    'record_number',
                    [
                        'run_number',
                        'machine_run_number',
                        'number',
                        'reference',
                    ],
                    120
                ),

            batchExternalId:
                $reader->requiredReference(
                    'batch_external_id',
                    [
                        'batch.external_id',
                        'batch_id',
                        'production_batch_id',
                    ]
                ),

            productionLineExternalId:
                $reader->requiredReference(
                    'production_line_external_id',
                    [
                        'production_line.external_id',
                        'batch.production_line.external_id',
                        'production_line_id',
                        'line_id',
                        'production_line.code',
                    ]
                ),

            shiftExternalId:
                $reader->requiredReference(
                    'shift_external_id',
                    [
                        'shift.external_id',
                        'batch.shift.external_id',
                        'shift_id',
                        'shift.code',
                    ]
                ),

            operatorExternalId:
                $reader->optionalReference(
                    'operator_external_id',
                    [
                        'operator.external_id',
                        'batch.operator_assignment.operator.external_id',
                        'operator_id',
                        'employee_id',
                    ]
                ),

            productionDate:
                $reader->requiredDate(
                    'production_date',
                    [
                        'interval_start_at',
                        'started_at',
                        'recorded_at',
                    ]
                ),

            startedAt: $startedAt,
            endedAt: $endedAt,

            producedQuantity: $produced,
            goodQuantity: $good,
            rejectedQuantity: $rejected,

            quantityUnit:
                $reader->optionalString(
                    'quantity_unit',
                    [
                        'unit',
                        'uom',
                        'batch.production_order.product.base_unit',
                    ],
                    30
                ) ?? 'bottles',

            runtimeMinutes: $runtime,
            downtimeMinutes: $downtime,

            /*
             * ERP-imported records are authoritative history. They are
             * stored as locked and validated so operators cannot edit or
             * submit them through the manual workflow.
             */
            status:
                ProductionRecordStatus::Locked,

            validationStatus:
                ProductionValidationStatus::Validated,

            submittedAt:
                $recordedAt
                ?? $endedAt
                ?? $startedAt,

            lockedAt:
                $lockedAt,

            notes:
                $reader->optionalString(
                    'notes',
                    [
                        'comment',
                        'description',
                    ],
                    5000
                ),
        );
    }

    private function mapRunLog(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): RunLogErpData {
        return new RunLogErpData(
            source: $source,

            machineRunExternalId:
                $reader->requiredReference(
                    'machine_run_external_id',
                    [
                        'machine_run_id',
                        'run_id',
                    ]
                ),

            machineExternalId:
                $reader->optionalReference(
                    'machine_external_id',
                    [
                        'machine_id',
                        'equipment_id',
                    ]
                ),

            logType: $this->runLogType(
                $source,
                $reader->requiredString(
                    'log_type',
                    [
                        'type',
                        'category',
                    ],
                    50
                )
            ),

            message: $reader->requiredString(
                'message',
                [
                    'description',
                    'content',
                    'notes',
                ],
                5000
            ),

            recordedAt:
                $reader->requiredDateTime(
                    'recorded_at',
                    [
                        'logged_at',
                        'created_at',
                        'occurred_at',
                    ]
                ),

            numericValue:
                $reader->optionalDecimal(
                    'numeric_value',
                    [
                        'value',
                        'measurement',
                    ],
                    null,
                    3
                ),

            unit: $reader->optionalString(
                'unit',
                [
                    'measurement_unit',
                    'uom',
                ],
                30
            ),
        );
    }

    private function mapDowntimeEvent(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): DowntimeEventErpData {
        $startedAt =
            $reader->requiredDateTime(
                'started_at',
                [
                    'start_time',
                    'occurred_at',
                ]
            );

        $endedAt =
            $reader->optionalDateTime(
                'ended_at',
                [
                    'end_time',
                    'resolved_at',
                ]
            );

        $this->assertChronology(
            $source,
            $startedAt,
            $endedAt,
            'started_at',
            'ended_at'
        );

        $resolved =
            $reader->optionalBoolean(
                'is_resolved',
                [
                    'resolved',
                    'resolution_status',
                    'status',
                ],
                $endedAt !== null
            );

        if ($resolved && $endedAt === null) {
            throw ErpMappingException::invalidField(
                $source,
                'ended_at',
                'resolution timestamp for a resolved downtime event'
            );
        }

        $duration =
            $reader->optionalInteger(
                'duration_minutes',
                ['duration'],
                null,
                0
            );

        if (
            $duration === null
            && $endedAt !== null
        ) {
            $duration = max(
                0,
                (int) floor(
                    (
                        $endedAt->getTimestamp()
                        - $startedAt->getTimestamp()
                    ) / 60
                )
            );
        }

        $category =
            $reader->requiredString(
                'category',
                [
                    'downtime_category',
                ],
                120
            );

        $downtimeType =
            $reader->optionalString(
                'downtime_type',
                [
                    'type',
                    'event_type',
                ],
                80
            ) ?? 'downtime';

        $explicitSeverity =
            $reader->optionalString(
                'severity',
                [
                    'priority',
                    'level',
                ],
                50
            );

        if ($explicitSeverity !== null) {
            $severity =
                $this->eventSeverity(
                    $source,
                    $explicitSeverity
                );
        } else {
            $normalizedType = strtolower(
                str_replace(
                    [
                        '-',
                        ' ',
                    ],
                    '_',
                    trim($downtimeType)
                )
            );

            $normalizedCategory =
                strtolower(
                    trim($category)
                );

            $derivedSeverity =
                match (true) {
                    in_array(
                        $normalizedType,
                        [
                            'breakdown',
                            'utility_failure',
                            'safety_stop',
                        ],
                        true
                    ) => 'critical',

                    $normalizedCategory === 'unplanned',
                    in_array(
                        $normalizedType,
                        [
                            'material_shortage',
                            'quality_hold',
                            'equipment_fault',
                        ],
                        true
                    ) => 'warning',

                    default => 'info',
                };

            $severity =
                $this->eventSeverity(
                    $source,
                    $derivedSeverity
                );
        }

        return new DowntimeEventErpData(
            source: $source,

            eventNumber:
                $reader->requiredString(
                    'event_number',
                    [
                        'downtime_number',
                        'number',
                        'reference',
                    ],
                    120
                ),

            machineExternalId:
                $reader->requiredReference(
                    'machine_external_id',
                    [
                        'machine.external_id',
                        'machine_id',
                        'equipment_id',
                    ]
                ),

            productionLineExternalId:
                $reader->requiredReference(
                    'production_line_external_id',
                    [
                        'production_line.external_id',
                        'production_line_id',
                        'line_id',
                    ]
                ),

            batchExternalId:
                $reader->optionalReference(
                    'batch_external_id',
                    [
                        'production_batch.external_id',
                        'production_batch_id',
                        'batch_id',
                    ]
                ),

            shiftExternalId:
                $reader->optionalReference(
                    'shift_external_id',
                    [
                        'shift.external_id',
                        'production_batch.shift.external_id',
                        'shift_id',
                    ]
                ),

            operatorExternalId:
                $reader->optionalReference(
                    'operator_external_id',
                    [
                        'operator.external_id',
                        'operator_id',
                        'employee_id',
                    ]
                ),

            severity: $severity,
            category: $category,
            downtimeType: $downtimeType,

            reasonCode:
                $reader->optionalString(
                    'reason_code',
                    [
                        'code',
                    ],
                    80
                ),

            reason: $reader->optionalString(
                'reason',
                [
                    'reason_description',
                    'description',
                    'cause',
                ],
                5000
            ),

            startedAt: $startedAt,
            endedAt: $endedAt,
            durationMinutes: $duration,
            isResolved: $resolved,
        );
    }

    private function mapMachineStatusEvent(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): MachineStatusEventErpData {
        $occurredAt =
            $reader->requiredDateTime(
                'occurred_at',
                [
                    'started_at',
                    'status_at',
                ]
            );

        $endedAt =
            $reader->optionalDateTime(
                'ended_at',
                [
                    'finished_at',
                    'status_end_at',
                ]
            );

        $this->assertChronology(
            $source,
            $occurredAt,
            $endedAt,
            'occurred_at',
            'ended_at'
        );

        return new MachineStatusEventErpData(
            source: $source,

            machineExternalId:
                $reader->requiredReference(
                    'machine_external_id',
                    [
                        'machine.external_id',
                        'machine_id',
                        'equipment_id',
                    ]
                ),

            status: $this->machineStatus(
                $source,
                $reader->requiredString(
                    'status',
                    [
                        'status_code',
                        'machine_status',
                        'state',
                    ],
                    50
                )
            ),

            occurredAt: $occurredAt,
            endedAt: $endedAt,

            reason: $reader->optionalString(
                'reason',
                [
                    'description',
                    'notes',
                ],
                2000
            ),
        );
    }

    private function mapMaintenanceHistory(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): MaintenanceHistoryErpData {
        $scheduledAt =
            $reader->optionalDateTime(
                'scheduled_at',
                [
                    'reported_at',
                    'planned_at',
                    'scheduled_date',
                ]
            );

        $startedAt =
            $reader->optionalDateTime(
                'started_at',
                [
                    'actual_start_at',
                    'start_date',
                ]
            );

        $completedAt =
            $reader->optionalDateTime(
                'completed_at',
                [
                    'ended_at',
                    'actual_end_at',
                    'completion_date',
                ]
            );

        if (
            $scheduledAt !== null
            && $startedAt !== null
        ) {
            $this->assertChronology(
                $source,
                $scheduledAt,
                $startedAt,
                'scheduled_at',
                'started_at'
            );
        }

        if (
            $startedAt !== null
            && $completedAt !== null
        ) {
            $this->assertChronology(
                $source,
                $startedAt,
                $completedAt,
                'started_at',
                'completed_at'
            );
        } elseif ($completedAt !== null) {
            throw ErpMappingException::invalidField(
                $source,
                'completed_at',
                'completion timestamp with a start timestamp'
            );
        }

        $status = $this->maintenanceStatus(
            $source,
            $reader->requiredString(
                'status',
                ['state'],
                50
            )
        );

        if (
            $status === ErpMaintenanceStatus::Completed
            && $completedAt === null
        ) {
            throw ErpMappingException::invalidField(
                $source,
                'completed_at',
                'completion timestamp for completed maintenance'
            );
        }

        return new MaintenanceHistoryErpData(
            source: $source,

            maintenanceNumber:
                $reader->requiredString(
                    'maintenance_number',
                    [
                        'work_request_number',
                        'number',
                        'reference',
                    ],
                    120
                ),

            machineExternalId:
                $reader->requiredReference(
                    'machine_external_id',
                    [
                        'machine.external_id',
                        'machine_id',
                        'equipment_id',
                    ]
                ),

            maintenanceType:
                $this->maintenanceType(
                    $source,
                    $reader->requiredString(
                        'maintenance_type',
                        [
                            'type',
                            'category',
                        ],
                        50
                    )
                ),

            status: $status,
            scheduledAt: $scheduledAt,
            startedAt: $startedAt,
            completedAt: $completedAt,

            /*
             * technician_name is descriptive text, not a stable ERP
             * external identifier. It must not be used as a foreign key.
             */
            performedByExternalId:
                $reader->optionalReference(
                    'performed_by_external_id',
                    [
                        'technician.external_id',
                        'performed_by.external_id',
                        'technician_id',
                        'operator_id',
                    ]
                ),

            description:
                $reader->optionalString(
                    'description',
                    [
                        'failure_description',
                        'problem_description',
                        'root_cause',
                        'notes',
                    ],
                    5000
                ),

            actionsTaken:
                $reader->optionalString(
                    'actions_taken',
                    [
                        'resolution',
                        'work_performed',
                    ],
                    5000
                ),

            downtimeMinutes:
                $reader->optionalInteger(
                    'downtime_minutes',
                    [
                        'repair_duration_minutes',
                        'downtime',
                    ],
                    0,
                    0
                ) ?? 0,

            cost: $reader->optionalDecimal(
                'cost',
                [
                    'costs.total_cost',
                    'maintenance_cost',
                    'total_cost',
                ],
                null,
                2,
                0
            ),

            currency: $reader->optionalString(
                'currency',
                [
                    'costs.currency_code',
                    'currency_code',
                ],
                3
            ),
        );
    }

    private function mapInspection(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): InspectionErpData {
        $sampleSize =
            $reader->optionalInteger(
                'sample_size',
                [
                    'sample_quantity',
                    'inspected_quantity',
                ],
                null,
                0
            );

        $passed =
            $reader->optionalInteger(
                'passed_quantity',
                [
                    'accepted_quantity',
                    'passed_count',
                ],
                null,
                0
            );

        $failed =
            $reader->optionalInteger(
                'failed_quantity',
                [
                    'rejected_quantity',
                    'failed_count',
                ],
                null,
                0
            );

        if (
            $sampleSize !== null
            && ($passed ?? 0) + ($failed ?? 0)
                > $sampleSize
        ) {
            throw ErpMappingException::invalidField(
                $source,
                'sample_size',
                'sample size greater than or equal to passed plus failed quantities'
            );
        }

        return new InspectionErpData(
            source: $source,

            inspectionNumber:
                $reader->requiredString(
                    'inspection_number',
                    [
                        'number',
                        'reference',
                    ],
                    120
                ),

            batchExternalId:
                $reader->requiredReference(
                    'batch_external_id',
                    [
                        'batch_id',
                        'production_batch_id',
                    ]
                ),

            finishedLotExternalId:
                $reader->optionalReference(
                    'finished_lot_external_id',
                    [
                        'finished_lot_id',
                        'lot_id',
                    ]
                ),

            inspectorExternalId:
                $reader->optionalReference(
                    'inspector_external_id',
                    [
                        'inspector_id',
                        'operator_id',
                        'employee_id',
                    ]
                ),

            inspectionType:
                $reader->requiredString(
                    'inspection_type',
                    [
                        'type',
                        'category',
                    ],
                    120
                ),

            result: $this->inspectionResult(
                $source,
                $reader->requiredString(
                    'result',
                    [
                        'inspection_result',
                        'status',
                    ],
                    50
                )
            ),

            inspectedAt:
                $reader->requiredDateTime(
                    'inspected_at',
                    [
                        'inspection_date',
                        'performed_at',
                    ]
                ),

            sampleSize: $sampleSize,
            passedQuantity: $passed,
            failedQuantity: $failed,

            notes: $reader->optionalString(
                'notes',
                [
                    'comments',
                    'description',
                ],
                5000
            ),
        );
    }

    private function mapNonconformity(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): NonconformityErpData {
        $detectedAt =
            $reader->requiredDateTime(
                'detected_at',
                [
                    'created_at',
                    'occurred_at',
                ]
            );

        $correctedAt =
            $reader->optionalDateTime(
                'corrected_at',
                [
                    'resolved_at',
                    'closed_at',
                ]
            );

        $this->assertChronology(
            $source,
            $detectedAt,
            $correctedAt,
            'detected_at',
            'corrected_at'
        );

        $status = $this->nonconformityStatus(
            $source,
            $reader->requiredString(
                'status',
                ['state'],
                50
            )
        );

        if (
            $status->isResolved()
            && $correctedAt === null
        ) {
            throw ErpMappingException::invalidField(
                $source,
                'corrected_at',
                'correction timestamp for a resolved nonconformity'
            );
        }

        return new NonconformityErpData(
            source: $source,

            nonconformityNumber:
                $reader->requiredString(
                    'nonconformity_number',
                    [
                        'nc_number',
                        'number',
                        'reference',
                    ],
                    120
                ),

            inspectionExternalId:
                $reader->requiredReference(
                    'inspection_external_id',
                    [
                        'inspection_id',
                        'quality_inspection_id',
                    ]
                ),

            batchExternalId:
                $reader->optionalReference(
                    'batch_external_id',
                    [
                        'batch_id',
                        'production_batch_id',
                    ]
                ),

            severity:
                $this->nonconformitySeverity(
                    $source,
                    $reader->requiredString(
                        'severity',
                        [
                            'level',
                            'priority',
                        ],
                        50
                    )
                ),

            status: $status,

            category:
                $reader->requiredString(
                    'category',
                    [
                        'nonconformity_type',
                        'type',
                    ],
                    120
                ),

            description:
                $reader->requiredString(
                    'description',
                    [
                        'problem',
                        'details',
                    ],
                    5000
                ),

            detectedAt: $detectedAt,
            correctedAt: $correctedAt,

            correctiveAction:
                $reader->optionalString(
                    'corrective_action',
                    [
                        'resolution',
                        'action_taken',
                    ],
                    5000
                ),
        );
    }

    private function mapFinishedLot(
        ErpSourceRecord $source,
        ErpPayloadReader $reader
    ): FinishedLotErpData {
        $status = $this->finishedLotStatus(
            $source,
            $reader->requiredString(
                'status',
                [
                    'release_status',
                    'state',
                ],
                50
            )
        );

        $produced =
            $reader->requiredDecimal(
                'produced_quantity',
                [
                    'quantity',
                    'total_quantity',
                ],
                3,
                0
            );

        $released =
            $reader->optionalDecimal(
                'released_quantity',
                [
                    'accepted_quantity',
                ],
                '0.000',
                3,
                0
            ) ?? '0.000';

        $rejected =
            $reader->optionalDecimal(
                'rejected_quantity',
                [
                    'blocked_quantity',
                    'failed_quantity',
                ],
                '0.000',
                3,
                0
            ) ?? '0.000';

        if (
            $this->quantityToMilliUnits($released)
            + $this->quantityToMilliUnits($rejected)
            > $this->quantityToMilliUnits($produced)
        ) {
            throw ErpMappingException::invalidField(
                $source,
                'produced_quantity',
                'quantity greater than or equal to released plus rejected quantities'
            );
        }

        $releasedAt =
            $reader->optionalDateTime(
                'released_at',
                [
                    'release_date',
                    'approved_at',
                ]
            );

        if (
            $status === ErpFinishedLotStatus::Released
            && $releasedAt === null
        ) {
            throw ErpMappingException::invalidField(
                $source,
                'released_at',
                'release timestamp for a released lot'
            );
        }

        $producedAt =
            $reader->requiredDateTime(
                'produced_at',
                [
                    'production_date',
                    'completed_at',
                ]
            );

        if ($releasedAt !== null) {
            $this->assertChronology(
                $source,
                $producedAt,
                $releasedAt,
                'produced_at',
                'released_at'
            );
        }

        return new FinishedLotErpData(
            source: $source,

            lotNumber:
                $reader->requiredString(
                    'lot_number',
                    [
                        'finished_lot_number',
                        'number',
                        'reference',
                    ],
                    120
                ),

            batchExternalId:
                $reader->requiredReference(
                    'batch_external_id',
                    [
                        'batch_id',
                        'production_batch_id',
                    ]
                ),

            productExternalId:
                $reader->requiredReference(
                    'product_external_id',
                    [
                        'product_id',
                        'article_id',
                    ]
                ),

            status: $status,
            producedAt: $producedAt,

            expiryDate:
                $reader->optionalDate(
                    'expiry_date',
                    [
                        'expiration_date',
                        'expires_at',
                    ]
                ),

            producedQuantity: $produced,
            releasedQuantity: $released,
            rejectedQuantity: $rejected,

            quantityUnit:
                $reader->requiredString(
                    'quantity_unit',
                    [
                        'unit',
                        'uom',
                    ],
                    30
                ),

            releasedAt: $releasedAt,

            releasedByExternalId:
                $reader->optionalReference(
                    'released_by_external_id',
                    [
                        'released_by',
                        'approved_by',
                    ]
                ),

            releaseNotes:
                $reader->optionalString(
                    'release_notes',
                    [
                        'notes',
                        'comments',
                    ],
                    5000
                ),
        );
    }

    private function orderStatus(
        ErpSourceRecord $source,
        string $value
    ): ProductionOrderStatus {
        return ProductionOrderStatus::from(
            $this->canonicalValue(
                $source,
                'status',
                $value,
                [
                    'draft' => 'draft',
                    'new' => 'draft',
                    'planned' => 'planned',
                    'scheduled' => 'planned',
                    'released' => 'released',
                    'approved' => 'released',
                    'open' => 'released',
                    'in_progress' => 'in_progress',
                    'running' => 'in_progress',
                    'started' => 'in_progress',
                    'completed' => 'completed',
                    'done' => 'completed',
                    'closed' => 'completed',
                    'cancelled' => 'cancelled',
                    'canceled' => 'cancelled',
                    'void' => 'cancelled',
                ]
            )
        );
    }

    private function batchStatus(
        ErpSourceRecord $source,
        string $value
    ): ProductionBatchStatus {
        return ProductionBatchStatus::from(
            $this->canonicalValue(
                $source,
                'status',
                $value,
                [
                    'planned' => 'planned',
                    'new' => 'planned',
                    'scheduled' => 'planned',
                    'ready' => 'ready',
                    'queued' => 'ready',
                    'in_progress' => 'in_progress',
                    'running' => 'in_progress',
                    'started' => 'in_progress',
                    'completed' => 'completed',
                    'done' => 'completed',
                    'closed' => 'completed',
                    'cancelled' => 'cancelled',
                    'canceled' => 'cancelled',
                ]
            )
        );
    }



    private function runLogType(
        ErpSourceRecord $source,
        string $value
    ): ErpRunLogType {
        return ErpRunLogType::from(
            $this->canonicalValue(
                $source,
                'log_type',
                $value,
                [
                    'production' => 'production',
                    'output' => 'production',
                    'setup' => 'setup',
                    'changeover' => 'setup',
                    'cleaning' => 'cleaning',
                    'sanitation' => 'cleaning',
                    'quality' => 'quality',
                    'inspection' => 'quality',
                    'maintenance' => 'maintenance',
                    'repair' => 'maintenance',
                    'comment' => 'comment',
                    'note' => 'comment',
                ]
            )
        );
    }

    private function eventSeverity(
        ErpSourceRecord $source,
        string $value
    ): ProductionEventSeverity {
        return ProductionEventSeverity::from(
            $this->canonicalValue(
                $source,
                'severity',
                $value,
                [
                    /*
                     * ProductionEventSeverity uses the backing value
                     * "information", not the abbreviated value "info".
                     */
                    'info' => 'information',
                    'information' => 'information',
                    'informational' => 'information',
                    'low' => 'information',
                    'normal' => 'information',
                    'warning' => 'warning',
                    'medium' => 'warning',
                    'moderate' => 'warning',
                    'critical' => 'critical',
                    'high' => 'critical',
                    'severe' => 'critical',
                    'emergency' => 'critical',
                ]
            )
        );
    }

    private function machineStatus(
        ErpSourceRecord $source,
        string $value
    ): ErpMachineStatus {
        return ErpMachineStatus::from(
            $this->canonicalValue(
                $source,
                'status',
                $value,
                [
                    'running' => 'running',
                    'operational' => 'running',
                    'producing' => 'running',
                    'production' => 'running',

                    'idle' => 'idle',
                    'waiting' => 'idle',
                    'standby' => 'idle',

                    'stopped' => 'stopped',
                    'halted' => 'stopped',
                    'paused' => 'stopped',
                    'on_hold' => 'stopped',
                    'blocked' => 'stopped',
                    'setup' => 'stopped',
                    'changeover' => 'stopped',
                    'cleaning' => 'stopped',
                    'quality_hold' => 'stopped',
                    'material_shortage' => 'stopped',
                    'utility_failure' => 'stopped',
                    'planned_downtime' => 'stopped',
                    'unplanned_downtime' => 'stopped',

                    'fault' => 'fault',
                    'failed' => 'fault',
                    'failure' => 'fault',
                    'error' => 'fault',
                    'breakdown' => 'fault',
                    'equipment_fault' => 'fault',

                    'maintenance' => 'maintenance',
                    'under_maintenance' => 'maintenance',
                    'preventive_maintenance' => 'maintenance',
                    'corrective_maintenance' => 'maintenance',
                    'service' => 'maintenance',

                    'offline' => 'offline',
                    'disconnected' => 'offline',
                    'shutdown' => 'offline',
                ]
            )
        );
    }

    private function maintenanceType(
        ErpSourceRecord $source,
        string $value
    ): ErpMaintenanceType {
        return ErpMaintenanceType::from(
            $this->canonicalValue(
                $source,
                'maintenance_type',
                $value,
                [
                    'preventive' => 'preventive',
                    'scheduled' => 'preventive',
                    'corrective' => 'corrective',
                    'repair' => 'corrective',
                    'inspection' => 'inspection',
                    'check' => 'inspection',
                    'calibration' => 'calibration',
                    'adjustment' => 'calibration',
                ]
            )
        );
    }

    private function maintenanceStatus(
        ErpSourceRecord $source,
        string $value
    ): ErpMaintenanceStatus {
        return ErpMaintenanceStatus::from(
            $this->canonicalValue(
                $source,
                'status',
                $value,
                [
                    'planned' => 'planned',
                    'scheduled' => 'planned',
                    'in_progress' => 'in_progress',
                    'running' => 'in_progress',
                    'started' => 'in_progress',
                    'completed' => 'completed',
                    'done' => 'completed',
                    'closed' => 'completed',
                    'cancelled' => 'cancelled',
                    'canceled' => 'cancelled',
                ]
            )
        );
    }

    private function inspectionResult(
        ErpSourceRecord $source,
        string $value
    ): ErpInspectionResult {
        return ErpInspectionResult::from(
            $this->canonicalValue(
                $source,
                'result',
                $value,
                [
                    'pending' => 'pending',
                    'awaiting' => 'pending',
                    'passed' => 'passed',
                    'pass' => 'passed',
                    'approved' => 'passed',
                    'failed' => 'failed',
                    'fail' => 'failed',
                    'rejected' => 'failed',
                    'conditional' => 'conditional',
                    'conditionally_accepted' => 'conditional',
                ]
            )
        );
    }

    private function nonconformitySeverity(
        ErpSourceRecord $source,
        string $value
    ): ErpNonconformitySeverity {
        return ErpNonconformitySeverity::from(
            $this->canonicalValue(
                $source,
                'severity',
                $value,
                [
                    'minor' => 'minor',
                    'low' => 'minor',
                    'major' => 'major',
                    'medium' => 'major',
                    'critical' => 'critical',
                    'high' => 'critical',
                    'severe' => 'critical',
                ]
            )
        );
    }

    private function nonconformityStatus(
        ErpSourceRecord $source,
        string $value
    ): ErpNonconformityStatus {
        return ErpNonconformityStatus::from(
            $this->canonicalValue(
                $source,
                'status',
                $value,
                [
                    'open' => 'open',
                    'new' => 'open',
                    'investigating' => 'investigating',
                    'in_progress' => 'investigating',
                    'corrected' => 'corrected',
                    'resolved' => 'corrected',
                    'closed' => 'closed',
                    'verified' => 'closed',
                ]
            )
        );
    }

    private function finishedLotStatus(
        ErpSourceRecord $source,
        string $value
    ): ErpFinishedLotStatus {
        return ErpFinishedLotStatus::from(
            $this->canonicalValue(
                $source,
                'status',
                $value,
                [
                    'pending' => 'pending',
                    'awaiting_release' => 'pending',
                    'released' => 'released',
                    'approved' => 'released',
                    'blocked' => 'blocked',
                    'quarantined' => 'blocked',
                    'rejected' => 'rejected',
                    'failed' => 'rejected',
                ]
            )
        );
    }

    /**
     * @param array<string, string> $mapping
     */
    private function canonicalValue(
        ErpSourceRecord $source,
        string $field,
        string $value,
        array $mapping
    ): string {
        $normalized = strtolower(
            trim($value)
        );

        $normalized = preg_replace(
            '/[\s-]+/',
            '_',
            $normalized
        ) ?? $normalized;

        if (
            ! array_key_exists(
                $normalized,
                $mapping
            )
        ) {
            throw ErpMappingException::invalidField(
                $source,
                $field,
                'supported normalized value'
            );
        }

        return $mapping[$normalized];
    }

    private function assertChronology(
        ErpSourceRecord $source,
        CarbonImmutable $start,
        ?CarbonImmutable $end,
        string $startField,
        string $endField
    ): void {
        if (
            $end !== null
            && $end->lessThan($start)
        ) {
            throw ErpMappingException::invalidChronology(
                $source,
                $startField,
                $endField
            );
        }
    }

    private function quantityToMilliUnits(
        string $quantity
    ): int {
        return (int) round(
            ((float) $quantity) * 1000
        );
    }
}