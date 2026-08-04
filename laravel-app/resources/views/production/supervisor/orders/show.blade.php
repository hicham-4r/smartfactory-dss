@extends('layouts.app')

@section('title', 'Production Order')

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
                {{ $productionOrder->order_number }}
            </h1>

            <div class="text-muted">
                {{ $productionOrder->status->label() }}
                · Version {{ $productionOrder->lock_version }}
            </div>
        </div>

        <a
            href="{{
                route(
                    'production.supervisor.index'
                )
            }}"
            class="btn btn-outline-secondary"
        >
            Back
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Product</dt>
                <dd class="col-md-9">
                    {{
                        $productionOrder->product?->name
                        ?? $productionOrder->product?->code
                        ?? 'Product #'.$productionOrder->product_id
                    }}
                </dd>

                <dt class="col-md-3">Production line</dt>
                <dd class="col-md-9">
                    {{
                        $productionOrder
                            ->productionLine
                            ?->name
                        ?? $productionOrder
                            ->productionLine
                            ?->code
                        ?? 'Line #'
                            .$productionOrder
                                ->production_line_id
                    }}
                </dd>

                <dt class="col-md-3">Shift</dt>
                <dd class="col-md-9">
                    {{
                        $productionOrder->shift?->name
                        ?? $productionOrder->shift?->code
                        ?? 'Any eligible shift'
                    }}
                </dd>

                <dt class="col-md-3">Target</dt>
                <dd class="col-md-9">
                    {{ $productionOrder->target_quantity }}
                    {{ $productionOrder->quantity_unit }}
                </dd>

                <dt class="col-md-3">Priority</dt>
                <dd class="col-md-9">
                    {{ $productionOrder->priority }}
                </dd>

                <dt class="col-md-3">Planned window</dt>
                <dd class="col-md-9">
                    {{
                        $productionOrder
                            ->planned_start_at
                            ?->format('Y-m-d H:i')
                    }}

                    @if ($productionOrder->planned_end_at)
                        –
                        {{
                            $productionOrder
                                ->planned_end_at
                                ->format('Y-m-d H:i')
                        }}
                    @endif
                </dd>

                <dt class="col-md-3">Instructions</dt>
                <dd class="col-md-9">
                    {{ $productionOrder->instructions ?? 'None' }}
                </dd>
            </dl>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            Order actions
        </div>

        <div class="card-body d-flex flex-wrap gap-2">
            @if (
                $productionOrder->status
                === \App\Enums\Production\ProductionOrderStatus::Draft
            )
                @can('update', $productionOrder)
                    <form
                        method="POST"
                        action="{{
                            route(
                                'production.supervisor.orders.transition',
                                $productionOrder
                            )
                        }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="target_status"
                            value="planned"
                        >

                        <input
                            type="hidden"
                            name="lock_version"
                            value="{{ $productionOrder->lock_version }}"
                        >

                        <button
                            class="btn btn-primary"
                            type="submit"
                        >
                            Move to planned
                        </button>
                    </form>
                @endcan
            @endif

            @if (
                $productionOrder->status
                === \App\Enums\Production\ProductionOrderStatus::Planned
            )
                @can('release', $productionOrder)
                    <form
                        method="POST"
                        action="{{
                            route(
                                'production.supervisor.orders.transition',
                                $productionOrder
                            )
                        }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="target_status"
                            value="released"
                        >

                        <input
                            type="hidden"
                            name="lock_version"
                            value="{{ $productionOrder->lock_version }}"
                        >

                        <button
                            class="btn btn-success"
                            type="submit"
                        >
                            Release order
                        </button>
                    </form>
                @endcan
            @endif

            @can('cancel', $productionOrder)
                <form
                    method="POST"
                    action="{{
                        route(
                            'production.supervisor.orders.transition',
                            $productionOrder
                        )
                    }}"
                    onsubmit="return confirm('Cancel this production order?');"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="target_status"
                        value="cancelled"
                    >

                    <input
                        type="hidden"
                        name="lock_version"
                        value="{{ $productionOrder->lock_version }}"
                    >

                    <button
                        class="btn btn-outline-danger"
                        type="submit"
                    >
                        Cancel order
                    </button>
                </form>
            @endcan
        </div>
    </div>

    @if (
        in_array(
            $productionOrder->status,
            [
                \App\Enums\Production\ProductionOrderStatus::Released,
                \App\Enums\Production\ProductionOrderStatus::InProgress,
            ],
            true
        )
    )
        @can(
            'create',
            [
                \App\Models\ProductionBatch::class,
                $productionOrder
            ]
        )
            <form
                method="POST"
                action="{{
                    route(
                        'production.supervisor.batches.store',
                        $productionOrder
                    )
                }}"
                class="card shadow-sm mb-4"
            >
                @csrf

                <div class="card-header fw-semibold">
                    Create production batch
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label
                                for="planned_quantity"
                                class="form-label"
                            >
                                Planned quantity
                            </label>

                            <input
                                id="planned_quantity"
                                name="planned_quantity"
                                type="number"
                                min="0.001"
                                step="0.001"
                                class="form-control"
                                required
                            >

                            <div class="form-text">
                                Unit:
                                {{ $productionOrder->quantity_unit }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label
                                for="scheduled_start_at"
                                class="form-label"
                            >
                                Scheduled start
                            </label>

                            <input
                                id="scheduled_start_at"
                                name="scheduled_start_at"
                                type="datetime-local"
                                class="form-control"
                            >
                        </div>
                    </div>
                </div>

                <div class="card-footer text-end">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Create batch
                    </button>
                </div>
            </form>
        @endcan
    @endif

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            Production batches
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Planned</th>
                            <th>Good</th>
                            <th>Rejected</th>
                            <th>Records</th>
                            <th>Events</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($productionOrder->batches as $batch)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $batch->batch_number }}
                                </td>

                                <td>
                                    {{ $batch->sequence_number }}
                                </td>

                                <td>
                                    {{ $batch->status->label() }}
                                </td>

                                <td>
                                    {{ $batch->planned_quantity }}
                                </td>

                                <td>
                                    {{ $batch->actual_good_quantity }}
                                </td>

                                <td>
                                    {{ $batch->actual_rejected_quantity }}
                                </td>

                                <td>
                                    {{ $batch->records_count }}
                                </td>

                                <td>
                                    {{ $batch->events_count }}
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{
                                            route(
                                                'production.supervisor.batches.show',
                                                $batch
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
                                    No production batches exist.
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