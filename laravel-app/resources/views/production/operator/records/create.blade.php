@extends('layouts.app')

@section('title', 'New Production Record')

@section('content')
<div class="container py-4">
    @include(
        'production.operator.partials.alerts'
    )

    <div class="d-flex justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">
                New production record
            </h1>

            <div class="text-muted">
                Batch {{ $productionBatch->batch_number }}
            </div>
        </div>

        <a
            href="{{
                route(
                    'production.operator.batches.show',
                    $productionBatch
                )
            }}"
            class="btn btn-outline-secondary"
        >
            Cancel
        </a>
    </div>

    <form
        method="POST"
        action="{{
            route(
                'production.operator.records.store',
                $productionBatch
            )
        }}"
        class="card shadow-sm"
    >
        @csrf

        <div class="card-body">
            <div class="alert alert-info">
                Quantity unit:
                <strong>
                    {{ $productionBatch->quantity_unit }}
                </strong>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label
                        for="shift_id"
                        class="form-label"
                    >
                        Assignment / shift
                    </label>

                    <select
                        id="shift_id"
                        name="shift_id"
                        class="form-select @error('shift_id') is-invalid @enderror"
                        required
                    >
                        @foreach ($assignments as $assignment)
                            <option
                                value="{{ $assignment->shift_id }}"
                                @selected(
                                    (string) old('shift_id')
                                    === (string) $assignment->shift_id
                                )
                            >
                                {{
                                    $assignment
                                        ->productionLine
                                        ?->name
                                    ?? 'Line #'
                                        .$assignment
                                            ->production_line_id
                                }}
                                —
                                {{
                                    $assignment
                                        ->shift
                                        ?->name
                                    ?? 'Shift #'
                                        .$assignment->shift_id
                                }}
                            </option>
                        @endforeach
                    </select>

                    @error('shift_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label
                        for="production_date"
                        class="form-label"
                    >
                        Production date
                    </label>

                    <input
                        id="production_date"
                        name="production_date"
                        type="date"
                        class="form-control @error('production_date') is-invalid @enderror"
                        value="{{
                            old(
                                'production_date',
                                now()->toDateString()
                            )
                        }}"
                        required
                    >

                    @error('production_date')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label
                        for="started_at"
                        class="form-label"
                    >
                        Started at
                    </label>

                    <input
                        id="started_at"
                        name="started_at"
                        type="datetime-local"
                        class="form-control @error('started_at') is-invalid @enderror"
                        value="{{ old('started_at') }}"
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="ended_at"
                        class="form-label"
                    >
                        Ended at
                    </label>

                    <input
                        id="ended_at"
                        name="ended_at"
                        type="datetime-local"
                        class="form-control @error('ended_at') is-invalid @enderror"
                        value="{{ old('ended_at') }}"
                    >
                </div>

                <div class="col-md-4">
                    <label
                        for="produced_quantity"
                        class="form-label"
                    >
                        Produced quantity
                    </label>

                    <input
                        id="produced_quantity"
                        name="produced_quantity"
                        type="number"
                        min="0"
                        step="0.001"
                        class="form-control @error('produced_quantity') is-invalid @enderror"
                        value="{{ old('produced_quantity', '0.000') }}"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label
                        for="good_quantity"
                        class="form-label"
                    >
                        Good quantity
                    </label>

                    <input
                        id="good_quantity"
                        name="good_quantity"
                        type="number"
                        min="0"
                        step="0.001"
                        class="form-control @error('good_quantity') is-invalid @enderror"
                        value="{{ old('good_quantity', '0.000') }}"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label
                        for="rejected_quantity"
                        class="form-label"
                    >
                        Rejected quantity
                    </label>

                    <input
                        id="rejected_quantity"
                        name="rejected_quantity"
                        type="number"
                        min="0"
                        step="0.001"
                        class="form-control @error('rejected_quantity') is-invalid @enderror"
                        value="{{ old('rejected_quantity', '0.000') }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="runtime_minutes"
                        class="form-label"
                    >
                        Runtime minutes
                    </label>

                    <input
                        id="runtime_minutes"
                        name="runtime_minutes"
                        type="number"
                        min="0"
                        max="1440"
                        class="form-control @error('runtime_minutes') is-invalid @enderror"
                        value="{{ old('runtime_minutes', 0) }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="downtime_minutes"
                        class="form-label"
                    >
                        Downtime minutes
                    </label>

                    <input
                        id="downtime_minutes"
                        name="downtime_minutes"
                        type="number"
                        min="0"
                        max="1440"
                        class="form-control @error('downtime_minutes') is-invalid @enderror"
                        value="{{ old('downtime_minutes', 0) }}"
                        required
                    >
                </div>

                <div class="col-12">
                    <label
                        for="notes"
                        class="form-label"
                    >
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        maxlength="5000"
                        rows="4"
                        class="form-control @error('notes') is-invalid @enderror"
                    >{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card-footer text-end">
            <button
                type="submit"
                class="btn btn-primary"
            >
                Save draft
            </button>
        </div>
    </form>
</div>
@endsection