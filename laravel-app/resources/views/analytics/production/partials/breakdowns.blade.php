<section class="mt-4" aria-labelledby="production-breakdowns-title">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 id="production-breakdowns-title" class="h4 mb-1">
                Production trends and breakdowns
            </h2>

            <p class="text-muted mb-0">
                Deterministic database aggregations from synchronized DSS production orders,
                batches, and eligible production records. No machine-learning values are used.
            </p>
        </div>

        <span class="badge text-bg-light border">
            Generated {{ $report->generatedAt->format('Y-m-d H:i') }} UTC
        </span>
    </div>

    @if($report->hasMixedUnits())
        <div class="alert alert-warning" role="note">
            Results contain several quantity units. Values are kept in separate rows and are
            never added across units.
        </div>
    @endif

    <div class="alert alert-secondary" role="note">
        <div><strong>Line ranking:</strong> {{ $report->lineRankingBasis() }}</div>
        <div class="mt-1"><strong>Shift targets:</strong> {{ $report->shiftTargetCaution() }}</div>
        <div class="mt-1">
            <strong>Good-output efficiency:</strong>
            good quantity divided by scheduled target. This is a prototype production KPI,
            not Overall Equipment Effectiveness (OEE).
        </div>
    </div>

    @if($report->isEmpty())
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <h3 class="h5">No trend or breakdown data</h3>
                <p class="text-muted mb-0">
                    No eligible execution target or production record matches the selected filters.
                </p>
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header fw-semibold">Best-performing line by unit</div>
                    <div class="card-body">
                        @forelse($report->bestLinesByUnit as $row)
                            <div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-2">
                                <div>
                                    <div class="fw-semibold">{{ $row->label }}</div>
                                    <div class="small text-muted">{{ $row->quantityUnit }}</div>
                                </div>

                                <div class="text-end">
                                    <div class="fw-semibold">
                                        {{
                                            $row->achievementPercentage === null
                                                ? 'N/A'
                                                : number_format(
                                                    $row->achievementPercentage,
                                                    2
                                                ).'%'
                                        }}
                                    </div>
                                    <div class="small text-muted">target achievement</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">
                                A ranking requires at least one line with both target and actual production.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header fw-semibold">Lowest-performing line by unit</div>
                    <div class="card-body">
                        @forelse($report->lowestLinesByUnit as $row)
                            <div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-2">
                                <div>
                                    <div class="fw-semibold">{{ $row->label }}</div>
                                    <div class="small text-muted">{{ $row->quantityUnit }}</div>
                                </div>

                                <div class="text-end">
                                    <div class="fw-semibold">
                                        {{
                                            $row->achievementPercentage === null
                                                ? 'N/A'
                                                : number_format(
                                                    $row->achievementPercentage,
                                                    2
                                                ).'%'
                                        }}
                                    </div>
                                    <div class="small text-muted">target achievement</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">
                                A ranking requires at least one line with both target and actual production.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include(
        'analytics.production.partials.metric-table',
        [
            'title' => 'Daily production trend',
            'description' =>
                'Target quantities are assigned to planned dates. With a shift filter, batch targets are assigned once to the first matching production-record date.',
            'firstColumnLabel' => 'Production date',
            'rows' => $report->dailyTrend,
            'open' => true,
        ]
    )

    @include(
        'analytics.production.partials.metric-table',
        [
            'title' => 'Weekly production trend',
            'description' =>
                'ISO weeks begin on Monday. Weekly values are rolled up from database-aggregated daily rows.',
            'firstColumnLabel' => 'ISO week',
            'rows' => $report->weeklyTrend,
        ]
    )

    @include(
        'analytics.production.partials.metric-table',
        [
            'title' => 'Monthly production trend',
            'description' =>
                'Monthly values are rolled up from database-aggregated daily rows.',
            'firstColumnLabel' => 'Month',
            'rows' => $report->monthlyTrend,
        ]
    )

    @include(
        'analytics.production.partials.metric-table',
        [
            'title' => 'Production by line',
            'firstColumnLabel' => 'Production line',
            'rows' => $report->byProductionLine,
            'open' => true,
        ]
    )

    @include(
        'analytics.production.partials.metric-table',
        [
            'title' => 'Production by shift',
            'description' => $report->shiftTargetCaution(),
            'firstColumnLabel' => 'Shift',
            'rows' => $report->byShift,
        ]
    )

    @include(
        'analytics.production.partials.metric-table',
        [
            'title' => 'Production by product',
            'firstColumnLabel' => 'Product',
            'rows' => $report->byProduct,
        ]
    )

    @include(
        'analytics.production.partials.metric-table',
        [
            'title' => 'Production by product family',
            'firstColumnLabel' => 'Product family',
            'rows' => $report->byProductFamily,
        ]
    )
</section>
