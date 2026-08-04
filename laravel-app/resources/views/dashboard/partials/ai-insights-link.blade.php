@php
    $aiInsightsAbilities = [
        \App\Enums\PermissionName::ViewAdministratorDashboard->value,
        \App\Enums\PermissionName::ViewProductionManagerDashboard->value,
        \App\Enums\PermissionName::ViewProductionSupervisorDashboard->value,
        \App\Enums\PermissionName::ViewMaintenanceManagerDashboard->value,
    ];
    $showAiInsightsLink = auth()->check()
        && collect($aiInsightsAbilities)->contains(
            fn (string $ability): bool => auth()->user()->can($ability)
        );
@endphp

@if ($showAiInsightsLink)
    <div class="alert alert-primary d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="fw-semibold">AI-assisted insights are available</div>
            <div class="small">
                Review verified production forecasts, anomaly scores and maintenance risk.
                All outputs remain labeled as simulated-prototype decision support.
            </div>
        </div>

        <a href="{{ route('ai-insights.index') }}" class="btn btn-primary">
            Open AI insights
        </a>
    </div>
@endif
