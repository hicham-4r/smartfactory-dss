@extends('layouts.app')

@section('title', 'Production Event')

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
                {{ $productionEvent->event_number }}
            </h1>

            <div class="text-muted">
                {{ $productionEvent->event_type->label() }}
                · {{ $productionEvent->severity->label() }}
                · Version {{ $productionEvent->lock_version }}
            </div>
        </div>

        <a
            href="{{
                route(
                    'production.supervisor.batches.show',
                    $productionEvent->productionBatch
                )
            }}"
            class="btn btn-outline-secondary"
        >
            Back to batch
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Title</dt>
                <dd class="col-md-9">
                    {{ $productionEvent->title }}
                </dd>

                <dt class="col-md-3">Description</dt>
                <dd class="col-md-9">
                    {{ $productionEvent->description ?? 'None' }}
                </dd>

                <dt class="col-md-3">Order</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent
                            ->productionBatch
                            ->productionOrder
                            ->order_number
                    }}
                </dd>

                <dt class="col-md-3">Batch</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent
                            ->productionBatch
                            ->batch_number
                    }}
                </dd>

                <dt class="col-md-3">Line</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent
                            ->productionLine
                            ?->name
                        ?? $productionEvent
                            ->productionLine
                            ?->code
                        ?? 'Line #'
                            .$productionEvent
                                ->production_line_id
                    }}
                </dd>

                <dt class="col-md-3">Machine</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent->machine?->name
                        ?? $productionEvent->machine?->code
                        ?? 'No machine selected'
                    }}
                </dd>

                <dt class="col-md-3">Reported by</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent->reportedBy?->name
                        ?? 'User #'.$productionEvent->reported_by
                    }}
                </dd>

                <dt class="col-md-3">Started</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent
                            ->started_at
                            ?->format('Y-m-d H:i')
                    }}
                </dd>

                <dt class="col-md-3">Ended</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent
                            ->ended_at
                            ?->format('Y-m-d H:i')
                        ?? 'Open event'
                    }}
                </dd>

                <dt class="col-md-3">Duration</dt>
                <dd class="col-md-9">
                    @if (
                        $productionEvent
                            ->duration_minutes
                        !== null
                    )
                        {{
                            $productionEvent
                                ->duration_minutes
                        }}
                        minutes
                    @else
                        Not calculated
                    @endif
                </dd>

                <dt class="col-md-3">Resolution</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent->is_resolved
                        ? 'Resolved'
                        : 'Open'
                    }}
                </dd>
            </dl>
        </div>
    </div>

    @can('resolve', $productionEvent)
        <form
            method="POST"
            action="{{
                route(
                    'production.supervisor.events.resolve',
                    $productionEvent
                )
            }}"
            class="card shadow-sm"
        >
            @csrf

            <input
                type="hidden"
                name="lock_version"
                value="{{ $productionEvent->lock_version }}"
            >

            <div class="card-header fw-semibold">
                Resolve event
            </div>

            <div class="card-body">
                Resolving the event records your identity and the current
                resolution timestamp in the audit trail.
            </div>

            <div class="card-footer text-end">
                <button
                    type="submit"
                    class="btn btn-success"
                >
                    Mark as resolved
                </button>
            </div>
        </form>
    @endcan
</div>
@endsection