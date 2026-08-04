@extends('layouts.app')

@section('title', 'AI Insights')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-primary mb-1">
                Phase 7 AI services
            </p>
            <h1 class="h3 mb-1">AI-assisted operational insights</h1>
            <p class="text-muted mb-0">
                Automatic feature preparation and authenticated inference over
                verified SmartFactory model artifacts.
            </p>
        </div>

        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            Dashboard
        </a>
    </div>

    <div class="alert alert-warning" role="note">
        <strong>Prototype limitation:</strong>
        every result on this page is trained from
        <code>simulated_prototype</code> data. It is decision support only and
        must not be treated as validated industrial performance, an automatic
        production commitment, or reliable predictive maintenance.
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-2">
                The inference request could not be submitted.
            </div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card bg-white mb-4">
        <div class="border-bottom px-4 py-3 d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold">Verified model registry</div>
                <div class="small text-muted-smartfactory">
                    Loaded through Laravel using the internal bearer-token boundary.
                </div>
            </div>

            <span class="badge {{
                $registry->succeeded()
                    ? 'text-bg-success'
                    : 'text-bg-secondary'
            }}">
                {{ $registry->status->value }}
            </span>
        </div>

        <div class="p-4">
            @if ($registry->succeeded())
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="small text-muted-smartfactory">Model run</div>
                        <code class="text-break">
                            {{ $registry->data['model_run_id'] }}
                        </code>
                    </div>
                    <div class="col-lg-6">
                        <div class="small text-muted-smartfactory">
                            Source feature run
                        </div>
                        <code class="text-break">
                            {{ $registry->data['source_feature_run_id'] }}
                        </code>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted-smartfactory mb-2">
                            Available tasks
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($registry->data['tasks'] as $task)
                                <span class="badge text-bg-primary">
                                    {{ $task }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-secondary mb-0" role="status">
                    {{ $registry->message ?? 'The model registry is unavailable.' }}
                    <div class="small mt-2">
                        Request ID: <code>{{ $registry->requestId }}</code>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($inferenceResult !== null)
        @include('ai.insights.partials.result', [
            'result' => $inferenceResult,
            'operation' => $activeOperation,
        ])
    @endif

    <div class="mb-3">
        <h2 class="h4 mb-1">Automatic analysis</h2>
        <p class="text-muted mb-0">
            Choose operational entities and dates. Laravel reads only authorized
            simulated DSS records, calculates the exact model features, and sends
            the prepared row to FastAPI.
        </p>
    </div>

    @if ($canUseProductionModels)
        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                @include(
                    'ai.insights.partials.automatic-forecast-form'
                )
            </div>
            <div class="col-xl-5">
                @include(
                    'ai.insights.partials.automatic-anomaly-form'
                )
            </div>
        </div>
    @endif

    @if ($canUseMaintenanceModels)
        @include(
            'ai.insights.partials.automatic-maintenance-form'
        )
    @endif

    <details class="app-card bg-white mt-4">
        <summary class="px-4 py-3 fw-semibold">
            Advanced manual feature testing
        </summary>

        <div class="border-top p-4">
            <div class="alert alert-secondary">
                These detailed forms are retained only for contract testing and
                troubleshooting. Normal users should use the automatic forms above.
            </div>

            @if ($canUseProductionModels)
                <div class="row g-4 mb-4">
                    <div class="col-xl-6">
                        @include('ai.insights.partials.forecast-form')
                    </div>
                    <div class="col-xl-6">
                        @include('ai.insights.partials.anomaly-form')
                    </div>
                </div>
            @endif

            @if ($canUseMaintenanceModels)
                @include('ai.insights.partials.maintenance-form')
            @endif
        </div>
    </details>

    <div class="alert alert-secondary mt-4 mb-0" role="note">
        Laravel performs database access, authorization, and deterministic feature
        preparation. FastAPI receives one validated feature row and never queries
        Laravel, MySQL, the simulated Sage ERP, or operational tables directly.
        Ollama explanations are generated only on demand from an encrypted,
        session-bound snapshot of the exact verified inference result. A failed
        explanation never changes the numeric inference result.
    </div>
</div>
@endsection
