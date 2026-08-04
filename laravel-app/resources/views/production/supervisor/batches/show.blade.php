@extends('layouts.app')

@section('title', 'Production Batch')

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
                {{ $productionBatch->batch_number }}
            </h1>

            <div class="text-muted">
                {{ $productionBatch->status->label() }}
                · Version {{ $productionBatch->lock_version }}
            </div>
        </div>

        <a
            href="{{
                route(
                    'production.supervisor.orders.show',
                    $productionBatch->productionOrder
                )
            }}"
            class="btn btn-outline-secondary"
        >
            Back to order
        </a>
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

                <dt class="col-md-3">Good quantity</dt>
                <dd class="col-md-9">
                    {{ $productionBatch->actual_good_quantity }}
                </dd>

                <dt class="col-md-3">Rejected quantity</dt>
                <dd class="col-md-9">
                    {{ $productionBatch->actual_rejected_quantity }}
                </dd>

                <dt class="col-md-3">Actual start</dt>
                <dd class="col-md-9">
                    {{
                        $productionBatch
                            ->actual_start_at
                            ?->format('Y-m-d H:i')
                        ?? 'Not started'
                    }}
                </dd>

                <dt class="col-md-3">Actual end</dt>
                <dd class="col-md-9">
                    {{
                        $productionBatch
                            ->actual_end_at
                            ?->format('Y-m-d H:i')
                        ?? 'Not completed'
                    }}
                </dd>
            </dl>
        </div>
    </div>

    @can('transition', $productionBatch)
        @if (! $productionBatch->status->isTerminal())
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    Batch lifecycle actions
                </div>

                <div class="card-body d-flex flex-wrap gap-2">
                    @foreach (
                        $productionBatch
                            ->status
                            ->allowedTransitions()
                        as $target
                    )
                        <form
                            method="POST"
                            action="{{
                                route(
                                    'production.supervisor.batches.transition',
                                    $productionBatch
                                )
                            }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="target_status"
                                value="{{ $target->value }}"
                            >

                            <input
                                type="hidden"
                                name="lock_version"
                                value="{{ $productionBatch->lock_version }}"
                            >

                            <button
                                type="submit"
                                class="{{
                                    $target
                                        === \App\Enums\Production\ProductionBatchStatus::Cancelled
                                        ? 'btn btn-outline-danger'
                                        : 'btn btn-primary'
                                }}"
                            >
                                Move to {{ $target->label() }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    @endcan

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            Production records
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Operator</th>
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
                        @forelse ($productionBatch->records as $record)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $record->record_number }}
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
                                                'production.supervisor.records.show',
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
                                    colspan="9"
                                    class="text-center text-muted py-4"
                                >
                                    No production records exist.
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
            Production events
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Machine</th>
                            <th>Title</th>
                            <th>Resolved</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($productionBatch->events as $event)
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
                                        $event->machine?->name
                                        ?? $event->machine?->code
                                        ?? '—'
                                    }}
                                </td>

                                <td>{{ $event->title }}</td>

                                <td>
                                    {{ $event->is_resolved ? 'Yes' : 'No' }}
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-primary"
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
                                    colspan="7"
                                    class="text-center text-muted py-4"
                                >
                                    No production events exist.
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