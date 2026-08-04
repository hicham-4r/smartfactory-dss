@extends('layouts.app')

@section('title', 'Production Record')

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
                {{ $productionRecord->record_number }}
            </h1>

            <div class="text-muted">
                {{ $productionRecord->status->label() }}
                —
                {{
                    $productionRecord
                        ->validation_status
                        ->label()
                }}
            </div>
        </div>

        <div class="d-flex gap-2">
            @can('submit', $productionRecord)
                <form
                    method="POST"
                    action="{{
                        route(
                            'production.operator.records.submit',
                            $productionRecord
                        )
                    }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="lock_version"
                        value="{{ $productionRecord->lock_version }}"
                    >

                    <button
                        type="submit"
                        class="btn btn-success"
                    >
                        Submit for validation
                    </button>
                </form>
            @endcan

            <a
                href="{{
                    route(
                        'production.operator.batches.show',
                        $productionRecord->productionBatch
                    )
                }}"
                class="btn btn-outline-secondary"
            >
                Back
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Batch</dt>
                <dd class="col-md-9">
                    {{
                        $productionRecord
                            ->productionBatch
                            ->batch_number
                    }}
                </dd>

                <dt class="col-md-3">Production date</dt>
                <dd class="col-md-9">
                    {{
                        $productionRecord
                            ->production_date
                            ?->format('Y-m-d')
                    }}
                </dd>

                <dt class="col-md-3">Produced quantity</dt>
                <dd class="col-md-9">
                    {{ $productionRecord->produced_quantity }}
                    {{ $productionRecord->quantity_unit }}
                </dd>

                <dt class="col-md-3">Good quantity</dt>
                <dd class="col-md-9">
                    {{ $productionRecord->good_quantity }}
                </dd>

                <dt class="col-md-3">Rejected quantity</dt>
                <dd class="col-md-9">
                    {{ $productionRecord->rejected_quantity }}
                </dd>

                <dt class="col-md-3">Runtime</dt>
                <dd class="col-md-9">
                    {{ $productionRecord->runtime_minutes }} minutes
                </dd>

                <dt class="col-md-3">Downtime</dt>
                <dd class="col-md-9">
                    {{ $productionRecord->downtime_minutes }} minutes
                </dd>

                <dt class="col-md-3">Timeline</dt>
                <dd class="col-md-9">
                    {{
                        $productionRecord
                            ->started_at
                            ?->format('Y-m-d H:i')
                        ?? 'Not entered'
                    }}
                    –
                    {{
                        $productionRecord
                            ->ended_at
                            ?->format('Y-m-d H:i')
                        ?? 'Not entered'
                    }}
                </dd>

                <dt class="col-md-3">Notes</dt>
                <dd class="col-md-9">
                    {{ $productionRecord->notes ?? 'None' }}
                </dd>

                <dt class="col-md-3">Lock version</dt>
                <dd class="col-md-9">
                    {{ $productionRecord->lock_version }}
                </dd>
            </dl>
        </div>
    </div>
</div>
@endsection