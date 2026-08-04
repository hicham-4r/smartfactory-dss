@php
    $navigationContext = $context ?? 'dashboard';
    $navigationUser = auth()->user();

    $canUseProductionAi = $navigationUser !== null
        && (
            $navigationUser->can(
                \App\Enums\PermissionName::ViewAdministratorDashboard->value
            )
            || $navigationUser->can(
                \App\Enums\PermissionName::ViewProductionManagerDashboard->value
            )
            || $navigationUser->can(
                \App\Enums\PermissionName::ViewProductionSupervisorDashboard->value
            )
        );

    $canUseMaintenanceAi = $navigationUser !== null
        && (
            $navigationUser->can(
                \App\Enums\PermissionName::ViewAdministratorDashboard->value
            )
            || $navigationUser->can(
                \App\Enums\PermissionName::ViewMaintenanceManagerDashboard->value
            )
        );

    $canUseAiInsights = $canUseProductionAi || $canUseMaintenanceAi;
    $isReportsContext = $navigationContext === 'reports';
@endphp

@if (
    $canUseAiInsights
    && \Illuminate\Support\Facades\Route::has('ai-insights.index')
)
    <section
        class="app-card bg-white p-4 mb-4"
        aria-labelledby="step21o-ai-navigation-title-{{ $navigationContext }}"
        data-step21o-ai-navigation="{{ $navigationContext }}"
    >
        <div
            class="d-flex flex-column flex-lg-row
                   justify-content-between align-items-lg-center gap-3"
        >
            <div>
                <p class="text-uppercase small fw-semibold text-primary mb-1">
                    {{ $isReportsContext ? 'AI reporting' : 'Phase 6 AI services' }}
                </p>

                <h2
                    id="step21o-ai-navigation-title-{{ $navigationContext }}"
                    class="h5 fw-bold mb-2"
                >
                    {{ $isReportsContext ? 'AI analysis reports' : 'AI decision support' }}
                </h2>

                <p class="text-muted-smartfactory mb-2">
                    @if ($isReportsContext)
                        Run an authorized forecast, anomaly check, or maintenance-risk
                        analysis, then export that exact verified result as PDF, Excel,
                        or CSV without executing a second prediction.
                    @else
                        Open the role-aware AI workspace for automatic feature preparation,
                        verified model inference, and downloadable technical reports.
                    @endif
                </p>

                <div class="d-flex flex-wrap gap-2">
                    @if ($canUseProductionAi)
                        <span class="badge text-bg-primary">
                            Production forecast
                        </span>
                        <span class="badge text-bg-primary">
                            Production anomaly
                        </span>
                    @endif

                    @if ($canUseMaintenanceAi)
                        <span class="badge text-bg-warning">
                            Maintenance risk
                        </span>
                    @endif

                    <span class="badge text-bg-secondary">
                        simulated_prototype
                    </span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                <a
                    href="{{ route('ai-insights.index') }}"
                    class="btn btn-primary"
                >
                    {{ $isReportsContext ? 'Run AI analysis' : 'Open AI Insights' }}
                </a>

                @if (
                    ! $isReportsContext
                    && \Illuminate\Support\Facades\Route::has('reports.index')
                )
                    <a
                        href="{{ route('reports.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reports workspace
                    </a>
                @endif
            </div>
        </div>

        <p class="small text-muted-smartfactory mt-3 mb-0">
            Results are decision-support outputs trained only on simulated prototype
            data. They are not industrial commitments or automatic control actions.
        </p>
    </section>
@endif
