@include('dashboard.partials.ai-insights-link')

@php
    $maintenance = $snapshot->maintenance;
    $highestDowntimeMachine =
        $snapshot->highestDowntimeMachine();
@endphp

<section
    class="mb-4"
    data-sf-drilldown-scope
    data-sf-drilldown-url="{{
        route(
            'analytics.maintenance.index',
            $snapshot->filter->toMaintenanceQuery()
        )
    }}"
>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <p class="text-uppercase small fw-semibold text-warning mb-1">
                Maintenance operations
            </p>

            <h2 class="h4 fw-bold mb-1">
                Maintenance manager operational dashboard
            </h2>

            <p class="text-muted-smartfactory mb-0">
                Maintenance snapshot for the selected period
            </p>
        </div>

        @if ($snapshot->needsAttention())
            <span class="badge text-bg-warning fs-6">
                Attention required
            </span>
        @else
            <span class="badge text-bg-success fs-6">
                No active maintenance warning
            </span>
        @endif
    </div>

    <div class="alert alert-secondary" role="note">
        <strong>Maintenance basis:</strong>
        {{ $snapshot->dataBasisLabel() }}
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Total downtime
                </div>

                <div class="display-6">
                    {{ $duration($maintenance->totalDowntimeMinutes) }}
                </div>

                <div class="small text-muted-smartfactory">
                    {{ number_format($maintenance->downtimeEventCount) }}
                    events /
                    {{ number_format($maintenance->openDowntimeEventCount) }}
                    open
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Planned / unplanned
                </div>

                <div class="h3 mb-1">
                    {{ $duration($maintenance->plannedDowntimeMinutes) }}
                    /
                    {{ $duration($maintenance->unplannedDowntimeMinutes) }}
                </div>

                <div class="small text-muted-smartfactory">
                    {{
                        $duration(
                            $maintenance
                                ->unclassifiedDowntimeMinutes
                        )
                    }} unclassified
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Observed availability
                </div>

                <div class="display-6">
                    {{
                        $percentage(
                            $maintenance
                                ->availabilityPercentage
                        )
                    }}
                </div>

                <div class="small text-muted-smartfactory">
                    {{ $duration($maintenance->runningMinutes) }}
                    running /
                    {{ $duration($maintenance->observedStatusMinutes) }}
                    observed
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    MTTR
                </div>

                <div class="display-6">
                    {{ $duration($maintenance->mttrMinutes) }}
                </div>

                <div class="small text-muted-smartfactory">
                    {{
                        number_format(
                            $maintenance
                                ->completedCorrectiveCount
                        )
                    }} completed corrective interventions
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    MTBF
                </div>

                <div class="display-6">
                    {{ $duration($maintenance->mtbfMinutes) }}
                </div>

                <div class="small text-muted-smartfactory">
                    {{
                        number_format(
                            $maintenance->faultEventCount
                        )
                    }} recognized failures
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Failure frequency
                </div>

                <div class="display-6">
                    {{
                        $maintenance
                            ->failuresPer100RunningHours
                        === null
                            ? 'N/A'
                            : number_format(
                                $maintenance
                                    ->failuresPer100RunningHours,
                                2
                            )
                    }}
                </div>

                <div class="small text-muted-smartfactory">
                    Failures per 100 running hours
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Interventions
                </div>

                <div class="display-6">
                    {{
                        number_format(
                            $maintenance
                                ->maintenanceInterventionCount
                        )
                    }}
                </div>

                <div class="small text-muted-smartfactory">
                    {{
                        number_format(
                            $maintenance
                                ->preventiveInterventionCount
                        )
                    }} preventive /
                    {{
                        number_format(
                            $maintenance
                                ->correctiveInterventionCount
                        )
                    }} corrective
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="app-card bg-white h-100 p-4">
                <div class="text-muted-smartfactory">
                    Repeated-failure machines
                </div>

                <div class="display-6">
                    {{
                        number_format(
                            $maintenance
                                ->repeatedFailureMachineCount
                        )
                    }}
                </div>

                <div class="small text-muted-smartfactory">
                    At least two recognized failures
                </div>
            </div>
        </div>
    </div>

    @if ($highestDowntimeMachine !== null)
        <div class="alert alert-warning" role="status">
            <strong>Highest downtime:</strong>
            {{ $highestDowntimeMachine->machineName }}
            on
            {{ $highestDowntimeMachine->productionLineName }}
            with
            {{
                $duration(
                    $highestDowntimeMachine
                        ->totalDowntimeMinutes
                )
            }}.
        </div>
    @endif

    <div class="app-card bg-white mb-4">
        <div class="border-bottom px-4 py-3">
            <div class="fw-semibold">
                Machine status and maintenance indicators
            </div>

            <div class="small text-muted-smartfactory">
                Machines are ranked by total downtime.
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Machine</th>
                        <th>Line</th>
                        <th class="text-end">Downtime</th>
                        <th class="text-end">Planned</th>
                        <th class="text-end">Unplanned</th>
                        <th class="text-end">Availability</th>
                        <th class="text-end">Failures</th>
                        <th class="text-end">MTBF</th>
                        <th class="text-end">MTTR</th>
                        <th class="text-end">Interventions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($maintenance->machines as $machine)
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    {{ $machine->machineName }}
                                </div>

                                <div class="small text-muted-smartfactory">
                                    {{ $machine->machineCode }}
                                </div>
                            </td>

                            <td>
                                {{ $machine->productionLineName }}
                            </td>

                            <td class="text-end">
                                {{
                                    $duration(
                                        $machine
                                            ->totalDowntimeMinutes
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    $duration(
                                        $machine
                                            ->plannedDowntimeMinutes
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    $duration(
                                        $machine
                                            ->unplannedDowntimeMinutes
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    $percentage(
                                        $machine
                                            ->availabilityPercentage
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    number_format(
                                        $machine
                                            ->faultEventCount
                                    )
                                }}

                                @if ($machine->hasRepeatedFailures())
                                    <span class="badge text-bg-danger">
                                        repeated
                                    </span>
                                @endif
                            </td>

                            <td class="text-end">
                                {{ $duration($machine->mtbfMinutes) }}
                            </td>

                            <td class="text-end">
                                {{ $duration($machine->mttrMinutes) }}
                            </td>

                            <td class="text-end">
                                {{
                                    number_format(
                                        $machine
                                            ->maintenanceInterventionCount
                                    )
                                }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="10"
                                class="text-center text-muted py-5"
                            >
                                No maintenance data matches the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="app-card bg-white mb-4">
        <div class="border-bottom px-4 py-3">
            <div class="fw-semibold">
                Maintenance intervention status
            </div>

            <div class="small text-muted-smartfactory">
                Preventive, corrective, inspection and calibration work.
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Planned</th>
                        <th class="text-end">In progress</th>
                        <th class="text-end">Completed</th>
                        <th class="text-end">Cancelled</th>
                        <th class="text-end">Downtime</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($maintenance->maintenanceTypes as $type)
                        <tr>
                            <td class="fw-semibold">
                                {{ $type->label }}
                            </td>

                            <td class="text-end">
                                {{
                                    number_format(
                                        $type->interventionCount
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    number_format(
                                        $type->plannedCount
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    number_format(
                                        $type->inProgressCount
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    number_format(
                                        $type->completedCount
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    number_format(
                                        $type->cancelledCount
                                    )
                                }}
                            </td>

                            <td class="text-end">
                                {{
                                    $duration(
                                        $type->downtimeMinutes
                                    )
                                }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-muted py-5"
                            >
                                No maintenance interventions match this selection.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
