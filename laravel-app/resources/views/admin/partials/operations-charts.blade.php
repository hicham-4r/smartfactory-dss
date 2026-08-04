@php
    $queueLabels = [];
    $queueValues = [];

    foreach (
        [
            'Ready' => $operations->queue['ready'] ?? null,
            'Reserved' => $operations->queue['reserved'] ?? null,
            'Delayed' => $operations->queue['delayed'] ?? null,
            'Failed' => $operations->queue['failed'] ?? null,
        ] as $label => $value
    ) {
        if ($value !== null) {
            $queueLabels[] = $label;
            $queueValues[] = (int) $value;
        }
    }

    $erpLabels = [];
    $erpValues = [];

    if ($operations->erpHealth !== null) {
        $erpLabels = [
            'Runs in window',
            'Failed runs',
            'Stale states',
            'Recent failures',
        ];

        $erpValues = [
            (int) (
                $operations
                    ->erpHealth
                    ->summary[
                        'runs_in_window'
                    ]
                ?? 0
            ),
            (int) (
                $operations
                    ->erpHealth
                    ->summary[
                        'failed_runs_in_window'
                    ]
                ?? 0
            ),
            (int) (
                $operations
                    ->erpHealth
                    ->summary[
                        'stale_states'
                    ]
                ?? 0
            ),
            count(
                $operations
                    ->erpHealth
                    ->failures
            ),
        ];
    }
@endphp

<div class="col-12">
    <section
        class="sf-chart-section mb-0"
        aria-labelledby="administrator-operations-charts-title"
    >
        <div class="mb-3">
            <p class="text-uppercase small fw-semibold text-secondary mb-1">
                Operations visualization
            </p>

            <h2
                id="administrator-operations-charts-title"
                class="h4 fw-bold mb-1"
            >
                Administrator operations charts
            </h2>

            <p class="text-muted-smartfactory mb-0">
                Sanitized account, workforce, queue and ERP health summaries.
            </p>
        </div>

        <div class="row g-4">
            @php
                $chartId =
                    'administrator-user-status';

                $chartTitle =
                    'User account status';

                $chartDescription =
                    'Active and inactive authenticated DSS accounts.';

                $chartConfig = [
                    'type' => 'donut',
                    'ariaLabel' =>
                        'Active and inactive user accounts',
                    'labels' => [
                        'Active',
                        'Inactive',
                    ],
                    'datasets' => [
                        [
                            'label' => 'Accounts',
                            'values' => [
                                $operations
                                    ->users['active'],
                                $operations
                                    ->users['inactive'],
                            ],
                            'colors' => [
                                '#0f766e',
                                '#64748b',
                            ],
                        ],
                    ],
                    'totalLabel' => 'Accounts',
                    'emptyMessage' =>
                        'No user account data is available.',
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

            @php
                $chartId =
                    'administrator-operator-readiness';

                $chartTitle =
                    'Operator readiness exceptions';

                $chartDescription =
                    'Active Operators needing account linkage or a current assignment.';

                $chartConfig = [
                    'type' => 'bar',
                    'ariaLabel' =>
                        'Operator account and assignment readiness exceptions',
                    'labels' => [
                        'Without account',
                        'Without assignment',
                        'Operator accounts unlinked',
                    ],
                    'datasets' => [
                        [
                            'label' => 'Items',
                            'values' => [
                                $operations
                                    ->operators[
                                        'active_without_account'
                                    ],
                                $operations
                                    ->operators[
                                        'active_without_current_assignment'
                                    ],
                                $operations
                                    ->users[
                                        'operator_accounts_without_profile'
                                    ],
                            ],
                            'color' => '#d97706',
                        ],
                    ],
                    'emptyMessage' =>
                        'No Operator readiness exception is detected.',
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

            @if ($queueLabels !== [])
                @php
                    $chartId =
                        'administrator-queue-state';

                    $chartTitle =
                        'Queue state';

                    $chartDescription =
                        'Sanitized counts only; payloads and exception traces are excluded.';

                    $chartConfig = [
                        'type' => 'bar',
                        'ariaLabel' =>
                            'Database queue job state',
                        'labels' =>
                            $queueLabels,
                        'datasets' => [
                            [
                                'label' => 'Jobs',
                                'values' =>
                                    $queueValues,
                                'color' => '#2563eb',
                            ],
                        ],
                        'emptyMessage' =>
                            'No queued or failed job is detected.',
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

            @if ($erpLabels !== [])
                @php
                    $chartId =
                        'administrator-erp-health';

                    $chartTitle =
                        'ERP synchronization indicators';

                    $chartDescription =
                        'Sanitized run, failure and stale-state counts from ERP monitoring.';

                    $chartConfig = [
                        'type' => 'bar',
                        'ariaLabel' =>
                            'ERP synchronization health indicators',
                        'labels' =>
                            $erpLabels,
                        'datasets' => [
                            [
                                'label' => 'Count',
                                'values' =>
                                    $erpValues,
                                'color' => '#7c3aed',
                            ],
                        ],
                        'emptyMessage' =>
                            'No ERP synchronization exception is detected in the monitoring window.',
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
</div>
