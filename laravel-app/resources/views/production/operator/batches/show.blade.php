@extends('layouts.app')

@section('title', 'Production Batch')

@section('content')
<div class="container py-4">
    @include(
        'production.operator.partials.alerts'
    )

    <div
        class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"
    >
        <div>
            <h1 class="h3 mb-1">
                {{ $productionBatch->batch_number }}
            </h1>

            <div class="text-muted">
                {{ $productionBatch->status->label() }}
            </div>
        </div>

        <div class="d-flex gap-2">
            @can(
                'create',
                [
                    \App\Models\ProductionRecord::class,
                    $productionBatch
                ]
            )
                <a
                    href="{{
                        route(
                            'production.operator.records.create',
                            $productionBatch
                        )
                    }}"
                    class="btn btn-primary"
                >
                    New production record
                </a>
            @endcan

            <a
                href="{{
                    route(
                        'production.operator.events.create',
                        $productionBatch
                    )
                }}"
                class="btn btn-outline-danger"
            >
                Report event
            </a>

            <a
                href="{{
                    route(
                        'production.operator.orders.show',
                        $productionBatch->productionOrder
                    )
                }}"
                class="btn btn-outline-secondary"
            >
                Back
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Order</dt>
                <dd class="col-md-9">
                    {{
                        $productionBatch
                            ->productionOrder
                            ->order_number
                    }}
                </dd>

                <dt class="col-md-3">Product</dt>
                <dd class="col-md-9">
                    {{
                        $productionBatch
                            ->productionOrder
                            ->product
                            ?->name
                        ?? $productionBatch
                            ->productionOrder
                            ->product
                            ?->code
                        ?? 'Product'
                    }}
                </dd>

                <dt class="col-md-3">Planned quantity</dt>
                <dd class="col-md-9">
                    {{ $productionBatch->planned_quantity }}
                    {{ $productionBatch->quantity_unit }}
                </dd>

                <dt class="col-md-3">Actual good quantity</dt>
                <dd class="col-md-9">
                    {{ $productionBatch->actual_good_quantity }}
                </dd>

                <dt class="col-md-3">Actual rejected quantity</dt>
                <dd class="col-md-9">
                    {{ $productionBatch->actual_rejected_quantity }}
                </dd>
            </dl>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            My records for this batch
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Date</th>
                            <th>Produced</th>
                            <th>Status</th>
                            <th>Validation</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ $record->record_number }}</td>

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
                                        href="{{
                                            route(
                                                'production.operator.records.show',
                                                $record
                                            )
                                        }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="6"
                                    class="text-center text-muted py-4"
                                >
                                    No records have been created by you.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            My events for this batch
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
                            <th>Resolved</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td>{{ $event->event_number }}</td>

                                <td>
                                    {{ $event->event_type->label() }}
                                </td>

                                <td>
                                    {{ $event->severity->label() }}
                                </td>

                                <td>{{ $event->title }}</td>

                                <td>
                                    {{ $event->is_resolved ? 'Yes' : 'No' }}
                                </td>

                                <td class="text-end">
                                    <a
                                        href="{{
                                            route(
                                                'production.operator.events.show',
                                                $event
                                            )
                                        }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="6"
                                    class="text-center text-muted py-4"
                                >
                                    No events have been reported by you.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection