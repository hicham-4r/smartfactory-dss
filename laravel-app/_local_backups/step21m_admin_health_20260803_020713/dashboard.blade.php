@extends('layouts.app')

@section('title', 'Administrator operations')

@section('content')
    @php
        $overallStatus = $operations->overallStatus();

        $overallBadge = match ($overallStatus) {
            'critical' => 'text-bg-danger',
            'warning' => 'text-bg-warning',
            default => 'text-bg-success',
        };

        $healthBadge = static fn (?string $status): string =>
            match ($status) {
                'available', 'healthy' => 'text-bg-success',
                'degraded', 'not_implemented',
                'not_monitored' => 'text-bg-warning',
                default => 'text-bg-danger',
            };

        $alertClass = static fn (string $severity): string =>
            match ($severity) {
                'critical' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info',
            };
    @endphp

    <div class="row g-4">
        <div class="col-12">
            <div class="app-card bg-white p-4">
                <div
                    class="d-flex flex-column flex-lg-row
                           justify-content-between gap-3"
                >
                    <div>
                        <p
                            class="text-uppercase small fw-semibold
                                   text-secondary mb-2"
                        >
                            Secure administration
                        </p>

                        <h1 class="h3 fw-bold mb-2">
                            Administrator operations dashboard
                        </h1>

                        <p class="text-muted-smartfactory mb-0">
                            User readiness, Operator assignments,
                            ERP synchronization, queue state and
                            sanitized audit activity.
                        </p>
                    </div>

                    <div class="text-lg-end">
                        <span class="badge {{ $overallBadge }}">
                            {{ ucfirst($overallStatus) }}
                        </span>

                        <div
                            class="small text-muted-smartfactory mt-2"
                        >
                            Generated
                            {{
                                $operations->generatedAt
                                    ->timezone(
                                        config(
                                            'app.timezone',
                                            'UTC'
                                        )
                                    )
                                    ->format('Y-m-d H:i:s')
                            }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($operations->alerts !== [])
            <div class="col-12">
                <div class="app-card bg-white p-4">
                    <h2 class="h5 fw-bold mb-3">
                        System alerts
                    </h2>

                    <div class="d-grid gap-2">
                        @foreach ($operations->alerts as $alert)
                            <div
                                class="alert {{
                                    $alertClass(
                                        $alert['severity']
                                    )
                                }} mb-0"
                                role="alert"
                            >
                                <div class="fw-semibold">
                                    {{ $alert['title'] }}
                                </div>

                                <div class="small">
                                    {{ $alert['message'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <h2 class="h5 fw-bold mb-0">
                Access and workforce readiness
            </h2>
        </div>

        @foreach ([
            [
                'label' => 'Active users',
                'value' => $operations->users['active'],
                'hint' => $operations->users['total'].' total accounts',
            ],
            [
                'label' => 'Inactive users',
                'value' => $operations->users['inactive'],
                'hint' => 'Authentication disabled',
            ],
            [
                'label' => 'Password changes pending',
                'value' => $operations->users['must_change_password'],
                'hint' => 'Temporary password still active',
            ],
            [
                'label' => 'Locked accounts',
                'value' => $operations->users['locked'],
                'hint' => 'Temporary authentication lock',
            ],
            [
                'label' => 'Operator accounts unlinked',
                'value' => $operations->users['operator_accounts_without_profile'],
                'hint' => 'No ERP Operator profile',
            ],
            [
                'label' => 'Active Operators unassigned',
                'value' => $operations->operators['active_without_current_assignment'],
                'hint' => 'No current line and shift',
            ],
        ] as $metric)
            <div class="col-md-6 col-xl-4">
                <div class="app-card bg-white h-100 p-4">
                    <div
                        class="small text-uppercase fw-semibold
                               text-secondary"
                    >
                        {{ $metric['label'] }}
                    </div>

                    <div class="display-6 fw-bold my-2">
                        {{ number_format($metric['value']) }}
                    </div>

                    <div class="small text-muted-smartfactory">
                        {{ $metric['hint'] }}
                    </div>
                </div>
            </div>
        @endforeach

        @include(
            'admin.partials.operations-charts',
            ['operations' => $operations]
        )

        <div class="col-12">
            <h2 class="h5 fw-bold mb-0">
                Application and queue health
            </h2>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="fw-semibold mb-2">
                    Database
                </div>

                <span
                    class="badge {{
                        $healthBadge(
                            $operations
                                ->applicationHealth[
                                    'database'
                                ]['status']
                        )
                    }}"
                >
                    {{
                        ucfirst(
                            $operations
                                ->applicationHealth[
                                    'database'
                                ]['status']
                        )
                    }}
                </span>

                <div class="small text-muted-smartfactory mt-2">
                    @if (
                        $operations
                            ->applicationHealth[
                                'database'
                            ]['latency_ms'] !== null
                    )
                        {{
                            $operations
                                ->applicationHealth[
                                    'database'
                                ]['latency_ms']
                        }}
                        ms
                    @else
                        No latency measurement
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="fw-semibold mb-2">
                    Cache
                </div>

                <span
                    class="badge {{
                        $healthBadge(
                            $operations
                                ->applicationHealth[
                                    'cache'
                                ]['status']
                        )
                    }}"
                >
                    {{
                        ucfirst(
                            $operations
                                ->applicationHealth[
                                    'cache'
                                ]['status']
                        )
                    }}
                </span>

                <div class="small text-muted-smartfactory mt-2">
                    @if (
                        $operations
                            ->applicationHealth[
                                'cache'
                            ]['latency_ms'] !== null
                    )
                        {{
                            $operations
                                ->applicationHealth[
                                    'cache'
                                ]['latency_ms']
                        }}
                        ms
                    @else
                        No latency measurement
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="fw-semibold mb-2">
                    Queue backlog
                </div>

                <span
                    class="badge {{
                        $healthBadge(
                            $operations->queue['status']
                        )
                    }}"
                >
                    {{
                        str_replace(
                            '_',
                            ' ',
                            ucfirst(
                                $operations->queue['status']
                            )
                        )
                    }}
                </span>

                <div class="display-6 fw-bold my-2">
                    {{
                        $operations->queue['backlog']
                        ?? 'N/A'
                    }}
                </div>

                <div class="small text-muted-smartfactory">
                    Driver:
                    {{ $operations->queue['connection'] }}
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="fw-semibold mb-2">
                    Failed queue jobs
                </div>

                <div class="display-6 fw-bold my-2">
                    {{
                        $operations->queue['failed']
                        ?? 'N/A'
                    }}
                </div>

                <div class="small text-muted-smartfactory">
                    Payloads and exception traces are not displayed.
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="app-card bg-white h-100 p-4">
                <div
                    class="d-flex justify-content-between
                           align-items-start gap-3 mb-3"
                >
                    <div>
                        <h2 class="h5 fw-bold mb-1">
                            ERP synchronization health
                        </h2>

                        <p
                            class="small text-muted-smartfactory
                                   mb-0"
                        >
                            Sanitized health from the existing ERP
                            monitoring service.
                        </p>
                    </div>

                    @can(\App\Enums\PermissionName::ViewSynchronizationLogs->value)
                        <a
                            href="{{
                                route(
                                    'admin.erp-monitoring.index'
                                )
                            }}"
                            class="btn btn-sm btn-outline-primary"
                        >
                            Open monitoring
                        </a>
                    @endcan
                </div>

                @if ($operations->erpHealth !== null)
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span
                            class="badge {{
                                $healthBadge(
                                    $operations
                                        ->erpHealth
                                        ->status
                                )
                            }}"
                        >
                            {{
                                ucfirst(
                                    $operations
                                        ->erpHealth
                                        ->status
                                )
                            }}
                        </span>

                        <span class="small text-muted-smartfactory">
                            Source:
                            {{
                                $operations
                                    ->erpHealth
                                    ->sourceSystem
                            }}
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-secondary">
                                    Runs in window
                                </div>

                                <div class="h4 fw-bold mb-0">
                                    {{
                                        $operations
                                            ->erpHealth
                                            ->summary[
                                                'runs_in_window'
                                            ]
                                        ?? 0
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-secondary">
                                    Failed runs
                                </div>

                                <div class="h4 fw-bold mb-0">
                                    {{
                                        $operations
                                            ->erpHealth
                                            ->summary[
                                                'failed_runs_in_window'
                                            ]
                                        ?? 0
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-secondary">
                                    Stale states
                                </div>

                                <div class="h4 fw-bold mb-0">
                                    {{
                                        $operations
                                            ->erpHealth
                                            ->summary[
                                                'stale_states'
                                            ]
                                        ?? 0
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-secondary">
                                    Recent failures
                                </div>

                                <div class="h4 fw-bold mb-0">
                                    {{
                                        count(
                                            $operations
                                                ->erpHealth
                                                ->failures
                                        )
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (
                        $operations
                            ->erpHealth
                            ->reasons !== []
                    )
                        <div class="mt-3">
                            <div class="small fw-semibold mb-1">
                                Health reasons
                            </div>

                            <ul class="small mb-0">
                                @foreach (
                                    $operations
                                        ->erpHealth
                                        ->reasons as $reason
                                )
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @else
                    <div class="alert alert-danger mb-0">
                        {{
                            $operations
                                ->erpHealthMessage
                            ?? 'ERP health is unavailable.'
                        }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-4">
            @php
                $aiHealth =
                    $operations
                        ->applicationHealth[
                            'ai_service'
                        ]
                    ?? [
                        'status' => 'unavailable',
                        'latency_ms' => null,
                        'service_version' => null,
                        'api_version' => null,
                        'message' =>
                            'The FastAPI health state is unavailable.',
                    ];

                $aiStatus =
                    (string) (
                        $aiHealth['status']
                        ?? 'unavailable'
                    );

                $aiBadge = match ($aiStatus) {
                    'available' => 'text-bg-success',
                    'degraded' => 'text-bg-warning',
                    'not_configured' => 'text-bg-secondary',
                    default => 'text-bg-danger',
                };
            @endphp

            <div class="app-card bg-white h-100 p-4">
                <h2 class="h5 fw-bold">
                    AI service status
                </h2>

                <span class="badge {{ $aiBadge }}">
                    {{
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $aiStatus
                            )
                        )
                    }}
                </span>

                @if (
                    is_string(
                        $aiHealth[
                            'service_version'
                        ] ?? null
                    )
                )
                    <div class="small mt-3">
                        <strong>FastAPI:</strong>
                        {{
                            $aiHealth[
                                'service_version'
                            ]
                        }}
                    </div>
                @endif

                @if (
                    is_string(
                        $aiHealth[
                            'api_version'
                        ] ?? null
                    )
                )
                    <div class="small mt-1">
                        <strong>Contract:</strong>
                        {{
                            $aiHealth[
                                'api_version'
                            ]
                        }}
                    </div>
                @endif

                @if (
                    is_int(
                        $aiHealth[
                            'latency_ms'
                        ] ?? null
                    )
                )
                    <div class="small mt-1">
                        <strong>Latency:</strong>
                        {{
                            $aiHealth[
                                'latency_ms'
                            ]
                        }}
                        ms
                    </div>
                @endif

                <p class="small text-muted-smartfactory mt-3 mb-0">
                    {{
                        $aiHealth['message']
                        ?? 'No sanitized AI health message is available.'
                    }}
                </p>

                <p class="small text-muted-smartfactory mt-2 mb-0">
                    Step 21A reports only the FastAPI foundation.
                    Machine learning and Ollama are not active yet.
                </p>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white p-4">
                <h2 class="h5 fw-bold mb-3">
                    Recent sanitized audit activity
                </h2>

                @if ($operations->auditItems === [])
                    <div class="text-muted-smartfactory">
                        No audit activity is available.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Occurred</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Subject</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach (
                                    $operations->auditItems
                                    as $audit
                                )
                                    <tr>
                                        <td class="text-nowrap">
                                            {{
                                                $audit[
                                                    'occurred_at'
                                                ]
                                                ?? 'N/A'
                                            }}
                                        </td>

                                        <td>
                                            <div class="fw-semibold">
                                                {{
                                                    $audit[
                                                        'actor_name'
                                                    ]
                                                }}
                                            </div>

                                            @if (
                                                $audit[
                                                    'actor_email'
                                                ] !== null
                                            )
                                                <div
                                                    class="small
                                                           text-muted-smartfactory"
                                                >
                                                    {{
                                                        $audit[
                                                            'actor_email'
                                                        ]
                                                    }}
                                                </div>
                                            @endif
                                        </td>

                                        <td>
                                            <code>
                                                {{
                                                    $audit[
                                                        'action'
                                                    ]
                                                }}
                                            </code>
                                        </td>

                                        <td>
                                            {{
                                                $audit[
                                                    'subject_type'
                                                ]
                                                ?? 'System'
                                            }}

                                            @if (
                                                $audit[
                                                    'subject_id'
                                                ] !== null
                                            )
                                                #{{ $audit['subject_id'] }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12">
            <h2 class="h5 fw-bold mb-0">
                Administration workspaces
            </h2>
        </div>

        @can(\App\Enums\PermissionName::ViewUsers->value)
            <div class="col-md-6 col-xl-4">
                <a
                    href="{{ route('admin.users.index') }}"
                    class="text-decoration-none text-dark"
                >
                    <div class="app-card bg-white h-100 p-4">
                        <h3 class="h5 fw-bold">
                            User administration
                        </h3>

                        <p class="text-muted-smartfactory mb-0">
                            Create accounts, manage status and issue
                            controlled temporary passwords.
                        </p>
                    </div>
                </a>
            </div>
        @endcan

        <div class="col-md-6 col-xl-4">
            <a
                href="{{
                    route(
                        'admin.operator-administration.index'
                    )
                }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <h3 class="h5 fw-bold">
                        Operator administration
                    </h3>

                    <p class="text-muted-smartfactory mb-0">
                        Link accounts and assign production lines and
                        shifts.
                    </p>
                </div>
            </a>
        </div>

        @can(\App\Enums\PermissionName::ViewRoles->value)
            <div class="col-md-6 col-xl-4">
                <a
                    href="{{ route('admin.roles.index') }}"
                    class="text-decoration-none text-dark"
                >
                    <div class="app-card bg-white h-100 p-4">
                        <h3 class="h5 fw-bold">
                            Roles and permissions
                        </h3>

                        <p class="text-muted-smartfactory mb-0">
                            Review least-privilege role assignments.
                        </p>
                    </div>
                </a>
            </div>
        @endcan

        <div class="col-md-6 col-xl-4">
            <a
                href="{{ route('admin.master-data.index') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <h3 class="h5 fw-bold">
                        Production master data
                    </h3>

                    <p class="text-muted-smartfactory mb-0">
                        Browse synchronized products, lines, machines,
                        shifts and Operators.
                    </p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-4">
            <a
                href="{{ route('security.two-factor.show') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <h3 class="h5 fw-bold">
                        Two-factor security
                    </h3>

                    <p class="text-muted-smartfactory mb-0">
                        Review recovery codes or replace the
                        authenticator device.
                    </p>
                </div>
            </a>
        </div>

        @can(\App\Enums\PermissionName::ViewSynchronizationLogs->value)
            <div class="col-md-6 col-xl-4">
                <a
                    href="{{
                        route(
                            'admin.erp-monitoring.index'
                        )
                    }}"
                    class="text-decoration-none text-dark"
                >
                    <div class="app-card bg-white h-100 p-4">
                        <h3 class="h5 fw-bold">
                            ERP monitoring
                        </h3>

                        <p class="text-muted-smartfactory mb-0">
                            Review synchronization runs, resources and
                            sanitized failures.
                        </p>
                    </div>
                </a>
            </div>
        @endcan
    </div>
@endsection