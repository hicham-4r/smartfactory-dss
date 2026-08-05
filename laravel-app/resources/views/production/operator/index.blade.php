@extends('layouts.app')

@section('title', 'Operator Production')

@section('content')
<div class="container py-4">
    @include(
        'production.operator.partials.alerts'
    )

    <div
        class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"
    >
        <div>
            <h1 class="h3 mb-1">
                Operator Production
            </h1>

            <p class="text-muted mb-0">
                Assigned orders, production records and reported events.
            </p>
        </div>

        <a
            href="{{ route('dashboard') }}"
            class="btn btn-outline-secondary"
        >
            Back to dashboard
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            Current assignments
        </div>

        <div class="card-body">
            @forelse ($assignments as $assignment)
                <div class="border rounded p-3 mb-2">
                    <div class="fw-semibold">
                        {{
                            $assignment
                                ->productionLine
                                ?->name
                            ?? $assignment
                                ->productionLine
                                ?->code
                            ?? 'Production line #'
                                .$assignment
                                    ->production_line_id
                        }}
                    </div>

                    <div class="small text-muted">
                        Shift:
                        {{
                            $assignment
                                ->shift
                                ?->name
                            ?? $assignment
                                ->shift
                                ?->code
                            ?? '#'
                                .$assignment->shift_id
                        }}
                    </div>
                </div>
            @empty
                <div class="alert alert-warning mb-0">
                    No active assignment exists for
                    {{ $referenceDate->format('Y-m-d') }}.
                </div>
            @endforelse
        </div>
    </div>


    @php
        $hasQuickActionBatch = false;
    @endphp

    <div class="card shadow-sm border-primary mb-4">
        <div
            class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center gap-2"
        >
            <div>
                <div class="fw-semibold">
                    Quick operator actions
                </div>

                <div class="small opacity-75">
                    Enter production data or report a line problem without
                    searching through several pages.
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3">
                @foreach ($orders as $order)
                    @foreach ($order->batches as $batch)
                        @php
                            $hasQuickActionBatch = true;
                        @endphp

                        <div class="col-12 col-xl-6">
                            <div class="border rounded h-100 p-3">
                                <div
                                    class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"
                                >
                                    <div>
                                        <div class="fw-semibold">
                                            {{ $batch->batch_number }}
                                        </div>

                                        <div class="small text-muted">
                                            {{ $order->order_number }}
                                            ·
                                            {{
                                                $order->product?->name
                                                ?? $order->product?->code
                                                ?? 'Product'
                                            }}
                                        </div>
                                    </div>

                                    <span class="badge text-bg-secondary">
                                        {{ $batch->status->label() }}
                                    </span>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    @can(
                                        'create',
                                        [
                                            \App\Models\ProductionRecord::class,
                                            $batch
                                        ]
                                    )
                                        <a
                                            href="{{
                                                route(
                                                    'production.operator.records.create',
                                                    $batch
                                                )
                                            }}"
                                            class="btn btn-success"
                                        >
                                            Enter production data
                                        </a>
                                    @endcan

                                    @can(
                                        'report',
                                        [
                                            \App\Models\ProductionEvent::class,
                                            \App\Enums\Production\ProductionEventType::MachineIncident
                                        ]
                                    )
                                        <a
                                            href="{{
                                                route(
                                                    'production.operator.events.create',
                                                    [
                                                        'productionBatch' => $batch,
                                                        'event_type' => \App\Enums\Production\ProductionEventType::MachineIncident->value,
                                                    ]
                                                )
                                            }}"
                                            class="btn btn-danger"
                                        >
                                            Report machine not working
                                        </a>
                                    @endcan

                                    @can(
                                        'report',
                                        [
                                            \App\Models\ProductionEvent::class,
                                            \App\Enums\Production\ProductionEventType::Downtime
                                        ]
                                    )
                                        <a
                                            href="{{
                                                route(
                                                    'production.operator.events.create',
                                                    [
                                                        'productionBatch' => $batch,
                                                        'event_type' => \App\Enums\Production\ProductionEventType::Downtime->value,
                                                    ]
                                                )
                                            }}"
                                            class="btn btn-outline-danger"
                                        >
                                            Report downtime
                                        </a>
                                    @endcan

                                    <a
                                        href="{{
                                            route(
                                                'production.operator.batches.show',
                                                $batch
                                            )
                                        }}"
                                        class="btn btn-outline-secondary"
                                    >
                                        Open batch
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>

            @if (! $hasQuickActionBatch)
                <div class="alert alert-warning mb-0" role="alert">
                    <strong>No reportable production batch is available.</strong>
                    An operator action must be linked to assigned work that is
                    ready, in progress, or blocked. Ask the production
                    supervisor to release or start a batch, then refresh this
                    workspace.
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            Assigned production orders
        </div>

        <div class="card-body">
            <form
                method="GET"
                action="{{ route('production.operator.index') }}"
                class="row g-3 mb-4"
            >
                <div class="col-md-4">
                    <label
                        class="form-label"
                        for="search"
                    >
                        Order number
                    </label>

                    <input
                        id="search"
                        name="search"
                        type="text"
                        maxlength="100"
                        class="form-control"
                        value="{{ $filters['search'] ?? '' }}"
                    >
                </div>

                <div class="col-md-3">
                    <label
                        class="form-label"
                        for="status"
                    >
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >
                        <option value="">
                            Open orders
                        </option>

                        @foreach (
                            \App\Enums\Production\ProductionOrderStatus::cases()
                            as $status
                        )
                            <option
                                value="{{ $status->value }}"
                                @selected(
                                    ($filters['status'] ?? null)
                                    === $status->value
                                )
                            >
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label
                        class="form-label"
                        for="reference_date"
                    >
                        Assignment date
                    </label>

                    <input
                        id="reference_date"
                        name="reference_date"
                        type="date"
                        class="form-control"
                        value="{{
                            $filters['reference_date']
                            ?? $referenceDate->format('Y-m-d')
                        }}"
                    >
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Filter
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Product</th>
                            <th>Line</th>
                            <th>Shift</th>
                            <th>Status</th>
                            <th>Planned start</th>
                            <th>Batches</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $order->order_number }}
                                </td>

                                <td>
                                    {{
                                        $order
                                            ->product
                                            ?->name
                                        ?? $order
                                            ->product
                                            ?->code
                                        ?? 'Product #'
                                            .$order->product_id
                                    }}
                                </td>

                                <td>
                                    {{
                                        $order
                                            ->productionLine
                                            ?->name
                                        ?? $order
                                            ->productionLine
                                            ?->code
                                        ?? '#'
                                            .$order
                                                ->production_line_id
                                    }}
                                </td>

                                <td>
                                    {{
                                        $order
                                            ->shift
                                            ?->name
                                        ?? $order
                                            ->shift
                                            ?->code
                                        ?? 'Any assigned shift'
                                    }}
                                </td>

                                <td>
                                    <span class="badge bg-secondary">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>

                                <td>
                                    {{
                                        $order
                                            ->planned_start_at
                                            ?->format('Y-m-d H:i')
                                    }}
                                </td>

                                <td>
                                    {{ $order->batches_count }}
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{
                                            route(
                                                'production.operator.orders.show',
                                                $order
                                            )
                                        }}"
                                    >
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >
                                    No assigned production orders were found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links() }}
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            My production records
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Date</th>
                            <th>Produced</th>
                            <th>Good</th>
                            <th>Rejected</th>
                            <th>Status</th>
                            <th>Validation</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $record->record_number }}
                                </td>

                                <td>
                                    {{
                                        $record
                                            ->production_date
                                            ?->format('Y-m-d')
                                    }}
                                </td>

                                <td>
                                    {{ $record->produced_quantity }}
                                </td>

                                <td>
                                    {{ $record->good_quantity }}
                                </td>

                                <td>
                                    {{ $record->rejected_quantity }}
                                </td>

                                <td>
                                    {{ $record->status->label() }}
                                </td>

                                <td>
                                    {{
                                        $record
                                            ->validation_status
                                            ->label()
                                    }}
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{
                                            route(
                                                'production.operator.records.show',
                                                $record
                                            )
                                        }}"
                                    >
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >
                                    You have not created any production records.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $records->links() }}
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            My reported events
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Title</th>
                            <th>Started</th>
                            <th>Resolved</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $event->event_number }}
                                </td>

                                <td>
                                    {{ $event->event_type->label() }}
                                </td>

                                <td>
                                    {{ $event->severity->label() }}
                                </td>

                                <td>
                                    {{ $event->title }}
                                </td>

                                <td>
                                    {{
                                        $event
                                            ->started_at
                                            ?->format('Y-m-d H:i')
                                    }}
                                </td>

                                <td>
                                    {{ $event->is_resolved ? 'Yes' : 'No' }}
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{
                                            route(
                                                'production.operator.events.show',
                                                $event
                                            )
                                        }}"
                                    >
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="7"
                                    class="text-center text-muted py-4"
                                >
                                    No reported production events.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $events->links() }}
        </div>
    </div>
</div>
@endsection
