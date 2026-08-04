@extends('layouts.app')

@section('title', 'Production Supervisor')

@section('content')
<div class="container py-4">
    @include(
        'production.supervisor.partials.alerts'
    )

    <div
        class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"
    >
        <div>
            <h1 class="h3 mb-1">
                Production Supervisor
            </h1>

            <p class="text-muted mb-0">
                Manage orders, batches, validations and operational events.
            </p>
        </div>

        <div class="d-flex gap-2">
            @can(
                'create',
                \App\Models\ProductionOrder::class
            )
                <a
                    href="{{
                        route(
                            'production.supervisor.orders.create'
                        )
                    }}"
                    class="btn btn-primary"
                >
                    New production order
                </a>
            @endcan

            <a
                href="{{ route('dashboard') }}"
                class="btn btn-outline-secondary"
            >
                Dashboard
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Open orders
                    </div>

                    <div class="display-6">
                        {{ $summary['open_orders'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        In progress
                    </div>

                    <div class="display-6">
                        {{ $summary['in_progress_orders'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Pending validations
                    </div>

                    <div class="display-6">
                        {{ $summary['pending_validations'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Open critical events
                    </div>

                    <div class="display-6">
                        {{ $summary['critical_events'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        All unresolved events
                    </div>

                    <div class="display-6">
                        {{ $summary['unresolved_events'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            Production orders
        </div>

        <div class="card-body">
            <form
                method="GET"
                action="{{
                    route(
                        'production.supervisor.index'
                    )
                }}"
                class="row g-3 mb-4"
            >
                <div class="col-md-4">
                    <label
                        for="search"
                        class="form-label"
                    >
                        Search
                    </label>

                    <input
                        id="search"
                        name="search"
                        class="form-control"
                        maxlength="100"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Order, product or code"
                    >
                </div>

                <div class="col-md-2">
                    <label
                        for="status"
                        class="form-label"
                    >
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >
                        <option value="">All</option>

                        @foreach ($orderStatuses as $status)
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

                        @foreach ($productionLines as $line)
                            <option
                                value="{{ $line->id }}"
                                @selected(
                                    (string) (
                                        $filters[
                                            'production_line_id'
                                        ] ?? ''
                                    )
                                    === (string) $line->id
                                )
                            >
                                {{
                                    $line->name
                                    ?? $line->code
                                    ?? 'Line #'.$line->id
                                }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button
                        class="btn btn-primary w-100"
                        type="submit"
                    >
                        Filter orders
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
                            <th>Target</th>
                            <th>Status</th>
                            <th>Start</th>
                            <th>Batches</th>
                            <th></th>
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
                                        $order->product?->name
                                        ?? $order->product?->code
                                        ?? 'Product #'.$order->product_id
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
                                        ?? 'Line #'
                                            .$order
                                                ->production_line_id
                                    }}
                                </td>

                                <td>
                                    {{
                                        $order->shift?->name
                                        ?? $order->shift?->code
                                        ?? 'Any'
                                    }}
                                </td>

                                <td>
                                    {{ $order->target_quantity }}
                                    {{ $order->quantity_unit }}
                                </td>

                                <td>
                                    {{ $order->status->label() }}
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
                                                'production.supervisor.orders.show',
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
                                    colspan="9"
                                    class="text-center text-muted py-4"
                                >
                                    No production orders were found.
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
            Pending production-record validations
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Order</th>
                            <th>Operator</th>
                            <th>Line</th>
                            <th>Date</th>
                            <th>Produced</th>
                            <th>Submitted</th>
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
                                            ->productionBatch
                                            ->productionOrder
                                            ->order_number
                                    }}
                                </td>

                                <td>
                                    {{
                                        $record->operator?->name
                                        ?? $record->operator?->employee_number
                                        ?? 'Operator #'.$record->operator_id
                                    }}
                                </td>

                                <td>
                                    {{
                                        $record
                                            ->productionLine
                                            ?->name
                                        ?? $record
                                            ->productionLine
                                            ?->code
                                        ?? 'Line #'
                                            .$record
                                                ->production_line_id
                                    }}
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
                                    {{ $record->quantity_unit }}
                                </td>

                                <td>
                                    {{
                                        $record
                                            ->submitted_at
                                            ?->format('Y-m-d H:i')
                                    }}
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-success"
                                        href="{{
                                            route(
                                                'production.supervisor.records.show',
                                                $record
                                            )
                                        }}"
                                    >
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >
                                    There are no pending validations.
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
            Unresolved production events
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Line</th>
                            <th>Machine</th>
                            <th>Title</th>
                            <th>Started</th>
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
                                    {{
                                        $event
                                            ->productionLine
                                            ?->name
                                        ?? $event
                                            ->productionLine
                                            ?->code
                                        ?? 'Line #'
                                            .$event
                                                ->production_line_id
                                    }}
                                </td>

                                <td>
                                    {{
                                        $event->machine?->name
                                        ?? $event->machine?->code
                                        ?? '—'
                                    }}
                                </td>

                                <td>{{ $event->title }}</td>

                                <td>
                                    {{
                                        $event
                                            ->started_at
                                            ?->format('Y-m-d H:i')
                                    }}
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-danger"
                                        href="{{
                                            route(
                                                'production.supervisor.events.show',
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
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >
                                    No unresolved events were found.
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