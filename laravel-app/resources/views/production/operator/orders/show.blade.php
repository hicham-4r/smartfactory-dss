@extends('layouts.app')

@section('title', 'Production Order')

@section('content')
<div class="container py-4">
    @include(
        'production.operator.partials.alerts'
    )

    <div class="d-flex justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">
                {{ $productionOrder->order_number }}
            </h1>

            <div class="text-muted">
                {{ $productionOrder->status->label() }}
            </div>
        </div>

        <a
            href="{{ route('production.operator.index') }}"
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
                        $productionOrder
                            ->product
                            ?->name
                        ?? $productionOrder
                            ->product
                            ?->code
                        ?? 'Product #'
                            .$productionOrder->product_id
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
                        ?? '#'
                            .$productionOrder
                                ->production_line_id
                    }}
                </dd>

                <dt class="col-md-3">Shift</dt>
                <dd class="col-md-9">
                    {{
                        $productionOrder
                            ->shift
                            ?->name
                        ?? $productionOrder
                            ->shift
                            ?->code
                        ?? 'Any assigned shift'
                    }}
                </dd>

                <dt class="col-md-3">Target</dt>
                <dd class="col-md-9">
                    {{ $productionOrder->target_quantity }}
                    {{ $productionOrder->quantity_unit }}
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

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            Batches
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Planned quantity</th>
                            <th>Scheduled start</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($productionOrder->batches as $batch)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $batch->batch_number }}
                                </td>

                                <td>{{ $batch->sequence_number }}</td>

                                <td>{{ $batch->status->label() }}</td>

                                <td>
                                    {{ $batch->planned_quantity }}
                                    {{ $batch->quantity_unit }}
                                </td>

                                <td>
                                    {{
                                        $batch
                                            ->scheduled_start_at
                                            ?->format('Y-m-d H:i')
                                        ?? 'Not scheduled'
                                    }}
                                </td>

                                <td class="text-end">
                                    <div
                                        class="d-inline-flex flex-wrap justify-content-end gap-1"
                                    >
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
                                                class="btn btn-sm btn-success"
                                            >
                                                Enter production
                                            </a>
                                        @endcan

                                        @if (
                                            in_array(
                                                $batch->status,
                                                [
                                                    \App\Enums\Production\ProductionBatchStatus::Ready,
                                                    \App\Enums\Production\ProductionBatchStatus::InProgress,
                                                    \App\Enums\Production\ProductionBatchStatus::Blocked,
                                                ],
                                                true
                                            )
                                        )
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
                                                    class="btn btn-sm btn-danger"
                                                >
                                                    Machine not working
                                                </a>
                                            @endcan
                                        @endif

                                        <a
                                            href="{{
                                                route(
                                                    'production.operator.batches.show',
                                                    $batch
                                                )
                                            }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Open batch
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="6"
                                    class="text-center text-muted py-4"
                                >
                                    No production batches are available.
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
