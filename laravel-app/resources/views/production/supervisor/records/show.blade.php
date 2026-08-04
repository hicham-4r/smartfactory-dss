@extends('layouts.app')

@section('title', 'Review Production Record')

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
                {{ $productionRecord->record_number }}
            </h1>

            <div class="text-muted">
                {{ $productionRecord->status->label() }}
                ·
                {{
                    $productionRecord
                        ->validation_status
                        ->label()
                }}
                · Version {{ $productionRecord->lock_version }}
            </div>
        </div>

        <a
            href="{{
                route(
                    'production.supervisor.batches.show',
                    $productionRecord->productionBatch
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
                <dt class="col-md-3">Order</dt>
                <dd class="col-md-9">
                    {{
                        $productionRecord
                            ->productionBatch
                            ->productionOrder
                            ->order_number
                    }}
                </dd>

                <dt class="col-md-3">Batch</dt>
                <dd class="col-md-9">
                    {{
                        $productionRecord
                            ->productionBatch
                            ->batch_number
                    }}
                </dd>

                <dt class="col-md-3">Operator</dt>
                <dd class="col-md-9">
                    {{
                        $productionRecord->operator?->name
                        ?? $productionRecord->operator?->employee_number
                        ?? 'Operator #'.$productionRecord->operator_id
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
            </dl>
        </div>
    </div>

    @if ($productionRecord->canBeValidated())
        <div class="row g-4">
            @can('validate', $productionRecord)
                <div class="col-lg-6">
                    <form
                        method="POST"
                        action="{{
                            route(
                                'production.supervisor.records.decide',
                                $productionRecord
                            )
                        }}"
                        class="card shadow-sm h-100"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="decision"
                            value="validated"
                        >

                        <input
                            type="hidden"
                            name="lock_version"
                            value="{{ $productionRecord->lock_version }}"
                        >

                        <div class="card-header fw-semibold text-success">
                            Validate record
                        </div>

                        <div class="card-body">
                            <label
                                for="validation_reason"
                                class="form-label"
                            >
                                Optional validation note
                            </label>

                            <textarea
                                id="validation_reason"
                                name="reason"
                                maxlength="2000"
                                rows="4"
                                class="form-control"
                            ></textarea>
                        </div>

                        <div class="card-footer text-end">
                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                Validate and lock
                            </button>
                        </div>
                    </form>
                </div>
            @endcan

            @can('reject', $productionRecord)
                <div class="col-lg-6">
                    <form
                        method="POST"
                        action="{{
                            route(
                                'production.supervisor.records.decide',
                                $productionRecord
                            )
                        }}"
                        class="card shadow-sm h-100"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="decision"
                            value="rejected"
                        >

                        <input
                            type="hidden"
                            name="lock_version"
                            value="{{ $productionRecord->lock_version }}"
                        >

                        <div class="card-header fw-semibold text-danger">
                            Reject record
                        </div>

                        <div class="card-body">
                            <label
                                for="rejection_reason"
                                class="form-label"
                            >
                                Rejection reason
                            </label>

                            <textarea
                                id="rejection_reason"
                                name="reason"
                                maxlength="2000"
                                rows="4"
                                class="form-control"
                                required
                            ></textarea>
                        </div>

                        <div class="card-footer text-end">
                            <button
                                type="submit"
                                class="btn btn-danger"
                            >
                                Reject for correction
                            </button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>
    @else
        <div class="alert alert-info">
            This production record is not awaiting a supervisor decision.
        </div>
    @endif

    @if ($productionRecord->validations->isNotEmpty())
        <div class="card shadow-sm mt-4">
            <div class="card-header fw-semibold">
                Decision history
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Decision</th>
                                <th>Reviewer</th>
                                <th>Version</th>
                                <th>Reason</th>
                                <th>Decided at</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach (
                                $productionRecord->validations
                                as $validation
                            )
                                <tr>
                                    <td>
                                        {{
                                            $validation
                                                ->decision
                                                ->label()
                                        }}
                                    </td>

                                    <td>
                                        {{
                                            $validation
                                                ->decidedBy
                                                ?->name
                                            ?? 'User #'
                                                .$validation
                                                    ->decided_by
                                        }}
                                    </td>

                                    <td>
                                        {{ $validation->record_version }}
                                    </td>

                                    <td>
                                        {{
                                            $validation
                                                ->decision_reason
                                            ?? '—'
                                        }}
                                    </td>

                                    <td>
                                        {{
                                            $validation
                                                ->decided_at
                                                ?->format('Y-m-d H:i')
                                        }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection