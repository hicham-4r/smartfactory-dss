@extends('layouts.app')

@section('title', 'ERP synchronization run')

@section('content')
    @php
        $statusValue =
            $run->status instanceof \BackedEnum
                ? $run->status->value
                : (string) $run->status;

        $triggerValue =
            $run->trigger instanceof \BackedEnum
                ? $run->trigger->value
                : (string) $run->trigger;

        $runBadge = match ($statusValue) {
            'completed' => 'text-bg-success',
            'completed_with_errors' => 'text-bg-warning',
            'failed' => 'text-bg-danger',
            'running' => 'text-bg-primary',
            default => 'text-bg-secondary',
        };
    @endphp

    <div
        class="d-flex flex-column flex-lg-row
               justify-content-between align-items-lg-center
               gap-3 mb-4"
    >
        <div>
            <p
                class="text-uppercase small fw-semibold
                       text-secondary mb-2"
            >
                ERP synchronization run
            </p>

            <h1 class="h3 fw-bold mb-2">
                {{ $run->run_uuid }}
            </h1>

            <span class="badge {{ $runBadge }}">
                {{
                    \Illuminate\Support\Str::headline(
                        $statusValue
                    )
                }}
            </span>
        </div>

        <a
            href="{{ route('admin.erp-monitoring.index') }}"
            class="btn btn-outline-secondary"
        >
            Back to monitoring
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Source and trigger
                </div>

                <div class="fw-semibold">
                    {{ $run->source_system }}
                </div>

                <div class="small text-muted">
                    {{
                        \Illuminate\Support\Str::headline(
                            $triggerValue
                        )
                    }}
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Started
                </div>

                <div class="fw-semibold">
                    {{
                        $run->started_at
                            ?->format('Y-m-d H:i:s')
                        ?? '—'
                    }}
                </div>

                <div class="small text-muted">
                    Finished:
                    {{
                        $run->finished_at
                            ?->format('Y-m-d H:i:s')
                        ?? 'In progress'
                    }}
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Records
                </div>

                <div>
                    Fetched:
                    <strong>{{ $run->records_fetched }}</strong>
                </div>

                <div>
                    Mapped:
                    <strong>{{ $run->records_mapped }}</strong>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Result counters
                </div>

                <div>Created: {{ $run->records_created }}</div>
                <div>Updated: {{ $run->records_updated }}</div>
                <div>Skipped: {{ $run->records_skipped }}</div>
                <div>Failed: {{ $run->records_failed }}</div>
            </div>
        </div>
    </div>

    <div class="app-card bg-white p-4 mb-4">
        <h2 class="h5 fw-bold mb-3">
            Execution context
        </h2>

        <dl class="row mb-0">
            <dt class="col-sm-3">
                Initiated by
            </dt>

            <dd class="col-sm-9">
                @if ($run->initiatedBy)
                    {{ $run->initiatedBy->name }}
                    <span class="text-muted">
                        ({{ $run->initiatedBy->email }})
                    </span>
                @else
                    Scheduler or system process
                @endif
            </dd>

            <dt class="col-sm-3">
                Request ID
            </dt>

            <dd class="col-sm-9">
                {{ $run->request_id ?? 'Not supplied' }}
            </dd>

            <dt class="col-sm-3">
                Requested resources
            </dt>

            <dd class="col-sm-9">
                {{
                    implode(
                        ', ',
                        $run->requested_resources
                        ?? []
                    )
                }}
            </dd>

            <dt class="col-sm-3">
                Run error
            </dt>

            <dd class="col-sm-9">
                @if ($run->error_code)
                    <code>{{ $run->error_code }}</code>
                    —
                    {{ $run->error_message }}
                @else
                    None
                @endif
            </dd>
        </dl>
    </div>

    <div class="app-card bg-white overflow-hidden mb-4">
        <div class="p-4 border-bottom">
            <h2 class="h5 fw-bold mb-1">
                Resource results
            </h2>

            <p class="small text-muted mb-0">
                Counters and terminal status for every resource in
                this synchronization run.
            </p>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th class="ps-4">Resource</th>
                    <th>Status</th>
                    <th>Pages</th>
                    <th>Fetched</th>
                    <th>Mapped</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Skipped</th>
                    <th>Failed</th>
                    <th class="pe-4">Error</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($run->resources as $resource)
                    @php
                        $resourceValue =
                            $resource->resource
                                instanceof \BackedEnum
                                ? $resource->resource->value
                                : (string) $resource->resource;

                        $resourceStatus =
                            $resource->status
                                instanceof \BackedEnum
                                ? $resource->status->value
                                : (string) $resource->status;

                        $resourceBadge =
                            match ($resourceStatus) {
                                'completed' =>
                                    'text-bg-success',

                                'failed' =>
                                    'text-bg-danger',

                                'running' =>
                                    'text-bg-primary',

                                default =>
                                    'text-bg-secondary',
                            };
                    @endphp

                    <tr>
                        <td class="ps-4 fw-semibold">
                            {{ $resourceValue }}
                        </td>

                        <td>
                            <span
                                class="badge {{ $resourceBadge }}"
                            >
                                {{
                                    \Illuminate\Support\Str
                                        ::headline(
                                            $resourceStatus
                                        )
                                }}
                            </span>
                        </td>

                        <td>
                            {{ $resource->pages_processed }}
                        </td>

                        <td>
                            {{ $resource->records_fetched }}
                        </td>

                        <td>
                            {{ $resource->records_mapped }}
                        </td>

                        <td>
                            {{ $resource->records_created }}
                        </td>

                        <td>
                            {{ $resource->records_updated }}
                        </td>

                        <td>
                            {{ $resource->records_skipped }}
                        </td>

                        <td>
                            {{ $resource->records_failed }}
                        </td>

                        <td class="pe-4">
                            @if ($resource->error_code)
                                <code>
                                    {{ $resource->error_code }}
                                </code>

                                <div class="small text-muted">
                                    {{ $resource->error_message }}
                                </div>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="10"
                            class="text-center text-muted py-5"
                        >
                            This run has no resource rows.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="app-card bg-white overflow-hidden">
        <div class="p-4 border-bottom">
            <h2 class="h5 fw-bold mb-1">
                Sanitized failures
            </h2>

            <p class="small text-muted mb-0">
                The interface never loads or renders internal diagnostic
                contexts, payloads, credentials, authorization
                headers or encrypted resume cursors.
            </p>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th class="ps-4">Occurred</th>
                    <th>Resource</th>
                    <th>Stage</th>
                    <th>External ID</th>
                    <th>Page</th>
                    <th>Error code</th>
                    <th>Retryable</th>
                    <th class="pe-4">Message</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($run->failures as $failure)
                    @php
                        $failureResource =
                            $failure->resource
                                instanceof \BackedEnum
                                ? $failure->resource->value
                                : (string) $failure->resource;

                        $failureStage =
                            $failure->stage
                                instanceof \BackedEnum
                                ? $failure->stage->value
                                : (string) $failure->stage;
                    @endphp

                    <tr>
                        <td class="ps-4">
                            {{
                                $failure->occurred_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    )
                                ?? '—'
                            }}
                        </td>

                        <td>{{ $failureResource }}</td>
                        <td>{{ $failureStage }}</td>
                        <td>
                            {{ $failure->external_id ?? '—' }}
                        </td>
                        <td>{{ $failure->page ?? '—' }}</td>
                        <td>
                            <code>
                                {{ $failure->error_code }}
                            </code>
                        </td>
                        <td>
                            {{ $failure->retryable ? 'Yes' : 'No' }}
                        </td>
                        <td class="pe-4">
                            {{ $failure->error_message }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="8"
                            class="text-center text-muted py-5"
                        >
                            This run contains no recorded failures.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
