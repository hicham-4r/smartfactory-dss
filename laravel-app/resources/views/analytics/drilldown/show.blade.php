@extends('layouts.app')

@section('title', $context['title'])

@section('content')
@php
    $percentage = static fn (?float $value): string =>
        $value === null
            ? 'N/A'
            : number_format($value, 2).'%';

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

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours === 0
            ? $remaining.' min'
            : $hours.' h '.$remaining.' min';
    };
@endphp

<div class="container-fluid py-2">
    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ $dashboardUrl }}">Dashboard</a>
            </li>

            <li class="breadcrumb-item">
                <a href="{{ $analyticsUrl }}">Analytics</a>
            </li>

            <li
                class="breadcrumb-item active"
                aria-current="page"
            >
                {{ $context['entity_label'] }}
            </li>
        </ol>
    </nav>

    <header class="app-card bg-white p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="text-uppercase small fw-semibold text-secondary mb-1">
                    {{ $context['eyebrow'] }}
                </p>

                <h1 class="h3 fw-bold mb-2">
                    {{ $context['title'] }}
                </h1>

                <p class="lead mb-1">
                    {{ $context['entity_label'] }}
                </p>

                @if (! empty($context['entity_code']))
                    <p class="text-muted-smartfactory mb-2">
                        {{ $context['entity_type'] }} code:
                        <span class="font-monospace">
                            {{ $context['entity_code'] }}
                        </span>
                    </p>
                @endif

                <p class="text-muted-smartfactory mb-0">
                    {{ $context['description'] }}
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a
                    href="{{ $analyticsUrl }}"
                    class="btn btn-primary"
                >
                    Open filtered analytics
                </a>

                <a
                    href="{{ $dashboardUrl }}"
                    class="btn btn-outline-secondary"
                >
                    Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="alert alert-info" role="note">
        <strong>Selected period:</strong>
        {{ $filter->startDateString() }}
        to
        {{ $filter->endDateString() }}
        in
        {{ $filter->timezone }}.
        Values are calculated from synchronized simulated ERP or DSS
        prototype data. Different quantity units are never combined.
    </div>

    @if ($domain === 'production')
        @php
            $summary = $productionSummary;
        @endphp

        <div class="alert alert-secondary" role="note">
            <strong>Data basis:</strong>
            {{ $summary->dataBasisLabel() }}
        </div>

        @if ($summary->isEmpty())
            <x-interface-state
                type="empty"
                title="No production data"
                message="No eligible production target or record matches this entity and period."
                :action-url="$analyticsUrl"
                action-label="Change filters"
                class="mb-4"
            />
        @else
            <div class="row g-3 mb-4">
                @foreach ([
                    [
                        'Records',
                        number_format($summary->recordCount),
                        number_format($summary->validatedRecordCount).' validated',
                    ],
                    [
                        'Provisional records',
                        number_format($summary->provisionalRecordCount),
                        $summary->isProvisional()
                            ? 'Pending values are included'
                            : 'No pending values',
                    ],
                    [
                        'Runtime',
                        $duration($summary->runtimeMinutes),
                        'Observed production time',
                    ],
                    [
                        'Downtime',
                        $duration($summary->downtimeMinutes),
                        'Recorded interruption time',
                    ],
                ] as [$label, $value, $note])
                    <div class="col-sm-6 col-xl-3">
                        <div class="app-card bg-white h-100 p-4">
                            <div class="text-muted-smartfactory">
                                {{ $label }}
                            </div>

                            <div class="h2 mb-1">
                                {{ $value }}
                            </div>

                            <div class="small text-muted-smartfactory">
                                {{ $note }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <section class="app-card bg-white mb-4">
                <div class="border-bottom px-4 py-3">
                    <h2 class="h5 mb-1">
                        Production totals by quantity unit
                    </h2>

                    <p class="small text-muted-smartfactory mb-0">
                        Targets and actual output are compared only inside
                        the same unit.
                    </p>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th class="text-end">Target</th>
                                <th class="text-end">Actual</th>
                                <th class="text-end">Good</th>
                                <th class="text-end">Rejected</th>
                                <th class="text-end">Achievement</th>
                                <th class="text-end">Rejection</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($summary->units as $unit)
                                <tr>
                                    <th scope="row">
                                        {{ $unit->quantityUnit }}
                                    </th>

                                    <td class="text-end">
                                        {{ number_format((float) $unit->targetQuantity, 3) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format((float) $unit->actualQuantity, 3) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format((float) $unit->goodQuantity, 3) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format((float) $unit->rejectedQuantity, 3) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $percentage($unit->achievementPercentage) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $percentage($unit->rejectionPercentage) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @include(
            'analytics.production.partials.breakdowns',
            [
                'report' => $productionBreakdown,
            ]
        )
    @elseif ($domain === 'maintenance')
        @php
            $summary = $maintenanceSummary;
        @endphp

        <div class="alert alert-secondary" role="note">
            <strong>Data basis:</strong>
            {{ $summary->dataBasisLabel() }}
        </div>

        @if ($summary->isEmpty())
            <x-interface-state
                type="empty"
                title="No maintenance data"
                message="No downtime, machine-state, or maintenance intervention matches this machine and period."
                :action-url="$analyticsUrl"
                action-label="Change filters"
                class="mb-4"
            />
        @else
            <div class="row g-3 mb-4">
                @foreach ([
                    [
                        'Downtime',
                        $duration($summary->totalDowntimeMinutes),
                        number_format($summary->downtimeEventCount).' events',
                    ],
                    [
                        'Availability',
                        $percentage($summary->availabilityPercentage),
                        'Observed machine-state availability',
                    ],
                    [
                        'MTTR',
                        $duration($summary->mttrMinutes),
                        'Completed corrective interventions',
                    ],
                    [
                        'MTBF',
                        $duration($summary->mtbfMinutes),
                        'Observed running time per failure',
                    ],
                    [
                        'Failures',
                        number_format($summary->faultEventCount),
                        number_format($summary->repeatedFailureMachineCount).' repeated-failure machines',
                    ],
                    [
                        'Interventions',
                        number_format($summary->maintenanceInterventionCount),
                        number_format($summary->preventiveInterventionCount).' preventive / '
                            .number_format($summary->correctiveInterventionCount).' corrective',
                    ],
                ] as [$label, $value, $note])
                    <div class="col-sm-6 col-xl-4">
                        <div class="app-card bg-white h-100 p-4">
                            <div class="text-muted-smartfactory">
                                {{ $label }}
                            </div>

                            <div class="h2 mb-1">
                                {{ $value }}
                            </div>

                            <div class="small text-muted-smartfactory">
                                {{ $note }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <section class="app-card bg-white mb-4">
                <div class="border-bottom px-4 py-3">
                    <h2 class="h5 mb-0">
                        Machine maintenance metrics
                    </h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Line</th>
                                <th class="text-end">Downtime</th>
                                <th class="text-end">Planned</th>
                                <th class="text-end">Unplanned</th>
                                <th class="text-end">Availability</th>
                                <th class="text-end">Failures</th>
                                <th class="text-end">MTTR</th>
                                <th class="text-end">MTBF</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($summary->machines as $machine)
                                <tr>
                                    <th scope="row">
                                        {{ $machine->machineName }}

                                        <div class="small text-muted-smartfactory">
                                            {{ $machine->machineCode }}
                                        </div>
                                    </th>

                                    <td>
                                        {{ $machine->productionLineName }}
                                    </td>

                                    <td class="text-end">
                                        {{ $duration($machine->totalDowntimeMinutes) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $duration($machine->plannedDowntimeMinutes) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $duration($machine->unplannedDowntimeMinutes) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $percentage($machine->availabilityPercentage) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format($machine->faultEventCount) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $duration($machine->mttrMinutes) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $duration($machine->mtbfMinutes) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @else
        @php
            $summary = $qualitySummary;
        @endphp

        <div class="alert alert-secondary" role="note">
            <strong>Data basis:</strong>
            {{ $summary->dataBasisLabel() }}
        </div>

        @if ($summary->isEmpty())
            <x-interface-state
                type="empty"
                title="No quality data"
                message="No inspection, finished lot, or nonconformity matches this entity and period."
                :action-url="$analyticsUrl"
                action-label="Change filters"
                class="mb-4"
            />
        @else
            <div class="row g-3 mb-4">
                @foreach ([
                    [
                        'Inspections',
                        number_format($summary->inspectionCount),
                        number_format($summary->passedInspectionCount).' passed / '
                            .number_format($summary->failedInspectionCount).' failed',
                    ],
                    [
                        'Inspection pass rate',
                        $percentage($summary->inspectionPassPercentage),
                        number_format($summary->conditionalInspectionCount).' conditional',
                    ],
                    [
                        'Finished lots',
                        number_format($summary->lotCount),
                        number_format($summary->releasedLotCount).' released / '
                            .number_format($summary->blockedLotCount).' blocked',
                    ],
                    [
                        'Nonconformities',
                        number_format($summary->nonconformityCount),
                        number_format($summary->criticalNonconformityCount).' critical',
                    ],
                ] as [$label, $value, $note])
                    <div class="col-sm-6 col-xl-3">
                        <div class="app-card bg-white h-100 p-4">
                            <div class="text-muted-smartfactory">
                                {{ $label }}
                            </div>

                            <div class="h2 mb-1">
                                {{ $value }}
                            </div>

                            <div class="small text-muted-smartfactory">
                                {{ $note }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <section class="app-card bg-white mb-4">
                <div class="border-bottom px-4 py-3">
                    <h2 class="h5 mb-0">
                        Finished-lot quantities by unit
                    </h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th class="text-end">Lots</th>
                                <th class="text-end">Produced</th>
                                <th class="text-end">Released</th>
                                <th class="text-end">Released rate</th>
                                <th class="text-end">Rejected</th>
                                <th class="text-end">Rejected rate</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($summary->quantityUnits as $unit)
                                <tr>
                                    <th scope="row">
                                        {{ $unit->quantityUnit }}
                                    </th>

                                    <td class="text-end">
                                        {{ number_format($unit->lotCount) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format((float) $unit->producedQuantity, 3) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format((float) $unit->releasedQuantity, 3) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $percentage($unit->releasedQuantityPercentage) }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format((float) $unit->rejectedQuantity, 3) }}
                                    </td>

                                    <td class="text-end">
                                        {{ $percentage($unit->rejectedQuantityPercentage) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
