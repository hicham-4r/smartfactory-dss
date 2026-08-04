@extends('layouts.app')

@section('title', 'Production Event')

@section('content')
<div class="container py-4">
    @include(
        'production.operator.partials.alerts'
    )

    <div class="d-flex justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">
                {{ $productionEvent->event_number }}
            </h1>

            <div class="text-muted">
                {{ $productionEvent->event_type->label() }}
                —
                {{ $productionEvent->severity->label() }}
            </div>
        </div>

        <a
            href="{{
                route(
                    'production.operator.batches.show',
                    $productionEvent->productionBatch
                )
            }}"
            class="btn btn-outline-secondary"
        >
            Back
        </a>
    </div>

    <div class="card shadow-sm">
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

                <dt class="col-md-3">Machine</dt>
                <dd class="col-md-9">
                    {{
                        $productionEvent
                            ->machine
                            ?->name
                        ?? $productionEvent
                            ->machine
                            ?->code
                        ?? 'No machine selected'
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
                    {{
                        $productionEvent
                            ->duration_minutes
                        ?? 'Not calculated'
                    }}
                    @if (
                        $productionEvent
                            ->duration_minutes
                        !== null
                    )
                        minutes
                    @endif
                </dd>

                <dt class="col-md-3">Resolution status</dt>
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
</div>
@endsection