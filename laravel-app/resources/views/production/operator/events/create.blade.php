@extends('layouts.app')

@section('title', 'Report Production Event')

@php
    $allowedEventValues = $eventTypes
        ->map(
            static fn (
                \App\Enums\Production\ProductionEventType $type
            ): string => $type->value
        )
        ->all();

    $requestedEventType = request()->query(
        'event_type'
    );

    $selectedEventType = old(
        'event_type',
        is_string($requestedEventType)
        && in_array(
            $requestedEventType,
            $allowedEventValues,
            true
        )
            ? $requestedEventType
            : $eventTypes->first()?->value
    );

    $defaultTitle = match ($selectedEventType) {
        \App\Enums\Production\ProductionEventType::MachineIncident->value =>
            'Machine not working',

        \App\Enums\Production\ProductionEventType::Downtime->value =>
            'Production downtime',

        \App\Enums\Production\ProductionEventType::Comment->value =>
            'Operator comment',

        default => '',
    };
@endphp

@section('content')
<div class="container py-4">
    @include(
        'production.operator.partials.alerts'
    )

    <div class="d-flex justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">
                Report production event
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
                'production.operator.events.store',
                $productionBatch
            )
        }}"
        class="card shadow-sm"
    >
        @csrf

        <div class="card-body">
            <div class="alert alert-info" role="note">
                <strong>Assigned work only.</strong>
                This report will be attached to batch
                {{ $productionBatch->batch_number }} and its production line.
                Machine choices are limited to that line. Operators can report
                events but cannot resolve or close them.
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
                        class="form-select"
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
                </div>

                <div class="col-md-6">
                    <label
                        for="event_type"
                        class="form-label"
                    >
                        Event type
                    </label>

                    <select
                        id="event_type"
                        name="event_type"
                        class="form-select"
                        required
                    >
                        @foreach ($eventTypes as $type)
                            <option
                                value="{{ $type->value }}"
                                @selected(
                                    $selectedEventType
                                    === $type->value
                                )
                            >
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label
                        for="severity"
                        class="form-label"
                    >
                        Severity
                    </label>

                    <select
                        id="severity"
                        name="severity"
                        class="form-select"
                        required
                    >
                        @foreach ($severities as $severity)
                            <option
                                value="{{ $severity->value }}"
                                @selected(
                                    old('severity')
                                    === $severity->value
                                )
                            >
                                {{ $severity->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label
                        for="machine_id"
                        class="form-label"
                    >
                        Machine
                    </label>

                    <select
                        id="machine_id"
                        name="machine_id"
                        class="form-select"
                    >
                        <option value="">
                            No machine selected
                        </option>

                        @foreach ($machines as $machine)
                            <option
                                value="{{ $machine->id }}"
                                @selected(
                                    (string) old('machine_id')
                                    === (string) $machine->id
                                )
                            >
                                {{
                                    $machine->name
                                    ?? $machine->code
                                    ?? 'Machine #'.$machine->id
                                }}
                            </option>
                        @endforeach
                    </select>

                    <div class="form-text">
                        Required for a machine incident.
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="production_record_id"
                        class="form-label"
                    >
                        Related production record
                    </label>

                    <select
                        id="production_record_id"
                        name="production_record_id"
                        class="form-select"
                    >
                        <option value="">
                            No related record
                        </option>

                        @foreach ($records as $record)
                            <option
                                value="{{ $record->id }}"
                                @selected(
                                    (string) old(
                                        'production_record_id'
                                    )
                                    === (string) $record->id
                                )
                            >
                                {{ $record->record_number }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label
                        for="title"
                        class="form-label"
                    >
                        Title
                    </label>

                    <input
                        id="title"
                        name="title"
                        type="text"
                        maxlength="180"
                        class="form-control"
                        value="{{ old('title', $defaultTitle) }}"
                        required
                    >
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
                        class="form-control"
                        value="{{
                            old(
                                'started_at',
                                now()->format('Y-m-d\TH:i')
                            )
                        }}"
                        required
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
                        class="form-control"
                        value="{{ old('ended_at') }}"
                    >
                </div>

                <div class="col-12">
                    <label
                        for="description"
                        class="form-label"
                    >
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        maxlength="5000"
                        rows="5"
                        class="form-control"
                    >{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card-footer text-end">
            <button
                type="submit"
                class="btn btn-danger"
            >
                Report event
            </button>
        </div>
    </form>
</div>
@endsection
