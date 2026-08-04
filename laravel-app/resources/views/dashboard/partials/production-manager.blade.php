@include('dashboard.partials.ai-insights-link')

@php
    $managerDuration = static function (
        int|float|null $minutes
    ): string {
        if ($minutes === null) {
            return 'N/A';
        }

        $minutes = max(0, (int) round($minutes));
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours === 0
            ? $remaining.' min'
            : $hours.' h '.$remaining.' min';
    };

    $managerPercentage = static fn (
        float|int|null $value
    ): string => $value === null
        ? 'N/A'
        : number_format((float) $value, 2).'%';
@endphp

<section
    class="mb-4"
    aria-labelledby="production-manager-dashboard-title"
    data-sf-drilldown-scope
    data-sf-drilldown-url="{{
        route(
            'analytics.production.index',
            $snapshot->filter->toQuery()
        )
    }}"
>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-1">
                Executive production control
            </p>

            <h2
                id="production-manager-dashboard-title"
                class="h4 fw-bold mb-0"
            >
                Production manager executive dashboard
            </h2>
        </div>

        @if ($snapshot->needsAttention())
            <span class="badge text-bg-warning fs-6">
                Executive attention required
            </span>
        @else
            <span class="badge text-bg-success fs-6">
                No critical exception detected
            </span>
        @endif
    </div>

    <div class="alert alert-secondary" role="note">
        <strong>Executive data basis:</strong>
        {{ $snapshot->dataBasisLabel() }}
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    In-progress orders
                </div>
                <div class="display-6">
                    {{ number_format($snapshot->inProgressOrderCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    Active execution workload
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Completed orders
                </div>
                <div class="display-6">
                    {{ number_format($snapshot->completedOrderCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    Completed in the selected period
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Critical unresolved events
                </div>
                <div class="display-6">
                    {{ number_format($snapshot->criticalEventCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    Production exceptions requiring escalation
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Execution records
                </div>
                <div class="display-6">
                    {{ number_format($snapshot->production->recordCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    {{ number_format($snapshot->production->validatedRecordCount) }} validated /
                    {{ number_format($snapshot->production->provisionalRecordCount) }} provisional
                </div>
            </div>
        </div>
    </div>

    <div class="app-card bg-white mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom px-4 py-3">
            <div>
                <div class="fw-semibold">Production snapshot</div>
                <div class="small text-muted-smartfactory">
                    Target, actual output, quality and time indicators by quantity unit
                </div>
            </div>

            <a
                href="{{ route('analytics.production.index', $snapshot->filter->toQuery()) }}"
                class="btn btn-sm btn-outline-primary"
            >
                Open detailed production analytics
            </a>
        </div>

        @if ($snapshot->production->units === [])
            <div class="p-4 text-muted-smartfactory">
                No production KPI data matches the selected filters.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th class="text-end">Target</th>
                            <th class="text-end">Actual</th>
                            <th class="text-end">Achievement</th>
                            <th class="text-end">Good</th>
                            <th class="text-end">Rejected</th>
                            <th class="text-end">Rejection rate</th>
                            <th class="text-end">Runtime</th>
                            <th class="text-end">Downtime</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshot->production->units as $unit)
                            <tr>
                                <th>{{ $unit->quantityUnit }}</th>
                                <td class="text-end">
                                    {{ number_format((float) $unit->targetQuantity, 3) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format((float) $unit->actualQuantity, 3) }}
                                </td>
                                <td class="text-end">
                                    {{ $managerPercentage($unit->achievementPercentage) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format((float) $unit->goodQuantity, 3) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format((float) $unit->rejectedQuantity, 3) }}
                                </td>
                                <td class="text-end">
                                    {{ $managerPercentage($unit->rejectionPercentage) }}
                                </td>
                                <td class="text-end">
                                    {{ $managerDuration($unit->runtimeMinutes) }}
                                </td>
                                <td class="text-end">
                                    {{ $managerDuration($unit->downtimeMinutes) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="app-card bg-white h-100">
                <div class="border-bottom px-4 py-3 fw-semibold">
                    Monthly production trend
                </div>

                @if ($snapshot->breakdowns->monthlyTrend === [])
                    <div class="p-4 text-muted-smartfactory">
                        No monthly trend is available for this selection.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Unit</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Achievement</th>
                                    <th class="text-end">Rejected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($snapshot->breakdowns->monthlyTrend, -12) as $row)
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ number_format((float) $row->actualQuantity, 3) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->rejectionPercentage) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-6">
            <div class="app-card bg-white h-100">
                <div class="border-bottom px-4 py-3 fw-semibold">
                    Production-line comparison
                </div>

                @if ($snapshot->breakdowns->byProductionLine === [])
                    <div class="p-4 text-muted-smartfactory">
                        No production-line comparison is available.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Line</th>
                                    <th>Unit</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Achievement</th>
                                    <th class="text-end">Quality yield</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($snapshot->breakdowns->byProductionLine, 0, 10) as $row)
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ number_format((float) $row->actualQuantity, 3) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->qualityYieldPercentage) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="app-card bg-white h-100">
                <div class="border-bottom px-4 py-3 fw-semibold">
                    Product-family comparison
                </div>

                @if ($snapshot->breakdowns->byProductFamily === [])
                    <div class="p-4 text-muted-smartfactory">
                        No product-family comparison is available.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Family</th>
                                    <th>Unit</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Achievement</th>
                                    <th class="text-end">Rejected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($snapshot->breakdowns->byProductFamily, 0, 10) as $row)
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ number_format((float) $row->actualQuantity, 3) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->rejectionPercentage) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-6">
            <div class="app-card bg-white h-100">
                <div class="border-bottom px-4 py-3 fw-semibold">
                    Product comparison
                </div>

                @if ($snapshot->breakdowns->byProduct === [])
                    <div class="p-4 text-muted-smartfactory">
                        No product comparison is available.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Achievement</th>
                                    <th class="text-end">Rejected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($snapshot->breakdowns->byProduct, 0, 10) as $row)
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ number_format((float) $row->actualQuantity, 3) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->rejectionPercentage) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="app-card bg-white h-100">
                <div class="border-bottom px-4 py-3 fw-semibold">
                    Best-performing lines by unit
                </div>

                @if ($snapshot->breakdowns->bestLinesByUnit === [])
                    <div class="p-4 text-muted-smartfactory">
                        No best-line ranking is available.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Line</th>
                                    <th>Unit</th>
                                    <th class="text-end">Achievement</th>
                                    <th class="text-end">Rejection</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot->breakdowns->bestLinesByUnit as $row)
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->rejectionPercentage) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-6">
            <div class="app-card bg-white h-100">
                <div class="border-bottom px-4 py-3 fw-semibold">
                    Lowest-performing lines by unit
                </div>

                @if ($snapshot->breakdowns->lowestLinesByUnit === [])
                    <div class="p-4 text-muted-smartfactory">
                        No lowest-line ranking is available.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Line</th>
                                    <th>Unit</th>
                                    <th class="text-end">Achievement</th>
                                    <th class="text-end">Rejection</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot->breakdowns->lowestLinesByUnit as $row)
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $managerPercentage($row->rejectionPercentage) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-1">
                Quality
            </p>
            <h3 class="h5 fw-bold mb-0">Quality snapshot</h3>
        </div>

        <a
            href="{{ route('analytics.quality.index', $snapshot->filter->toQualityQuery()) }}"
            class="btn btn-sm btn-outline-info"
        >
            Open detailed quality analytics
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">Failed inspections</div>
                <div class="display-6">
                    {{ number_format($snapshot->quality->failedInspectionCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    {{ $managerPercentage($snapshot->quality->inspectionPassPercentage) }} pass rate
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">Blocked lots</div>
                <div class="display-6">
                    {{ number_format($snapshot->quality->blockedLotCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    Awaiting or blocked release decisions
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">Rejected lots</div>
                <div class="display-6">
                    {{ number_format($snapshot->quality->rejectedLotCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    Final rejected finished lots
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">Critical nonconformities</div>
                <div class="display-6">
                    {{ number_format($snapshot->quality->criticalNonconformityCount) }}
                </div>
                <div class="small text-muted-smartfactory">
                    Quality risks requiring escalation
                </div>
            </div>
        </div>
    </div>

    <div class="app-card bg-white mb-4">
        <div class="border-bottom px-4 py-3 fw-semibold">
            Recent critical unresolved production events
        </div>

        @if ($snapshot->criticalEvents === [])
            <div class="p-4 text-muted-smartfactory">
                No critical unresolved event matches the selected filters.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Line</th>
                            <th>Machine</th>
                            <th>Shift</th>
                            <th>Started</th>
                            <th class="text-end">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshot->criticalEvents as $event)
                            <tr>
                                <th>{{ $event->eventNumber }}</th>
                                <td>{{ $event->title }}</td>
                                <td>{{ str($event->eventType)->headline() }}</td>
                                <td>{{ $event->productionLineName }}</td>
                                <td>{{ $event->machineName ?? 'N/A' }}</td>
                                <td>{{ $event->shiftName ?? 'N/A' }}</td>
                                <td>{{ $event->startedAt }}</td>
                                <td class="text-end">
                                    {{ $managerDuration($event->durationMinutes) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="alert alert-light border" role="note">
        Forecasts, anomaly prediction and AI executive summaries are intentionally deferred until the deterministic dashboard and data-quality checks are fully accepted.
    </div>
</section>
