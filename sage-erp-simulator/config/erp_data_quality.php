<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data-quality simulation
    |--------------------------------------------------------------------------
    |
    | This feature modifies API responses only. It never modifies the
    | simulator database.
    |
    */

    'enabled' => env(
        'ERP_DQ_SIMULATION_ENABLED',
        true
    ),

    'default_seed' => (int) env(
        'ERP_DQ_DEFAULT_SEED',
        20260725
    ),

    'default_missing_rate' => (int) env(
        'ERP_DQ_DEFAULT_MISSING_RATE',
        15
    ),

    'default_duplicate_rate' => (int) env(
        'ERP_DQ_DEFAULT_DUPLICATE_RATE',
        10
    ),

    'maximum_rate' => (int) env(
        'ERP_DQ_MAXIMUM_RATE',
        100
    ),

    /*
    |--------------------------------------------------------------------------
    | Fields allowed to become null
    |--------------------------------------------------------------------------
    |
    | Identifiers such as external_id, product code, order number, batch
    | number, lot number, and event number are intentionally protected.
    |
    | Dot notation can target nested response values.
    |
    */

    'fields' => [
        'api/products' => [
            'name',
            'flavor',
            'beverage_type',
        ],

        'api/production-lines' => [
            'name',
            'description',
            'nominal_capacity_units_per_hour',
        ],

        'api/machines' => [
            'name',
            'manufacturer',
            'model_reference',
            'serial_number',
            'criticality',
            'installation_date',
        ],

        'api/shifts' => [
            'name',
            'start_time',
            'end_time',
        ],

        'api/operators' => [
            'first_name',
            'last_name',
            'skill_level',
        ],

        'api/production-orders' => [
            'planned_end_at',
            'priority',
            'notes',
        ],

        'api/production-batches' => [
            'actual_start_at',
            'actual_end_at',
            'operator_assignment',
            'expiry_date',
            'quality_status',
        ],

        'api/production-records' => [
            'target_quantity',
            'runtime_minutes',
            'downtime_minutes',
            'quality_rate_percent',
            'machine',
            'process_stage',
            'notes',
        ],

        'api/downtime-events' => [
            'reason_code',
            'reason_description',
            'production_impact_units',
            'shift',
            'production_batch',
            'maintenance_record',
        ],

        'api/machine-status-events' => [
            'duration_minutes',
            'notes',
            'shift',
        ],

        'api/maintenance-history' => [
            'failure_code',
            'failure_description',
            'root_cause',
            'actions_taken',
            'technician_name',
            'costs.parts_cost',
            'costs.labor_cost',
            'downtime_event',
        ],

        'api/quality-inspections' => [
            'inspector_name',
            'overall_score_percent',
            'nonconformity_code',
            'nonconformity_description',
            'corrective_action',
        ],

        'api/quality-test-results' => [
            'numeric_value',
            'text_value',
            'unit',
            'minimum_specification',
            'maximum_specification',
            'notes',
        ],

        'api/finished-lot-releases' => [
            'released_at',
            'released_by',
            'quality_certificate_number',
            'decision_reason',
        ],
    ],
];