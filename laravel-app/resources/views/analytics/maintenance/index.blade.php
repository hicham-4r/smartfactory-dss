@extends('layouts.app')

@section('title', 'Maintenance KPI Summary')

@section('content')
@php
    $formatMinutes = static function (int|float|null $minutes): string {
        if ($minutes === null) {
            return 'N/A';
        }

        $minutes = (int) round($minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return $remaining.' min';
        }

        return $hours.' h '.$remaining.' min';
    };

    $formatPercentage = static fn (?float $value): string =>
        $value === null
            ? 'N/A'
            : number_format($value, 2).'%';
@endphp

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Maintenance KPI Summary</h1>
            <p class="text-muted mb-0">
                Deterministic downtime, machine-status, and maintenance indicators from synchronized DSS data.
            </p>
        </div>

        <div class="d-flex gap-2">
            @can(\App\Enums\PermissionName::ViewProductionKpis->value)
                <a
                    href="{{ route('analytics.production.index') }}"
                    class="btn btn-outline-primary"
                >
                    Production analytics
                </a>
            @endcan

            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                Dashboard
            </a>
        </div>
    </div>

    @include(
        'analytics.partials.active-drilldowns',
        [
            'domain' => 'maintenance',
            'filter' => $filter,
        ]
    )

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-2">The filters could not be applied.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-secondary" role="note">
        <strong>Data basis:</strong>
        {{ $summary->dataBasisLabel() }}
        Maintenance type affects intervention metrics; downtime category affects downtime metrics.
    </div>

    @if ($summary->hasUnclassifiedDowntime())
        <div class="alert alert-warning" role="alert">
            {{ $formatMinutes($summary->unclassifiedDowntimeMinutes) }}
            of downtime could not be classified from either the dedicated ERP category
            or a recognized source downtime type/title.
        </div>
    @endif

    @if (! $summary->hasObservedStatusCoverage() && ! $summary->isEmpty())
        <div class="alert alert-info" role="alert">
            No machine-status interval covers this selection, so availability and MTBF remain N/A.
            Try a wider period or verify that machine-status events were synchronized.
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Filters</div>

        <div class="card-body">
            <form
                method="GET"
                action="{{ route('analytics.maintenance.index') }}"
                class="row g-3"
                data-sf-loading
                data-sf-loading-text="Applying filters..."
            >
                <div class="col-md-3 col-xl-2">
                    <label for="start_date" class="form-label">Start date</label>
                    <input
                        id="start_date"
                        name="start_date"
                        type="date"
                        class="form-control"
                        value="{{ $filter->startDateString() }}"
                        required
                    >
                </div>

                <div class="col-md-3 col-xl-2">
                    <label for="end_date" class="form-label">End date</label>
                    <input
                        id="end_date"
                        name="end_date"
                        type="date"
                        class="form-control"
                        value="{{ $filter->endDateString() }}"
                        required
                    >
                </div>

                <div class="col-md-3 col-xl-2">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select
                        id="timezone"
                        name="timezone"
                        class="form-select"
                        required
                    >
                        @foreach ($timezoneOptions as $timezone)
                            <option
                                value="{{ $timezone }}"
                                @selected($filter->timezone === $timezone)
                            >
                                {{ $timezone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 col-xl-3">
                    <label for="production_line_id" class="form-label">
                        Production line
                    </label>
                    <select
                        id="production_line_id"
                        name="production_line_id"
                        class="form-select"
                    >
                        <option value="">All lines</option>

                        @foreach ($productionLines as $line)
                            <option
                                value="{{ $line->id }}"
                                @selected($filter->productionLineId === (int) $line->id)
                            >
                                {{ $line->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Lines without maintenance analytics data in this period
                        are excluded.
                    </div>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="machine_id" class="form-label">Machine</label>
                    <select
                        id="machine_id"
                        name="machine_id"
                        class="form-select"
                    >
                        <option value="">All machines</option>

                        @foreach ($machines as $machine)
                            <option
                                value="{{ $machine->id }}"
                                data-production-line-id="{{ $machine->production_line_id }}"
                                @selected($filter->machineId === (int) $machine->id)
                            >
                                {{ $machine->production_line_name }} â€” {{ $machine->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Only machines with maintenance, downtime, or status data
                        in the selected period are listed.
                    </div>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="maintenance_type" class="form-label">
                        Maintenance type
                    </label>
                    <select
                        id="maintenance_type"
                        name="maintenance_type"
                        class="form-select"
                    >
                        <option value="">All maintenance types</option>

                        @foreach ($maintenanceTypes as $type)
                            <option
                                value="{{ $type->value }}"
                                @selected($filter->maintenanceType === $type->value)
                            >
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="downtime_category" class="form-label">
                        Downtime category
                    </label>
                    <select
                        id="downtime_category"
                        name="downtime_category"
                        class="form-select"
                    >
                        <option value="">All downtime</option>

                        @foreach ($downtimeCategories as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($filter->downtimeCategory === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        Apply filters
                    </button>

                    <a
                        href="{{ route('analytics.maintenance.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if ($summary->isEmpty())
        <div class="alert alert-light border shadow-sm" role="status">
            <h2 class="h5">No matching maintenance data</h2>
            <p class="mb-0 text-muted">
                No downtime, machine-status, or maintenance records match the selected period and filters.
            </p>
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Total downtime</div>
                        <div class="fs-4 fw-semibold">
                            {{ $formatMinutes($summary->totalDowntimeMinutes) }}
                        </div>
                        <div class="small text-muted">
                            {{ number_format($summary->downtimeEventCount) }} events,
                            {{ number_format($summary->openDowntimeEventCount) }} open
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Planned / unplanned</div>
                        <div class="fw-semibold">
                            {{ $formatMinutes($summary->plannedDowntimeMinutes) }}
                            /
                            {{ $formatMinutes($summary->unplannedDowntimeMinutes) }}
                        </div>
                        <div class="small text-muted">
                            ERP category with source-type fallback
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Observed availability</div>
                        <div class="fs-4 fw-semibold">
                            {{ $formatPercentage($summary->availabilityPercentage) }}
                        </div>
                        <div class="small text-muted">
                            {{ $formatMinutes($summary->runningMinutes) }} running /
                            {{ $formatMinutes($summary->observedStatusMinutes) }} observed
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">MTTR</div>
                        <div class="fs-4 fw-semibold">
                            {{ $formatMinutes($summary->mttrMinutes) }}
                        </div>
                        <div class="small text-muted">
                            {{ number_format($summary->completedCorrectiveCount) }}
                            completed corrective interventions
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">MTBF</div>
                        <div class="fs-4 fw-semibold">
                            {{ $formatMinutes($summary->mtbfMinutes) }}
                        </div>
                        <div class="small text-muted">
                            {{ number_format($summary->faultEventCount) }} recognized failure events
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Failure frequency</div>
                        <div class="fs-4 fw-semibold">
                            {{ $summary->failuresPer100RunningHours === null
                                ? 'N/A'
                                : number_format($summary->failuresPer100RunningHours, 2) }}
                        </div>
                        <div class="small text-muted">Faults per 100 running hours</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Interventions</div>
                        <div class="fs-4 fw-semibold">
                            {{ number_format($summary->maintenanceInterventionCount) }}
                        </div>
                        <div class="small text-muted">
                            {{ number_format($summary->preventiveInterventionCount) }} preventive /
                            {{ number_format($summary->correctiveInterventionCount) }} corrective
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Repeated-failure machines</div>
                        <div class="fs-4 fw-semibold">
                            {{ number_format($summary->repeatedFailureMachineCount) }}
                        </div>
                        <div class="small text-muted">At least two fault events</div>
                    </div>
                </div>
            </div>
        </div>

        @if ($summary->highestDowntimeMachine() !== null)
            <div class="alert alert-danger-subtle border mb-4">
                <strong>Highest downtime:</strong>
                {{ $summary->highestDowntimeMachine()->machineName }}
                on {{ $summary->highestDowntimeMachine()->productionLineName }}
                with {{ $formatMinutes($summary->highestDowntimeMachine()->totalDowntimeMinutes) }}.
            </div>
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Machine maintenance indicators</div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Machine</th>
                            <th scope="col">Line</th>
                            <th scope="col" class="text-end">Downtime</th>
                            <th scope="col" class="text-end">Planned</th>
                            <th scope="col" class="text-end">Unplanned</th>
                            <th scope="col" class="text-end">Availability</th>
                            <th scope="col" class="text-end">Failures</th>
                            <th scope="col" class="text-end">MTBF</th>
                            <th scope="col" class="text-end">MTTR</th>
                            <th scope="col" class="text-end">Preventive</th>
                            <th scope="col" class="text-end">Corrective</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary->machines as $machine)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $machine->machineName }}</div>
                                    <div class="small text-muted">{{ $machine->machineCode }}</div>
                                    @if ($machine->hasRepeatedFailures())
                                        <span class="badge text-bg-warning">Repeated failures</span>
                                    @endif
                                </td>
                                <td>{{ $machine->productionLineName }}</td>
                                <td class="text-end">
                                    {{ $formatMinutes($machine->totalDowntimeMinutes) }}
                                </td>
                                <td class="text-end">
                                    {{ $formatMinutes($machine->plannedDowntimeMinutes) }}
                                </td>
                                <td class="text-end">
                                    {{ $formatMinutes($machine->unplannedDowntimeMinutes) }}
                                </td>
                                <td class="text-end">
                                    {{ $formatPercentage($machine->availabilityPercentage) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format($machine->faultEventCount) }}
                                </td>
                                <td class="text-end">
                                    {{ $formatMinutes($machine->mtbfMinutes) }}
                                </td>
                                <td class="text-end">
                                    {{ $formatMinutes($machine->mttrMinutes) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format($machine->preventiveInterventionCount) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format($machine->correctiveInterventionCount) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Interventions by maintenance type</div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col" class="text-end">Planned</th>
                            <th scope="col" class="text-end">In progress</th>
                            <th scope="col" class="text-end">Completed</th>
                            <th scope="col" class="text-end">Cancelled</th>
                            <th scope="col" class="text-end">Downtime</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary->maintenanceTypes as $type)
                            <tr>
                                <td class="fw-semibold">{{ $type->label }}</td>
                                <td class="text-end">{{ number_format($type->interventionCount) }}</td>
                                <td class="text-end">{{ number_format($type->plannedCount) }}</td>
                                <td class="text-end">{{ number_format($type->inProgressCount) }}</td>
                                <td class="text-end">{{ number_format($type->completedCount) }}</td>
                                <td class="text-end">{{ number_format($type->cancelledCount) }}</td>
                                <td class="text-end">{{ $formatMinutes($type->downtimeMinutes) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No maintenance interventions match this selection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="small text-muted mb-0">
        Generated at {{ $summary->generatedAt->setTimezone($filter->timezone)->format('Y-m-d H:i:s T') }}.
        All records are simulated ERP or DSS prototype data; they are not real company operational results.
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const lineSelect = document.getElementById('production_line_id');
    const machineSelect = document.getElementById('machine_id');

    if (! lineSelect || ! machineSelect) {
        return;
    }

    const machineOptions = Array.from(machineSelect.options);

    const refreshMachineOptions = () => {
        const selectedLineId = String(lineSelect.value || '');

        machineOptions.forEach((option) => {
            if (option.value === '') {
                option.hidden = false;
                option.disabled = false;

                return;
            }

            const belongsToSelectedLine =
                selectedLineId === ''
                || String(option.dataset.productionLineId || '')
                    === selectedLineId;

            option.hidden = ! belongsToSelectedLine;
            option.disabled = ! belongsToSelectedLine;
        });

        const selectedOption =
            machineSelect.options[
                machineSelect.selectedIndex
            ];

        if (
            selectedOption
            && selectedOption.value !== ''
            && selectedOption.disabled
        ) {
            machineSelect.value = '';
        }
    };

    lineSelect.addEventListener(
        'change',
        refreshMachineOptions
    );

    refreshMachineOptions();
});
</script>

@endsection
