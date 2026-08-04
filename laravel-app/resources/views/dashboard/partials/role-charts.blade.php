@php
    $chartColorSets = [
        'production' => [
            '#164e63',
            '#14b8a6',
            '#dc2626',
        ],
        'attention' => [
            '#d97706',
            '#dc2626',
            '#7c3aed',
            '#64748b',
        ],
        'maintenance' => [
            '#2563eb',
            '#d97706',
            '#64748b',
        ],
    ];

    $chartSlug = static fn (
        string $value
    ): string => str($value)
        ->slug('-')
        ->toString();
@endphp

@if ($overview->operatorDashboard !== null)
    @php
        $operatorChart = $overview->operatorDashboard;
    @endphp

    @if (
        $operatorChart->profileLinked
        && $operatorChart->operatorActive
    )
        <section
            class="sf-chart-section"
            aria-labelledby="operator-personal-charts-title"
        >
            <div class="mb-3">
                <p class="text-uppercase small fw-semibold text-secondary mb-1">
                    Personal visual analysis
                </p>

                <h2
                    id="operator-personal-charts-title"
                    class="h4 fw-bold mb-1"
                >
                    Your production charts
                </h2>

                <p class="text-muted-smartfactory mb-0">
                    These charts contain only your own records and incidents.
                </p>
            </div>

            <div class="row g-4">
                @foreach (
                    $operatorChart->quantityUnits
                    as $unitIndex => $unit
                )
                    @php
                        $chartId = 'operator-output-'
                            .$unitIndex
                            .'-'
                            .$chartSlug(
                                $unit->quantityUnit
                            );

                        $chartTitle = 'Personal output — '
                            .$unit->quantityUnit;

                        $chartDescription =
                            'Produced, good and rejected quantities remain separated within this unit.';

                        $chartConfig = [
                            'type' => 'bar',
                            'ariaLabel' =>
                                'Personal production output in '
                                .$unit->quantityUnit,
                            'labels' => [
                                'Produced',
                                'Good',
                                'Rejected',
                            ],
                            'datasets' => [
                                [
                                    'label' => 'Quantity',
                                    'values' => [
                                        (float) $unit
                                            ->producedQuantity,
                                        (float) $unit
                                            ->goodQuantity,
                                        (float) $unit
                                            ->rejectedQuantity,
                                    ],
                                    'color' => '#0f766e',
                                ],
                            ],
                            'valueSuffix' =>
                                ' '.$unit->quantityUnit,
                            'emptyMessage' =>
                                'No personal production quantity is available for this unit.',
                        ];
                    @endphp

                    @include(
                        'dashboard.partials.chart-card',
                        [
                            'chartId' => $chartId,
                            'chartTitle' => $chartTitle,
                            'chartDescription' =>
                                $chartDescription,
                            'chartConfig' => $chartConfig,
                            'columnClass' =>
                                'col-12 col-xl-6',
                        ]
                    )
                @endforeach

                @php
                    $chartId =
                        'operator-runtime-downtime';

                    $chartTitle =
                        'Personal runtime and downtime';

                    $chartDescription =
                        'Time totals use only your records in the selected period.';

                    $chartConfig = [
                        'type' => 'donut',
                        'ariaLabel' =>
                            'Personal runtime and downtime in minutes',
                        'labels' => [
                            'Runtime',
                            'Downtime',
                        ],
                        'datasets' => [
                            [
                                'label' => 'Minutes',
                                'values' => [
                                    $operatorChart
                                        ->runtimeMinutes,
                                    $operatorChart
                                        ->downtimeMinutes,
                                ],
                                'colors' => [
                                    '#0f766e',
                                    '#dc2626',
                                ],
                            ],
                        ],
                        'valueSuffix' => ' min',
                        'totalLabel' => 'Observed time',
                        'emptyMessage' =>
                            'No personal runtime or downtime is available for the selected period.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            </div>
        </section>
    @endif
@endif

@if ($overview->productionSupervisor !== null)
    @php
        $supervisorChart =
            $overview->productionSupervisor;

        $supervisorLineGroups = collect(
            $supervisorChart
                ->breakdowns
                ->byProductionLine
        )->groupBy(
            static fn ($row): string =>
                $row->quantityUnit
        );

        $supervisorShiftGroups = collect(
            $supervisorChart
                ->breakdowns
                ->byShift
        )->groupBy(
            static fn ($row): string =>
                $row->quantityUnit
        );
    @endphp

    <section
        class="sf-chart-section"
        aria-labelledby="supervisor-operational-charts-title"
    >
        <div class="mb-3">
            <p class="text-uppercase small fw-semibold text-secondary mb-1">
                Operational visualization
            </p>

            <h2
                id="supervisor-operational-charts-title"
                class="h4 fw-bold mb-1"
            >
                Production Supervisor charts
            </h2>

            <p class="text-muted-smartfactory mb-0">
                Current execution and action workload for the selected scope.
            </p>
        </div>

        <div class="row g-4">
            @php
                $chartId =
                    'supervisor-action-workload';

                $chartTitle =
                    'Current action workload';

                $chartDescription =
                    'Validation and event queues requiring supervisor attention.';

                $chartConfig = [
                    'type' => 'donut',
                    'ariaLabel' =>
                        'Production Supervisor action workload',
                    'labels' => [
                        'Pending validations',
                        'Unresolved events',
                        'Critical events',
                    ],
                    'datasets' => [
                        [
                            'label' => 'Items',
                            'values' => [
                                $supervisorChart
                                    ->pendingValidationCount,
                                $supervisorChart
                                    ->unresolvedEventCount,
                                $supervisorChart
                                    ->criticalEventCount,
                            ],
                            'colors' =>
                                $chartColorSets[
                                    'attention'
                                ],
                        ],
                    ],
                    'totalLabel' => 'Open items',
                    'emptyMessage' =>
                        'No open supervisor workload is currently detected.',
                ];
            @endphp

            @include(
                'dashboard.partials.chart-card',
                [
                    'chartId' => $chartId,
                    'chartTitle' => $chartTitle,
                    'chartDescription' =>
                        $chartDescription,
                    'chartConfig' => $chartConfig,
                    'columnClass' =>
                        'col-12 col-xl-6',
                ]
            )

            @foreach (
                $supervisorLineGroups
                as $unit => $rows
            )
                @php
                    $chartId = 'supervisor-lines-'
                        .$chartSlug((string) $unit);

                    $chartTitle =
                        'Production by line — '.$unit;

                    $chartDescription =
                        'Target and actual output are compared only within the same quantity unit.';

                    $chartConfig = [
                        'type' => 'horizontal-bar',
                        'ariaLabel' =>
                            'Production target and actual output by production line in '
                            .$unit,
                        'labels' =>
                            $rows
                                ->map(
                                    static fn ($row): string =>
                                        $row->label
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Target',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->targetQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][0],
                            ],
                            [
                                'label' => 'Actual',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->actualQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][1],
                            ],
                        ],
                        'valueSuffix' => ' '.$unit,
                        'emptyMessage' =>
                            'No line-level production is available for this unit.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endforeach

            @foreach (
                $supervisorShiftGroups
                as $unit => $rows
            )
                @php
                    $chartId = 'supervisor-shifts-'
                        .$chartSlug((string) $unit);

                    $chartTitle =
                        'Production by shift — '.$unit;

                    $chartDescription =
                        'Shift target values follow the existing deterministic batch denominator.';

                    $chartConfig = [
                        'type' => 'grouped-bar',
                        'ariaLabel' =>
                            'Production target and actual output by shift in '
                            .$unit,
                        'labels' =>
                            $rows
                                ->map(
                                    static fn ($row): string =>
                                        $row->label
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Target',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->targetQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][0],
                            ],
                            [
                                'label' => 'Actual',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->actualQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][1],
                            ],
                        ],
                        'valueSuffix' => ' '.$unit,
                        'emptyMessage' =>
                            'No shift-level production is available for this unit.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endforeach
        </div>
    </section>
@endif

@if ($overview->productionManager !== null)
    @php
        $managerChart =
            $overview->productionManager;

        $managerMonthlyGroups = collect(
            $managerChart
                ->breakdowns
                ->monthlyTrend
        )->groupBy(
            static fn ($row): string =>
                $row->quantityUnit
        );

        $managerLineGroups = collect(
            $managerChart
                ->breakdowns
                ->byProductionLine
        )->groupBy(
            static fn ($row): string =>
                $row->quantityUnit
        );

        $managerFamilyGroups = collect(
            $managerChart
                ->breakdowns
                ->byProductFamily
        )->groupBy(
            static fn ($row): string =>
                $row->quantityUnit
        );
    @endphp

    <section
        class="sf-chart-section"
        aria-labelledby="manager-executive-charts-title"
    >
        <div class="mb-3">
            <p class="text-uppercase small fw-semibold text-secondary mb-1">
                Executive visualization
            </p>

            <h2
                id="manager-executive-charts-title"
                class="h4 fw-bold mb-1"
            >
                Production Manager charts
            </h2>

            <p class="text-muted-smartfactory mb-0">
                Strategic trends and comparisons using the validated KPI services.
            </p>
        </div>

        <div class="row g-4">
            @foreach (
                $managerMonthlyGroups
                as $unit => $rows
            )
                @php
                    $chartId = 'manager-monthly-'
                        .$chartSlug((string) $unit);

                    $chartTitle =
                        'Monthly production trend — '.$unit;

                    $chartDescription =
                        'Target and actual monthly quantities remain separated by unit.';

                    $chartConfig = [
                        'type' => 'line',
                        'ariaLabel' =>
                            'Monthly target and actual production in '
                            .$unit,
                        'labels' =>
                            $rows
                                ->map(
                                    static fn ($row): string =>
                                        $row->label
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Target',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->targetQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][0],
                            ],
                            [
                                'label' => 'Actual',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->actualQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][1],
                            ],
                        ],
                        'valueSuffix' => ' '.$unit,
                        'emptyMessage' =>
                            'No monthly production trend is available for this unit.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endforeach

            @foreach (
                $managerLineGroups
                as $unit => $rows
            )
                @php
                    $chartId = 'manager-lines-'
                        .$chartSlug((string) $unit);

                    $chartTitle =
                        'Production-line comparison — '.$unit;

                    $chartDescription =
                        'Actual output and rejected output by production line.';

                    $chartConfig = [
                        'type' => 'horizontal-bar',
                        'ariaLabel' =>
                            'Actual and rejected production by line in '
                            .$unit,
                        'labels' =>
                            $rows
                                ->map(
                                    static fn ($row): string =>
                                        $row->label
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Actual',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->actualQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][1],
                            ],
                            [
                                'label' => 'Rejected',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->rejectedQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][2],
                            ],
                        ],
                        'valueSuffix' => ' '.$unit,
                        'emptyMessage' =>
                            'No line comparison is available for this unit.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endforeach

            @foreach (
                $managerFamilyGroups
                as $unit => $rows
            )
                @php
                    $chartId = 'manager-families-'
                        .$chartSlug((string) $unit);

                    $chartTitle =
                        'Product-family output — '.$unit;

                    $chartDescription =
                        'Actual production by product family within one quantity unit.';

                    $chartConfig = [
                        'type' => 'horizontal-bar',
                        'ariaLabel' =>
                            'Actual production by product family in '
                            .$unit,
                        'labels' =>
                            $rows
                                ->map(
                                    static fn ($row): string =>
                                        $row->label
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Actual',
                                'values' =>
                                    $rows
                                        ->map(
                                            static fn ($row): float =>
                                                (float) $row
                                                    ->actualQuantity
                                        )
                                        ->values()
                                        ->all(),
                                'color' =>
                                    $chartColorSets[
                                        'production'
                                    ][0],
                            ],
                        ],
                        'valueSuffix' => ' '.$unit,
                        'emptyMessage' =>
                            'No product-family output is available for this unit.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endforeach

            @php
                $chartId =
                    'manager-quality-risks';

                $chartTitle =
                    'Quality risk indicators';

                $chartDescription =
                    'Exception counts reuse the synchronized quality summary.';

                $chartConfig = [
                    'type' => 'donut',
                    'ariaLabel' =>
                        'Production quality risk indicators',
                    'labels' => [
                        'Failed inspections',
                        'Blocked lots',
                        'Rejected lots',
                        'Critical nonconformities',
                    ],
                    'datasets' => [
                        [
                            'label' => 'Items',
                            'values' => [
                                $managerChart
                                    ->quality
                                    ->failedInspectionCount,
                                $managerChart
                                    ->quality
                                    ->blockedLotCount,
                                $managerChart
                                    ->quality
                                    ->rejectedLotCount,
                                $managerChart
                                    ->quality
                                    ->criticalNonconformityCount,
                            ],
                            'colors' =>
                                $chartColorSets[
                                    'attention'
                                ],
                        ],
                    ],
                    'totalLabel' => 'Risk items',
                    'emptyMessage' =>
                        'No quality risk indicator is currently detected.',
                ];
            @endphp

            @include(
                'dashboard.partials.chart-card',
                [
                    'chartId' => $chartId,
                    'chartTitle' => $chartTitle,
                    'chartDescription' =>
                        $chartDescription,
                    'chartConfig' => $chartConfig,
                    'columnClass' =>
                        'col-12 col-xl-6',
                ]
            )
        </div>
    </section>
@endif

@if ($overview->maintenanceManager !== null)
    @php
        $maintenanceChart =
            $overview->maintenanceManager
                ->maintenance;

        $maintenanceMachines = collect(
            $maintenanceChart->machines
        )->take(8);

        $maintenanceAvailability = collect(
            $maintenanceChart->machines
        )->filter(
            static fn ($machine): bool =>
                $machine
                    ->availabilityPercentage
                !== null
        )->take(8);
    @endphp

    <section
        class="sf-chart-section"
        aria-labelledby="maintenance-operational-charts-title"
    >
        <div class="mb-3">
            <p class="text-uppercase small fw-semibold text-secondary mb-1">
                Maintenance visualization
            </p>

            <h2
                id="maintenance-operational-charts-title"
                class="h4 fw-bold mb-1"
            >
                Maintenance Manager charts
            </h2>

            <p class="text-muted-smartfactory mb-0">
                Downtime, availability, failure and intervention comparisons.
            </p>
        </div>

        <div class="row g-4">
            @php
                $chartId =
                    'maintenance-downtime-composition';

                $chartTitle =
                    'Downtime composition';

                $chartDescription =
                    'Planned, unplanned and unclassified downtime in minutes.';

                $chartConfig = [
                    'type' => 'donut',
                    'ariaLabel' =>
                        'Maintenance downtime composition',
                    'labels' => [
                        'Planned',
                        'Unplanned',
                        'Unclassified',
                    ],
                    'datasets' => [
                        [
                            'label' => 'Minutes',
                            'values' => [
                                $maintenanceChart
                                    ->plannedDowntimeMinutes,
                                $maintenanceChart
                                    ->unplannedDowntimeMinutes,
                                $maintenanceChart
                                    ->unclassifiedDowntimeMinutes,
                            ],
                            'colors' =>
                                $chartColorSets[
                                    'maintenance'
                                ],
                        ],
                    ],
                    'valueSuffix' => ' min',
                    'totalLabel' => 'Downtime',
                    'emptyMessage' =>
                        'No downtime is available for the selected period.',
                ];
            @endphp

            @include(
                'dashboard.partials.chart-card',
                [
                    'chartId' => $chartId,
                    'chartTitle' => $chartTitle,
                    'chartDescription' =>
                        $chartDescription,
                    'chartConfig' => $chartConfig,
                    'columnClass' =>
                        'col-12 col-xl-6',
                ]
            )

            @if ($maintenanceMachines->isNotEmpty())
                @php
                    $chartId =
                        'maintenance-machine-downtime';

                    $chartTitle =
                        'Highest machine downtime';

                    $chartDescription =
                        'Up to eight machines ordered by the existing maintenance metric set.';

                    $chartConfig = [
                        'type' => 'horizontal-bar',
                        'ariaLabel' =>
                            'Total downtime by machine in minutes',
                        'labels' =>
                            $maintenanceMachines
                                ->map(
                                    static fn ($machine): string =>
                                        $machine->machineName
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Downtime',
                                'values' =>
                                    $maintenanceMachines
                                        ->map(
                                            static fn ($machine): int =>
                                                $machine
                                                    ->totalDowntimeMinutes
                                        )
                                        ->values()
                                        ->all(),
                                'color' => '#dc2626',
                            ],
                        ],
                        'valueSuffix' => ' min',
                        'emptyMessage' =>
                            'No machine downtime is available.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endif

            @if ($maintenanceAvailability->isNotEmpty())
                @php
                    $chartId =
                        'maintenance-machine-availability';

                    $chartTitle =
                        'Observed machine availability';

                    $chartDescription =
                        'Availability uses observed machine-state coverage and is not OEE.';

                    $chartConfig = [
                        'type' => 'horizontal-bar',
                        'ariaLabel' =>
                            'Observed availability percentage by machine',
                        'labels' =>
                            $maintenanceAvailability
                                ->map(
                                    static fn ($machine): string =>
                                        $machine->machineName
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Availability',
                                'values' =>
                                    $maintenanceAvailability
                                        ->map(
                                            static fn ($machine): float =>
                                                (float) $machine
                                                    ->availabilityPercentage
                                        )
                                        ->values()
                                        ->all(),
                                'color' => '#0f766e',
                            ],
                        ],
                        'valueSuffix' => '%',
                        'emptyMessage' =>
                            'No observed availability percentage is available.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endif

            @if ($maintenanceChart->maintenanceTypes !== [])
                @php
                    $maintenanceTypes = collect(
                        $maintenanceChart
                            ->maintenanceTypes
                    );

                    $chartId =
                        'maintenance-intervention-types';

                    $chartTitle =
                        'Maintenance intervention mix';

                    $chartDescription =
                        'Preventive and corrective intervention counts by maintenance type.';

                    $chartConfig = [
                        'type' => 'grouped-bar',
                        'ariaLabel' =>
                            'Maintenance interventions by type',
                        'labels' =>
                            $maintenanceTypes
                                ->map(
                                    static fn ($type): string =>
                                        $type->label
                                )
                                ->values()
                                ->all(),
                        'datasets' => [
                            [
                                'label' => 'Planned',
                                'values' =>
                                    $maintenanceTypes
                                        ->map(
                                            static fn ($type): int =>
                                                $type->plannedCount
                                        )
                                        ->values()
                                        ->all(),
                                'color' => '#2563eb',
                            ],
                            [
                                'label' => 'In progress',
                                'values' =>
                                    $maintenanceTypes
                                        ->map(
                                            static fn ($type): int =>
                                                $type->inProgressCount
                                        )
                                        ->values()
                                        ->all(),
                                'color' => '#d97706',
                            ],
                            [
                                'label' => 'Completed',
                                'values' =>
                                    $maintenanceTypes
                                        ->map(
                                            static fn ($type): int =>
                                                $type->completedCount
                                        )
                                        ->values()
                                        ->all(),
                                'color' => '#0f766e',
                            ],
                        ],
                        'emptyMessage' =>
                            'No maintenance intervention is available.',
                    ];
                @endphp

                @include(
                    'dashboard.partials.chart-card',
                    [
                        'chartId' => $chartId,
                        'chartTitle' => $chartTitle,
                        'chartDescription' =>
                            $chartDescription,
                        'chartConfig' => $chartConfig,
                        'columnClass' =>
                            'col-12 col-xl-6',
                    ]
                )
            @endif
        </div>
    </section>
@endif
