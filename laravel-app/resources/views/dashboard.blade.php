@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- STEP21O_AI_NAVIGATION_START --}}
    @include('ai.insights.partials.navigation-card', ['context' => 'dashboard'])
    {{-- STEP21O_AI_NAVIGATION_END --}}
    @php
        $duration = static function (
            int|float|null $minutes
        ): string {
            if ($minutes === null) {
                return 'N/A';
            }

            $minutes = max(
                0,
                (int) round($minutes)
            );

            $hours = intdiv(
                $minutes,
                60
            );

            $remaining = $minutes % 60;

            if ($hours === 0) {
                return $remaining.' min';
            }

            return $hours
                .' h '
                .$remaining
                .' min';
        };

        $percentage = static fn (
            float|int|null $value
        ): string => $value === null
            ? 'N/A'
            : number_format(
                (float) $value,
                2
            ).'%';

        $productionDashboardFilterSnapshot =
            $overview->productionSupervisor
            ?? $overview->productionManager;

        $maintenanceDashboardFilterSnapshot =
            $overview->maintenanceManager;

        $toneClasses = [
            'primary' => 'text-bg-primary',
            'success' => 'text-bg-success',
            'info' => 'text-bg-info',
            'warning' => 'text-bg-warning',
            'dark' => 'text-bg-dark',
            'secondary' => 'text-bg-secondary',
        ];
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-2">
                Shared role-aware overview
            </p>

            <h1 class="h3 fw-bold mb-1">
                Welcome, {{ auth()->user()->name }}
            </h1>

            <p class="text-muted-smartfactory mb-0">
                {{ $overview->roleLabel() }} dashboard
            </p>
        </div>

        <span class="badge text-bg-secondary fs-6">
            Simulated ERP prototype
        </span>
    </div>

    <div class="alert alert-info" role="note">
        <strong>Data basis:</strong>
        {{ $overview->dataBasisLabel() }}
    </div>

    <div class="app-card bg-white mb-4">
        <div class="border-bottom px-4 py-3 fw-semibold">
            Dashboard period
        </div>

        <div class="p-4">
            <form
                method="GET"
                action="{{ route('dashboard') }}"
                class="row g-3 align-items-end"
                id="dashboard-filter-form"
            >
                <div class="col-md-4 col-xl-3">
                    <label
                        for="start_date"
                        class="form-label"
                    >
                        Start date
                    </label>

                    <input
                        id="start_date"
                        name="start_date"
                        type="date"
                        class="form-control"
                        value="{{ $filter->startDateString() }}"
                        required
                    >
                </div>

                <div class="col-md-4 col-xl-3">
                    <label
                        for="end_date"
                        class="form-label"
                    >
                        End date
                    </label>

                    <input
                        id="end_date"
                        name="end_date"
                        type="date"
                        class="form-control"
                        value="{{ $filter->endDateString() }}"
                        required
                    >
                </div>

                <div class="col-md-4 col-xl-3">
                    <label
                        for="timezone"
                        class="form-label"
                    >
                        Timezone
                    </label>

                    <select
                        id="timezone"
                        name="timezone"
                        class="form-select"
                        required
                    >
                        @foreach ($timezoneOptions as $timezone)
                            <option
                                value="{{ $timezone }}"
                                @selected(
                                    $filter->timezone
                                    === $timezone
                                )
                            >
                                {{ $timezone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($productionDashboardFilterSnapshot !== null)
                    <div class="col-md-4 col-xl-3">
                        <label
                            for="production_line_id"
                            class="form-label"
                        >
                            Production line
                        </label>

                        <select
                            id="production_line_id"
                            name="production_line_id"
                            class="form-select"
                            data-filter-key="production_line_id"
                        >
                            <option value="">All lines</option>

                            @foreach (
                                $productionDashboardFilterSnapshot
                                    ->productionLines
                                as $option
                            )
                                <option
                                    value="{{ $option->id }}"
                                    data-filter-value="{{
                                        $option->filterValue
                                    }}"
                                    @selected(
                                        $filter->productionLineId
                                        === $option->id
                                    )
                                >
                                    {{ $option->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 col-xl-3">
                        <label
                            for="product_id"
                            class="form-label"
                        >
                            Product
                        </label>

                        <select
                            id="product_id"
                            name="product_id"
                            class="form-select"
                            data-filter-key="product_id"
                        >
                            <option value="">All products</option>

                            @foreach (
                                $productionDashboardFilterSnapshot
                                    ->products
                                as $option
                            )
                                <option
                                    value="{{ $option->id }}"
                                    data-filter-value="{{
                                        $option->filterValue
                                    }}"
                                    @selected(
                                        $filter->productId
                                        === $option->id
                                    )
                                >
                                    {{ $option->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 col-xl-3">
                        <label
                            for="shift_id"
                            class="form-label"
                        >
                            Shift
                        </label>

                        <select
                            id="shift_id"
                            name="shift_id"
                            class="form-select"
                            data-filter-key="shift_key"
                        >
                            <option value="">All shifts</option>

                            @foreach (
                                $productionDashboardFilterSnapshot
                                    ->shifts
                                as $option
                            )
                                <option
                                    value="{{ $option->id }}"
                                    data-filter-value="{{
                                        $option->filterValue
                                    }}"
                                    @selected(
                                        $filter->shiftId
                                        === $option->id
                                    )
                                >
                                    {{ $option->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 col-xl-3">
                        <label
                            for="status"
                            class="form-label"
                        >
                            Execution status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                        >
                            <option value="">
                                All execution statuses
                            </option>

                            <option
                                value="in_progress"
                                @selected(
                                    $filter->status
                                    === 'in_progress'
                                )
                            >
                                In progress
                            </option>

                            <option
                                value="completed"
                                @selected(
                                    $filter->status
                                    === 'completed'
                                )
                            >
                                Completed
                            </option>
                        </select>
                    </div>
                @elseif ($maintenanceDashboardFilterSnapshot !== null)
                    <div class="col-md-4 col-xl-3">
                        <label
                            for="production_line_id"
                            class="form-label"
                        >
                            Production line
                        </label>

                        <select
                            id="production_line_id"
                            name="production_line_id"
                            class="form-select"
                        >
                            <option value="">All lines</option>

                            @foreach (
                                $maintenanceDashboardFilterSnapshot
                                    ->productionLines
                                as $option
                            )
                                <option
                                    value="{{ $option->id }}"
                                    @selected(
                                        $filter->productionLineId
                                        === $option->id
                                    )
                                >
                                    {{ $option->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 col-xl-3">
                        <label
                            for="machine_id"
                            class="form-label"
                        >
                            Machine
                        </label>

                        <select
                            id="machine_id"
                            name="machine_id"
                            class="form-select"
                        >
                            <option value="">All machines</option>

                            @foreach (
                                $maintenanceDashboardFilterSnapshot
                                    ->machines
                                as $option
                            )
                                <option
                                    value="{{ $option->id }}"
                                    data-production-line-id="{{
                                        $option->productionLineId
                                    }}"
                                    @selected(
                                        $filter->machineId
                                        === $option->id
                                    )
                                >
                                    {{ $option->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 col-xl-3">
                        <label
                            for="maintenance_type"
                            class="form-label"
                        >
                            Maintenance type
                        </label>

                        <select
                            id="maintenance_type"
                            name="maintenance_type"
                            class="form-select"
                        >
                            <option value="">
                                All maintenance types
                            </option>

                            @foreach (
                                \App\Enums\ERP\ErpMaintenanceType::cases()
                                as $type
                            )
                                <option
                                    value="{{ $type->value }}"
                                    @selected(
                                        $filter->maintenanceType
                                        === $type->value
                                    )
                                >
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 col-xl-3">
                        <label
                            for="downtime_category"
                            class="form-label"
                        >
                            Downtime category
                        </label>

                        <select
                            id="downtime_category"
                            name="downtime_category"
                            class="form-select"
                        >
                            <option value="">All downtime</option>

                            <option
                                value="planned"
                                @selected(
                                    $filter->downtimeCategory
                                    === 'planned'
                                )
                            >
                                Planned
                            </option>

                            <option
                                value="unplanned"
                                @selected(
                                    $filter->downtimeCategory
                                    === 'unplanned'
                                )
                            >
                                Unplanned
                            </option>
                        </select>
                    </div>
                @endif

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply filters
                    </button>

                    <a
                        href="{{ route('dashboard') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if ($overview->operatorDashboard !== null)
        @include(
            'dashboard.partials.operator',
            ['snapshot' => $overview->operatorDashboard]
        )
    @endif

    @if ($overview->productionSupervisor !== null)
        @include(
            'dashboard.partials.production-supervisor',
            ['snapshot' => $overview->productionSupervisor]
        )
    @endif

    @if ($overview->productionManager !== null)
        @include(
            'dashboard.partials.production-manager',
            ['snapshot' => $overview->productionManager]
        )
    @endif

    @if ($overview->maintenanceManager !== null)
        @include(
            'dashboard.partials.maintenance-manager',
            ['snapshot' => $overview->maintenanceManager]
        )
    @endif

    @include(
        'dashboard.partials.role-charts',
        ['overview' => $overview]
    )

    @if ($overview->modules !== [])
        <div class="row g-4 mb-4">
            @foreach ($overview->modules as $module)
                <div class="col-md-6 col-xl-4">
                    <a
                        href="{{
                            route(
                                $module->routeName,
                                $module->query
                            )
                        }}"
                        class="text-decoration-none text-dark"
                    >
                        <div class="app-card bg-white h-100 p-4">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <p class="text-uppercase small fw-semibold text-secondary mb-1">
                                        {{ $module->eyebrow }}
                                    </p>

                                    <h2 class="h5 fw-bold mb-0">
                                        {{ $module->title }}
                                    </h2>
                                </div>

                                <span
                                    class="badge {{
                                        $toneClasses[
                                            $module->tone
                                        ]
                                        ?? $toneClasses[
                                            'secondary'
                                        ]
                                    }}"
                                >
                                    Open
                                </span>
                            </div>

                            <p class="text-muted-smartfactory mb-0">
                                {{ $module->description }}
                            </p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-warning" role="status">
            No dashboard module is assigned to the current account.
            Ask an administrator to verify the account role and permissions.
        </div>
    @endif

    @if (
        $overview->production !== null
        && $overview->productionSupervisor === null
        && $overview->productionManager === null
    )
        <section class="mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <p class="text-uppercase small fw-semibold text-secondary mb-1">
                        Production
                    </p>

                    <h2 class="h4 fw-bold mb-0">
                        Production snapshot
                    </h2>
                </div>

                @if ($overview->production->isProvisional)
                    <span class="badge text-bg-warning">
                        Includes provisional records
                    </span>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Execution records
                        </div>

                        <div class="display-6">
                            {{
                                number_format(
                                    $overview
                                        ->production
                                        ->recordCount
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            {{
                                number_format(
                                    $overview
                                        ->production
                                        ->validatedRecordCount
                                )
                            }} validated /
                            {{
                                number_format(
                                    $overview
                                        ->production
                                        ->provisionalRecordCount
                                )
                            }} provisional
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Target orders
                        </div>

                        <div class="display-6">
                            {{
                                number_format(
                                    $overview
                                        ->production
                                        ->targetOrderCount
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            Execution statuses only
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Runtime
                        </div>

                        <div class="display-6">
                            {{
                                $duration(
                                    $overview
                                        ->production
                                        ->runtimeMinutes
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            Eligible production records
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Production downtime
                        </div>

                        <div class="display-6">
                            {{
                                $duration(
                                    $overview
                                        ->production
                                        ->downtimeMinutes
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            Recorded during execution
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (
        $overview->maintenance !== null
        && $overview->maintenanceManager === null
    )
        <section class="mb-4">
            <div class="mb-3">
                <p class="text-uppercase small fw-semibold text-secondary mb-1">
                    Maintenance
                </p>

                <h2 class="h4 fw-bold mb-0">
                    Maintenance snapshot
                </h2>
            </div>

            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Total downtime
                        </div>

                        <div class="display-6">
                            {{
                                $duration(
                                    $overview
                                        ->maintenance
                                        ->totalDowntimeMinutes
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            {{
                                number_format(
                                    $overview
                                        ->maintenance
                                        ->downtimeEventCount
                                )
                            }} events
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Observed availability
                        </div>

                        <div class="display-6">
                            {{
                                $percentage(
                                    $overview
                                        ->maintenance
                                        ->availabilityPercentage
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            Based on observed machine states
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Interventions
                        </div>

                        <div class="display-6">
                            {{
                                number_format(
                                    $overview
                                        ->maintenance
                                        ->maintenanceInterventionCount
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            Preventive and corrective
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Recognized failures
                        </div>

                        <div class="display-6">
                            {{
                                number_format(
                                    $overview
                                        ->maintenance
                                        ->faultEventCount
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            {{
                                number_format(
                                    $overview
                                        ->maintenance
                                        ->repeatedFailureMachineCount
                                )
                            }} repeated-failure machines
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (
        $overview->quality !== null
        && $overview->productionSupervisor === null
        && $overview->productionManager === null
    )
        <section class="mb-4">
            <div class="mb-3">
                <p class="text-uppercase small fw-semibold text-secondary mb-1">
                    Quality
                </p>

                <h2 class="h4 fw-bold mb-0">
                    Quality snapshot
                </h2>
            </div>

            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Inspections
                        </div>

                        <div class="display-6">
                            {{
                                number_format(
                                    $overview
                                        ->quality
                                        ->inspectionCount
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            {{
                                number_format(
                                    $overview
                                        ->quality
                                        ->passedInspectionCount
                                )
                            }} passed /
                            {{
                                number_format(
                                    $overview
                                        ->quality
                                        ->failedInspectionCount
                                )
                            }} failed
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Inspection pass rate
                        </div>

                        <div class="display-6">
                            {{
                                $percentage(
                                    $overview
                                        ->quality
                                        ->inspectionPassPercentage
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            Inspection-level result
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Finished lots
                        </div>

                        <div class="display-6">
                            {{
                                number_format(
                                    $overview
                                        ->quality
                                        ->lotCount
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            {{
                                number_format(
                                    $overview
                                        ->quality
                                        ->releasedLotCount
                                )
                            }} released
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="app-card bg-white h-100 p-4">
                        <div class="text-muted-smartfactory">
                            Nonconformities
                        </div>

                        <div class="display-6">
                            {{
                                number_format(
                                    $overview
                                        ->quality
                                        ->nonconformityCount
                                )
                            }}
                        </div>

                        <div class="small text-muted-smartfactory">
                            {{
                                number_format(
                                    $overview
                                        ->quality
                                        ->criticalNonconformityCount
                                )
                            }} critical
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (! $overview->hasAnySnapshot())
        <div class="alert alert-secondary" role="status">
            This role does not expose cross-domain KPI snapshots.
            Use the assigned workspace above for operational tasks.
        </div>
    @endif

    @if ($productionDashboardFilterSnapshot !== null)
        <script>
            (() => {
                const rows = @json(
                    $productionDashboardFilterSnapshot
                        ->compatibilityRows
                );

                const fields = {
                    production_line_id:
                        document.getElementById(
                            'production_line_id'
                        ),
                    product_id:
                        document.getElementById(
                            'product_id'
                        ),
                    shift_key:
                        document.getElementById(
                            'shift_id'
                        ),
                };

                const selectedValue = (key) => {
                    const select = fields[key];

                    if (!select || select.value === '') {
                        return null;
                    }

                    const option =
                        select.options[
                            select.selectedIndex
                        ];

                    return option.dataset.filterValue
                        ?? option.value;
                };

                const currentSelections = () => ({
                    production_line_id:
                        selectedValue(
                            'production_line_id'
                        ),
                    product_id:
                        selectedValue('product_id'),
                    shift_key:
                        selectedValue('shift_key'),
                });

                const rowMatches = (
                    row,
                    selections,
                    ignoredKey = null
                ) => Object.entries(
                    selections
                ).every(([key, value]) => {
                    if (
                        key === ignoredKey
                        || value === null
                    ) {
                        return true;
                    }

                    return String(
                        row[key] ?? ''
                    ) === String(value);
                });

                const refreshAvailability = () => {
                    const selections =
                        currentSelections();

                    Object.entries(fields).forEach(
                        ([key, select]) => {
                            if (!select) {
                                return;
                            }

                            Array.from(
                                select.options
                            ).forEach((option) => {
                                if (option.value === '') {
                                    option.hidden = false;
                                    option.disabled = false;
                                    return;
                                }

                                const candidate =
                                    option.dataset
                                        .filterValue
                                    ?? option.value;

                                const compatible =
                                    rows.some((row) => {
                                        if (
                                            String(
                                                row[key]
                                                ?? ''
                                            ) !== String(
                                                candidate
                                            )
                                        ) {
                                            return false;
                                        }

                                        return rowMatches(
                                            row,
                                            selections,
                                            key
                                        );
                                    });

                                option.hidden =
                                    !compatible;
                                option.disabled =
                                    !compatible;
                            });
                        }
                    );
                };

                const clearHiddenSelections = () => {
                    Object.values(fields).forEach(
                        (select) => {
                            if (
                                !select
                                || select.value === ''
                            ) {
                                return;
                            }

                            const option =
                                select.options[
                                    select.selectedIndex
                                ];

                            if (
                                option.hidden
                                || option.disabled
                            ) {
                                select.value = '';
                            }
                        }
                    );
                };

                Object.values(fields).forEach(
                    (select) => {
                        select?.addEventListener(
                            'change',
                            () => {
                                refreshAvailability();
                                clearHiddenSelections();
                                refreshAvailability();
                            }
                        );
                    }
                );

                refreshAvailability();
                clearHiddenSelections();
                refreshAvailability();
            })();
        </script>
    @endif

    @if ($maintenanceDashboardFilterSnapshot !== null)
        <script>
            (() => {
                const lineSelect =
                    document.getElementById(
                        'production_line_id'
                    );

                const machineSelect =
                    document.getElementById(
                        'machine_id'
                    );

                if (!lineSelect || !machineSelect) {
                    return;
                }

                const machineOptions =
                    Array.from(
                        machineSelect.options
                    );

                const refreshMachines = () => {
                    const lineId =
                        String(
                            lineSelect.value
                            ?? ''
                        );

                    machineOptions.forEach(
                        (option) => {
                            if (option.value === '') {
                                option.hidden = false;
                                option.disabled = false;
                                return;
                            }

                            const compatible =
                                lineId === ''
                                || String(
                                    option.dataset
                                        .productionLineId
                                    ?? ''
                                ) === lineId;

                            option.hidden = !compatible;
                            option.disabled = !compatible;
                        }
                    );

                    const selected =
                        machineSelect.options[
                            machineSelect.selectedIndex
                        ];

                    if (
                        selected
                        && selected.value !== ''
                        && selected.disabled
                    ) {
                        machineSelect.value = '';
                    }
                };

                lineSelect.addEventListener(
                    'change',
                    refreshMachines
                );

                refreshMachines();
            })();
        </script>
    @endif

    <p class="small text-muted-smartfactory mb-0">
        Generated at
        {{
            $overview
                ->generatedAt
                ->setTimezone(
                    $filter->timezone
                )
                ->format(
                    'Y-m-d H:i:s P'
                )
        }}.
    </p>
@endsection