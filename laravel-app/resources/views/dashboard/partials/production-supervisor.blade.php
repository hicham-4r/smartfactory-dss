@include('dashboard.partials.ai-insights-link')

@php
    $statusLabel = static fn (string $value): string => match ($value) {
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'validated' => 'Validated',
        'pending' => 'Pending',
        'downtime' => 'Downtime',
        'machine_incident' => 'Machine incident',
        'information' => 'Information',
        'warning' => 'Warning',
        'critical' => 'Critical',
        default => str($value)
            ->replace('_', ' ')
            ->headline()
            ->toString(),
    };

    $severityClass = static fn (string $severity): string => match ($severity) {
        'critical' => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
@endphp

<section
    class="mb-4"
    aria-labelledby="production-supervisor-dashboard-title"
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
                Production supervision
            </p>

            <h2
                id="production-supervisor-dashboard-title"
                class="h4 fw-bold mb-1"
            >
                Production Supervisor operational dashboard
            </h2>

            <p class="text-muted-smartfactory mb-0">
                Prioritize validations, unresolved events and current execution performance.
            </p>
        </div>

        @if ($snapshot->needsAttention())
            <span class="badge text-bg-warning fs-6">
                Attention required
            </span>
        @else
            <span class="badge text-bg-success fs-6">
                No open action detected
            </span>
        @endif
    </div>

    <div class="alert alert-info" role="note">
        <strong>Supervisor data basis:</strong>
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
                    Active execution orders in scope
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Pending validations
                </div>

                <div class="display-6">
                    {{ number_format($snapshot->pendingValidationCount) }}
                </div>

                <div class="small text-muted-smartfactory">
                    Submitted operator records awaiting decision
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Unresolved events
                </div>

                <div class="display-6">
                    {{ number_format($snapshot->unresolvedEventCount) }}
                </div>

                <div class="small text-muted-smartfactory">
                    Downtime, incidents and production events
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Critical events
                </div>

                <div class="display-6">
                    {{ number_format($snapshot->criticalEventCount) }}
                </div>

                <div class="small text-muted-smartfactory">
                    Highest operational priority
                </div>
            </div>
        </div>
    </div>

    <div class="app-card bg-white mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom px-4 py-3">
            <div>
                <div class="fw-semibold">
                    Production execution by quantity unit
                </div>

                <div class="small text-muted-smartfactory">
                    Scheduled versus actual, quality output and time utilization
                </div>
            </div>

            @if ($snapshot->production->isProvisional())
                <span class="badge text-bg-warning">
                    Includes provisional records
                </span>
            @endif
        </div>

        @if ($snapshot->production->units === [])
            <div class="p-4 text-muted-smartfactory">
                No matching production execution data exists for the selected filters.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th class="text-end">Scheduled</th>
                            <th class="text-end">Actual</th>
                            <th class="text-end">Good</th>
                            <th class="text-end">Rejected</th>
                            <th class="text-end">Achievement</th>
                            <th class="text-end">Rejection</th>
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
                                <td class="text-end">
                                    {{ $duration($unit->runtimeMinutes) }}
                                </td>
                                <td class="text-end">
                                    {{ $duration($unit->downtimeMinutes) }}
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
                    Performance by production line
                </div>

                @if ($snapshot->breakdowns->byProductionLine === [])
                    <div class="p-4 text-muted-smartfactory">
                        No line breakdown is available for this selection.
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
                                    <th class="text-end">Rejected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (
                                    array_slice(
                                        $snapshot
                                            ->breakdowns
                                            ->byProductionLine,
                                        0,
                                        10
                                    )
                                    as $row
                                )
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ number_format((float) $row->actualQuantity, 3) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $percentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $percentage($row->rejectionPercentage) }}
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
                    Performance by shift
                </div>

                @if ($snapshot->breakdowns->byShift === [])
                    <div class="p-4 text-muted-smartfactory">
                        No shift breakdown is available for this selection.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Shift</th>
                                    <th>Unit</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Achievement</th>
                                    <th class="text-end">Downtime</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (
                                    array_slice(
                                        $snapshot
                                            ->breakdowns
                                            ->byShift,
                                        0,
                                        10
                                    )
                                    as $row
                                )
                                    <tr>
                                        <th>{{ $row->label }}</th>
                                        <td>{{ $row->quantityUnit }}</td>
                                        <td class="text-end">
                                            {{ number_format((float) $row->actualQuantity, 3) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $percentage($row->achievementPercentage) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $duration($row->downtimeMinutes) }}
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

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Failed inspections
                </div>

                <div class="display-6">
                    {{ number_format($snapshot->quality->failedInspectionCount) }}
                </div>

                <div class="small text-muted-smartfactory">
                    {{ $percentage($snapshot->quality->inspectionPassPercentage) }} pass rate
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Blocked lots
                </div>

                <div class="display-6">
                    {{ number_format($snapshot->quality->blockedLotCount) }}
                </div>

                <div class="small text-muted-smartfactory">
                    Pending quality disposition
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Rejected lots
                </div>

                <div class="display-6">
                    {{ number_format($snapshot->quality->rejectedLotCount) }}
                </div>

                <div class="small text-muted-smartfactory">
                    Final rejected release decisions
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Critical nonconformities
                </div>

                <div class="display-6">
                    {{ number_format($snapshot->quality->criticalNonconformityCount) }}
                </div>

                <div class="small text-muted-smartfactory">
                    Quality issues requiring escalation
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="app-card bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom px-4 py-3">
                    <div class="fw-semibold">
                        In-progress production orders
                    </div>

                    <a
                        href="{{ route('production.supervisor.index') }}"
                        class="btn btn-sm btn-outline-primary"
                    >
                        Open supervisor workspace
                    </a>
                </div>

                @if ($snapshot->inProgressOrders === [])
                    <div class="p-4 text-muted-smartfactory">
                        No in-progress order matches the selected filters.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Product</th>
                                    <th>Line</th>
                                    <th>Shift</th>
                                    <th>Planned start</th>
                                    <th class="text-end">Target</th>
                                    <th class="text-end">Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot->inProgressOrders as $order)
                                    <tr>
                                        <th>
                                            <a href="{{ route('production.supervisor.orders.show', $order->id) }}">
                                                {{ $order->orderNumber }}
                                            </a>
                                        </th>
                                        <td>{{ $order->productName }}</td>
                                        <td>{{ $order->productionLineName }}</td>
                                        <td>{{ $order->shiftName ?? 'Record-assigned' }}</td>
                                        <td>{{ $order->plannedStartAt }}</td>
                                        <td class="text-end">
                                            {{ number_format((float) $order->targetQuantity, 3) }}
                                            {{ $order->quantityUnit }}
                                        </td>
                                        <td class="text-end">{{ $order->priority }}</td>
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
                    Records awaiting validation
                </div>

                @if ($snapshot->pendingRecords === [])
                    <div class="p-4 text-muted-smartfactory">
                        No submitted record is waiting for validation.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Record</th>
                                    <th>Date</th>
                                    <th>Product / line</th>
                                    <th class="text-end">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot->pendingRecords as $record)
                                    <tr>
                                        <th>
                                            <a href="{{ route('production.supervisor.records.show', $record->id) }}">
                                                {{ $record->recordNumber }}
                                            </a>
                                        </th>
                                        <td>{{ $record->productionDate }}</td>
                                        <td>
                                            {{ $record->productName }}
                                            <div class="small text-muted-smartfactory">
                                                {{ $record->productionLineName }} Â· {{ $record->shiftName }}
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            {{ number_format((float) $record->producedQuantity, 3) }}
                                            {{ $record->quantityUnit }}
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
                    Unresolved production events
                </div>

                @if ($snapshot->unresolvedEvents === [])
                    <div class="p-4 text-muted-smartfactory">
                        No unresolved event matches the selected filters.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Severity</th>
                                    <th>Line / machine</th>
                                    <th>Started</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot->unresolvedEvents as $event)
                                    <tr>
                                        <th>
                                            <a href="{{ route('production.supervisor.events.show', $event->id) }}">
                                                {{ $event->eventNumber }}
                                            </a>
                                            <div class="small text-muted-smartfactory">
                                                {{ $event->title }}
                                            </div>
                                        </th>
                                        <td>
                                            <span class="badge {{ $severityClass($event->severity) }}">
                                                {{ $statusLabel($event->severity) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $event->productionLineName }}
                                            <div class="small text-muted-smartfactory">
                                                {{ $event->machineName ?? 'No machine specified' }}
                                            </div>
                                        </td>
                                        <td>{{ $event->startedAt }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a
            href="{{ route('analytics.production.index', $snapshot->filter->toQuery()) }}"
            class="btn btn-outline-success"
        >
            Open full production analytics
        </a>

        <a
            href="{{ route('analytics.quality.index', $snapshot->filter->toQualityQuery()) }}"
            class="btn btn-outline-info"
        >
            Open full quality analytics
        </a>
    </div>
</section>
