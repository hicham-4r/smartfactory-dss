@extends('layouts.app')

@section('title', 'ERP synchronization monitoring')

@section('content')
    @php
        $summary = $health->summary;
        $latestRun = $health->latestRun;

        $healthBadge = match ($health->status) {
            'healthy' => 'text-bg-success',
            'degraded' => 'text-bg-warning',
            default => 'text-bg-danger',
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
                Secure ERP integration
            </p>

            <h1 class="h3 fw-bold mb-2">
                ERP synchronization monitoring
            </h1>

            <p class="text-muted-smartfactory mb-0">
                Health, resource freshness, sanitized failures and
                synchronization history for the simulated Sage source.
            </p>
        </div>

        <a
            href="{{ route('admin.dashboard') }}"
            class="btn btn-outline-secondary"
        >
            Back to administration
        </a>
    </div>

    @include('admin.erp-monitoring.partials.manual-sync')

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Current health
                </div>

                <span class="badge {{ $healthBadge }}">
                    {{ strtoupper($health->status) }}
                </span>

                <div class="small text-muted mt-3">
                    Generated
                    {{ $health->generatedAt->format('Y-m-d H:i:s') }}
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Resource checkpoints
                </div>

                <div class="h4 fw-bold mb-1">
                    {{
                        $summary['registered_states'] ?? 0
                    }}
                    /
                    {{
                        $summary['expected_resources'] ?? 0
                    }}
                </div>

                <div class="small text-muted">
                    External ERP resources only
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Stale checkpoints
                </div>

                <div class="h4 fw-bold mb-1">
                    {{ $summary['stale_states'] ?? 0 }}
                </div>

                <div class="small text-muted">
                    Threshold:
                    {{ $health->staleAfterMinutes }}
                    minutes
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="small text-muted mb-2">
                    Latest successful run
                </div>

                <div class="fw-semibold">
                    {{
                        $summary['last_successful_run_at']
                        ?? 'Never'
                    }}
                </div>

                <div class="small text-muted mt-2">
                    {{
                        $summary['minutes_since_success']
                        ?? 'Unknown'
                    }}
                    minute(s) ago
                </div>
            </div>
        </div>
    </div>

    @if ($health->reasons !== [])
        <div class="alert alert-warning mb-4">
            <div class="fw-semibold mb-2">
                Health findings
            </div>

            <ul class="mb-0">
                @foreach ($health->reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card bg-white p-4 mb-4">
        <div
            class="d-flex flex-column flex-lg-row
                   justify-content-between gap-3 mb-3"
        >
            <div>
                <h2 class="h5 fw-bold mb-1">
                    Resource freshness
                </h2>

                <p class="small text-muted mb-0">
                    Local run_logs telemetry is intentionally excluded.
                </p>
            </div>

            <div class="small text-muted">
                Active locks:
                {{ $summary['locked_states'] ?? 0 }}
                · Stale locks:
                {{ $summary['stale_locks'] ?? 0 }}
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>Resource</th>
                    <th>Freshness</th>
                    <th>Last success</th>
                    <th>Age</th>
                    <th>Failures</th>
                    <th>Lock</th>
                    <th>Last error</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($health->resources as $resource)
                    @php
                        $freshnessBadge = match (
                            $resource['freshness'] ?? 'never'
                        ) {
                            'fresh' => 'text-bg-success',
                            'stale' => 'text-bg-warning',
                            default => 'text-bg-secondary',
                        };
                    @endphp

                    <tr>
                        <td class="fw-semibold">
                            {{ $resource['resource'] }}
                        </td>

                        <td>
                            <span
                                class="badge {{ $freshnessBadge }}"
                            >
                                {{
                                    strtoupper(
                                        $resource['freshness']
                                        ?? 'never'
                                    )
                                }}
                            </span>
                        </td>

                        <td>
                            {{
                                $resource[
                                    'last_successful_sync_at'
                                ] ?? 'Never'
                            }}
                        </td>

                        <td>
                            {{
                                $resource[
                                    'minutes_since_success'
                                ] ?? '—'
                            }}
                            min
                        </td>

                        <td>
                            {{
                                $resource[
                                    'consecutive_failures'
                                ] ?? 0
                            }}
                        </td>

                        <td>
                            @if ($resource['stale_lock'] ?? false)
                                <span class="badge text-bg-danger">
                                    Stale
                                </span>
                            @elseif ($resource['locked'] ?? false)
                                <span class="badge text-bg-primary">
                                    Active
                                </span>
                            @else
                                <span
                                    class="badge text-bg-light border"
                                >
                                    None
                                </span>
                            @endif
                        </td>

                        <td>
                            {{
                                $resource[
                                    'last_error_code'
                                ] ?? '—'
                            }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No ERP resource checkpoints are available.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($health->failures !== [])
        <div class="app-card bg-white p-4 mb-4">
            <h2 class="h5 fw-bold mb-1">
                Recent sanitized failures
            </h2>

            <p class="small text-muted mb-3">
                Tokens, authorization headers, payloads, cursors and
                internal diagnostic contexts are never displayed.
            </p>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Occurred at</th>
                        <th>Resource</th>
                        <th>Stage</th>
                        <th>Error code</th>
                        <th>Retryable</th>
                        <th>Message</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach ($health->failures as $failure)
                        <tr>
                            <td>
                                {{
                                    $failure['occurred_at']
                                    ?? '—'
                                }}
                            </td>

                            <td>
                                {{ $failure['resource'] }}
                            </td>

                            <td>
                                {{ $failure['stage'] }}
                            </td>

                            <td>
                                <code>
                                    {{ $failure['error_code'] }}
                                </code>
                            </td>

                            <td>
                                {{
                                    ($failure['retryable'] ?? false)
                                        ? 'Yes'
                                        : 'No'
                                }}
                            </td>

                            <td>
                                {{ $failure['error_message'] }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="app-card bg-white p-4 mb-4">
        <h2 class="h5 fw-bold mb-3">
            Synchronization history
        </h2>

        <form method="GET" class="row g-3">
            <div class="col-lg-3">
                <label
                    for="source_system"
                    class="form-label"
                >
                    Source system
                </label>

                <input
                    type="text"
                    id="source_system"
                    name="source_system"
                    value="{{ $filters['source_system'] }}"
                    class="form-control"
                    maxlength="50"
                >
            </div>

            <div class="col-lg-3">
                <label for="status" class="form-label">
                    Run status
                </label>

                <select
                    id="status"
                    name="status"
                    class="form-select"
                >
                    <option value="all">
                        All statuses
                    </option>

                    @foreach (
                        \App\Enums\ERP\ErpSyncRunStatus::cases()
                        as $status
                    )
                        <option
                            value="{{ $status->value }}"
                            @selected(
                                $filters['status']
                                === $status->value
                            )
                        >
                            {{
                                \Illuminate\Support\Str::headline(
                                    $status->value
                                )
                            }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-3">
                <label for="trigger" class="form-label">
                    Trigger
                </label>

                <select
                    id="trigger"
                    name="trigger"
                    class="form-select"
                >
                    <option value="all">
                        All triggers
                    </option>

                    @foreach (
                        \App\Enums\ERP\ErpSyncTrigger::cases()
                        as $trigger
                    )
                        <option
                            value="{{ $trigger->value }}"
                            @selected(
                                $filters['trigger']
                                === $trigger->value
                            )
                        >
                            {{
                                \Illuminate\Support\Str::headline(
                                    $trigger->value
                                )
                            }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-2">
                <label for="per_page" class="form-label">
                    Per page
                </label>

                <select
                    id="per_page"
                    name="per_page"
                    class="form-select"
                >
                    @foreach ([10, 25, 50] as $size)
                        <option
                            value="{{ $size }}"
                            @selected(
                                $filters['per_page']
                                === $size
                            )
                        >
                            {{ $size }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div
                class="col-lg-1 d-flex align-items-end"
            >
                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >
                    Apply
                </button>
            </div>
        </form>
    </div>

    <div class="app-card bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th class="ps-4">Started</th>
                    <th>Status</th>
                    <th>Trigger</th>
                    <th>Resources</th>
                    <th>Fetched</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Skipped</th>
                    <th>Failed</th>
                    <th class="pe-4">Action</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($runs as $run)
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
                            'completed_with_errors' =>
                                'text-bg-warning',
                            'failed' => 'text-bg-danger',
                            'running' => 'text-bg-primary',
                            default => 'text-bg-secondary',
                        };
                    @endphp

                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold">
                                {{
                                    $run->started_at
                                        ?->format(
                                            'Y-m-d H:i:s'
                                        )
                                    ?? '—'
                                }}
                            </div>

                            <div class="small text-muted">
                                {{ $run->run_uuid }}
                            </div>
                        </td>

                        <td>
                            <span class="badge {{ $runBadge }}">
                                {{
                                    \Illuminate\Support\Str
                                        ::headline(
                                            $statusValue
                                        )
                                }}
                            </span>
                        </td>

                        <td>
                            {{
                                \Illuminate\Support\Str
                                    ::headline(
                                        $triggerValue
                                    )
                            }}
                        </td>

                        <td>
                            {{ $run->resources_count }}
                        </td>

                        <td>{{ $run->records_fetched }}</td>
                        <td>{{ $run->records_created }}</td>
                        <td>{{ $run->records_updated }}</td>
                        <td>{{ $run->records_skipped }}</td>
                        <td>
                            {{ $run->records_failed }}
                            @if ($run->failures_count > 0)
                                <span
                                    class="badge text-bg-danger ms-1"
                                >
                                    {{ $run->failures_count }}
                                </span>
                            @endif
                        </td>

                        <td class="pe-4">
                            <a
                                href="{{
                                    route(
                                        'admin.erp-monitoring.runs.show',
                                        $run
                                    )
                                }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="10"
                            class="text-center text-muted py-5"
                        >
                            No synchronization runs match the filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($runs->hasPages())
            <div class="border-top p-3">
                {{ $runs->links() }}
            </div>
        @endif
    </div>
@endsection
