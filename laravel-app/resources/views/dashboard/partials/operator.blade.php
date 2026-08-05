@php
    $statusClass = static function (
        string $status
    ): string {
        return match ($status) {
            'in_progress',
            'validated',
            'locked' => 'text-bg-success',

            'released',
            'submitted',
            'pending' => 'text-bg-warning',

            'rejected',
            'critical' => 'text-bg-danger',

            'draft',
            'information' => 'text-bg-secondary',

            default => 'text-bg-info',
        };
    };

    $quickActionOrder = collect(
        $snapshot->assignedOrders
    )->first(
        static fn ($order): bool =>
            $order->hasActionableBatch()
    );
@endphp

<section
    class="mb-4"
    data-sf-drilldown-scope
    data-sf-drilldown-url="{{
        route('production.operator.index')
    }}"
>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <p class="text-uppercase small fw-semibold text-primary mb-1">
                Personal production execution
            </p>

            <h2 class="h4 fw-bold mb-1">
                Operator limited dashboard
            </h2>

            <p class="text-muted-smartfactory mb-0">
                Your assignments, work, records and reported incidents
            </p>
        </div>

        @if (
            $snapshot->profileLinked
            && $snapshot->operatorActive
        )
            <div class="d-flex flex-wrap justify-content-end gap-2">
                @if ($quickActionOrder !== null)
                    <a
                        href="{{
                            route(
                                'production.operator.records.create',
                                $quickActionOrder->actionBatchId
                            )
                        }}"
                        class="btn btn-success"
                    >
                        Enter production data
                    </a>

                    <a
                        href="{{
                            route(
                                'production.operator.events.create',
                                [
                                    'productionBatch' => $quickActionOrder->actionBatchId,
                                    'event_type' => \App\Enums\Production\ProductionEventType::MachineIncident->value,
                                ]
                            )
                        }}"
                        class="btn btn-danger"
                    >
                        Report machine not working
                    </a>
                @endif

                <a
                    href="{{ route('production.operator.index') }}"
                    class="btn btn-primary"
                >
                    Open operator workspace
                </a>
            </div>
        @else
            <span class="btn btn-secondary disabled" aria-disabled="true">
                Operator workspace unavailable
            </span>
        @endif
    </div>

    <div class="alert alert-secondary" role="note">
        <strong>Access basis:</strong>
        {{ $snapshot->dataBasisLabel() }}
    </div>

    @if (
        $snapshot->profileLinked
        && $snapshot->operatorActive
        && $snapshot->hasActiveAssignment()
        && $quickActionOrder === null
    )
        <div class="alert alert-warning" role="alert">
            <strong>No in-progress batch is available for quick entry.</strong>
            Open the operator workspace to review assigned orders. Production
            entry and incident reporting are linked to an active production
            batch; the production supervisor must prepare or start that batch.
        </div>
    @endif

    @if (! $snapshot->profileLinked)
        <div class="alert alert-danger" role="alert">
            <strong>Operator profile not linked.</strong>
            Your login account is not connected to an operator employee
            record. An administrator must complete the account linkage before
            production work can be displayed.
        </div>
    @elseif (! $snapshot->operatorActive)
        <div class="alert alert-danger" role="alert">
            <strong>Operator profile inactive.</strong>
            Your linked operator employee record is inactive. Production work
            and personal operational data are unavailable.
        </div>
    @else
        <div class="app-card bg-white mb-4">
            <div class="border-bottom px-4 py-3">
                <div class="fw-semibold">
                    Operator identity and current assignments
                </div>

                <div class="small text-muted-smartfactory">
                    {{ $snapshot->operatorName }}
                    Â· {{ $snapshot->employeeCode }}
                </div>
            </div>

            @if (! $snapshot->hasActiveAssignment())
                <div class="p-4">
                    <div class="alert alert-warning mb-0" role="alert">
                        <strong>No active assignment.</strong>
                        No production-line and shift assignment is effective
                        for the current date. Contact your supervisor before
                        entering production data.
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Production line</th>
                                <th>Shift</th>
                                <th>Effective from</th>
                                <th>Effective until</th>
                                <th>Assignment</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($snapshot->assignments as $assignment)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $assignment->productionLineName }}
                                        </div>

                                        <div class="small text-muted-smartfactory">
                                            {{ $assignment->productionLineCode }}
                                        </div>
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            {{ $assignment->shiftName }}
                                        </div>

                                        <div class="small text-muted-smartfactory">
                                            {{ $assignment->shiftCode }}
                                        </div>
                                    </td>

                                    <td>{{ $assignment->startsOn }}</td>
                                    <td>{{ $assignment->endsOn ?? 'Open-ended' }}</td>
                                    <td>
                                        @if ($assignment->isPrimary)
                                            <span class="badge text-bg-primary">
                                                Primary
                                            </span>
                                        @else
                                            <span class="badge text-bg-secondary">
                                                Additional
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Personal records
                    </div>

                    <div class="display-6">
                        {{ number_format($snapshot->recordCount) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        {{ number_format($snapshot->draftRecordCount) }}
                        draft /
                        {{ number_format($snapshot->submittedRecordCount) }}
                        submitted
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Awaiting validation
                    </div>

                    <div class="display-6">
                        {{ number_format($snapshot->pendingValidationCount) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        Submitted to the production supervisor
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Validated / rejected
                    </div>

                    <div class="h2 mb-1">
                        {{ number_format($snapshot->validatedRecordCount) }}
                        /
                        {{ number_format($snapshot->rejectedRecordCount) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        Personal validation decisions
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Runtime / downtime
                    </div>

                    <div class="h3 mb-1">
                        {{ $duration($snapshot->runtimeMinutes) }}
                        /
                        {{ $duration($snapshot->downtimeMinutes) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        From your records in the selected period
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Reported downtime
                    </div>

                    <div class="display-6">
                        {{ number_format($snapshot->reportedDowntimeCount) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        Personal downtime reports
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Machine incidents
                    </div>

                    <div class="display-6">
                        {{
                            number_format(
                                $snapshot
                                    ->reportedMachineIncidentCount
                            )
                        }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        Incidents reported by you
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Unresolved reports
                    </div>

                    <div class="display-6">
                        {{ number_format($snapshot->unresolvedEventCount) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        Downtime or machine incidents still open
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="app-card bg-white h-100 p-4">
                    <div class="text-muted-smartfactory">
                        Active assigned work
                    </div>

                    <div class="display-6">
                        {{ number_format(count($snapshot->assignedOrders)) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        Released and in-progress orders shown below
                    </div>
                </div>
            </div>
        </div>

        <div class="app-card bg-white mb-4">
            <div class="border-bottom px-4 py-3">
                <div class="fw-semibold">
                    Personal production quantities
                </div>

                <div class="small text-muted-smartfactory">
                    Units are never combined.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th class="text-end">Records</th>
                            <th class="text-end">Produced</th>
                            <th class="text-end">Good</th>
                            <th class="text-end">Rejected</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($snapshot->quantityUnits as $unit)
                            <tr>
                                <th>{{ $unit->quantityUnit }}</th>
                                <td class="text-end">
                                    {{ number_format($unit->recordCount) }}
                                </td>
                                <td class="text-end">
                                    {{ $unit->producedQuantity }}
                                </td>
                                <td class="text-end">
                                    {{ $unit->goodQuantity }}
                                </td>
                                <td class="text-end">
                                    {{ $unit->rejectedQuantity }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="5"
                                    class="text-center text-muted py-5"
                                >
                                    No personal production record matches the
                                    selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="app-card bg-white mb-4">
            <div class="border-bottom px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">
                        Assigned released and in-progress work
                    </div>

                    <div class="small text-muted-smartfactory">
                        Restricted to your current line and shift assignments.
                    </div>
                </div>

                <a
                    href="{{ route('production.operator.index') }}"
                    class="btn btn-sm btn-outline-primary"
                >
                    View complete workspace
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Product</th>
                            <th>Line / shift</th>
                            <th>Status</th>
                            <th>Planned start</th>
                            <th class="text-end">Target</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($snapshot->assignedOrders as $order)
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $order->orderNumber }}
                                    </div>

                                    <div class="small text-muted-smartfactory">
                                        Priority {{ $order->priority }}
                                    </div>
                                </td>

                                <td>{{ $order->productName }}</td>

                                <td>
                                    {{ $order->productionLineName }}
                                    <div class="small text-muted-smartfactory">
                                        {{ $order->shiftName ?? 'Any assigned shift' }}
                                    </div>
                                </td>

                                <td>
                                    <span class="badge {{
                                        $statusClass($order->status)
                                    }}">
                                        {{ str($order->status)->headline() }}
                                    </span>
                                </td>

                                <td>{{ $order->plannedStartAt }}</td>

                                <td class="text-end">
                                    {{ $order->targetQuantity }}
                                    {{ $order->quantityUnit }}
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a
                                            href="{{
                                                route(
                                                    'production.operator.orders.show',
                                                    $order->id
                                                )
                                            }}"
                                            class="btn btn-sm btn-outline-secondary"
                                        >
                                            Open
                                        </a>

                                        @if ($order->hasActionableBatch())
                                            <a
                                                href="{{
                                                    route(
                                                        'production.operator.records.create',
                                                        $order->actionBatchId
                                                    )
                                                }}"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Enter production
                                            </a>

                                            <a
                                                href="{{
                                                    route(
                                                        'production.operator.events.create',
                                                        $order->actionBatchId
                                                    )
                                                }}"
                                                class="btn btn-sm btn-outline-danger"
                                            >
                                                Report incident
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="7"
                                    class="text-center text-muted py-5"
                                >
                                    No released or in-progress order is
                                    assigned to your current line and shift.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="app-card bg-white mb-4">
            <div class="border-bottom px-4 py-3">
                <div class="fw-semibold">
                    Recent personal production records
                </div>

                <div class="small text-muted-smartfactory">
                    Most recent records in the selected period.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Line / shift</th>
                            <th class="text-end">Produced</th>
                            <th>Status</th>
                            <th>Validation</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($snapshot->recentRecords as $record)
                            <tr>
                                <th>{{ $record->recordNumber }}</th>
                                <td>{{ $record->productionDate }}</td>
                                <td>{{ $record->productName }}</td>
                                <td>
                                    {{ $record->productionLineName }}
                                    <div class="small text-muted-smartfactory">
                                        {{ $record->shiftName }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    {{ $record->producedQuantity }}
                                    {{ $record->quantityUnit }}
                                </td>
                                <td>
                                    <span class="badge {{
                                        $statusClass($record->status)
                                    }}">
                                        {{ str($record->status)->headline() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{
                                        $statusClass(
                                            $record->validationStatus
                                        )
                                    }}">
                                        {{
                                            str(
                                                $record->validationStatus
                                            )->headline()
                                        }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a
                                        href="{{
                                            route(
                                                'production.operator.records.show',
                                                $record->id
                                            )
                                        }}"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="8"
                                    class="text-center text-muted py-5"
                                >
                                    No personal production record matches the
                                    selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="app-card bg-white mb-4">
            <div class="border-bottom px-4 py-3">
                <div class="fw-semibold">
                    Recent personal downtime and machine incidents
                </div>

                <div class="small text-muted-smartfactory">
                    Only events reported by or attributed to your operator
                    profile are displayed.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Line / machine</th>
                            <th>Started</th>
                            <th class="text-end">Duration</th>
                            <th>Status</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($snapshot->recentEvents as $event)
                            <tr>
                                <th>{{ $event->eventNumber }}</th>
                                <td>{{ $event->title }}</td>
                                <td>
                                    {{ str($event->eventType)->headline() }}
                                </td>
                                <td>
                                    {{ $event->productionLineName }}
                                    <div class="small text-muted-smartfactory">
                                        {{ $event->machineName ?? 'No machine selected' }}
                                    </div>
                                </td>
                                <td>{{ $event->startedAt }}</td>
                                <td class="text-end">
                                    {{ $duration($event->durationMinutes) }}
                                </td>
                                <td>
                                    @if ($event->isResolved)
                                        <span class="badge text-bg-success">
                                            Resolved
                                        </span>
                                    @else
                                        <span class="badge text-bg-warning">
                                            Open
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a
                                        href="{{
                                            route(
                                                'production.operator.events.show',
                                                $event->id
                                            )
                                        }}"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="8"
                                    class="text-center text-muted py-5"
                                >
                                    No personal downtime or machine incident
                                    matches the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
