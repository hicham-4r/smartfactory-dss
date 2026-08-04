@php
    $chartLabels = is_array(
        $chartConfig['labels'] ?? null
    )
        ? array_values($chartConfig['labels'])
        : [];

    $chartDatasets = is_array(
        $chartConfig['datasets'] ?? null
    )
        ? array_values($chartConfig['datasets'])
        : [];

    $chartActionUrl = $chartActionUrl ?? null;

    if (
        $chartActionUrl === null
        && isset($overview)
    ) {
        $chartActionUrl = match (true) {
            str_starts_with($chartId, 'operator-')
                && \Illuminate\Support\Facades\Route::has('production.operator.index') =>
                    route('production.operator.index'),

            str_starts_with($chartId, 'maintenance-')
                && \Illuminate\Support\Facades\Route::has('analytics.maintenance.index') =>
                    route(
                        'analytics.maintenance.index',
                        $overview->filter->toMaintenanceQuery()
                    ),

            \Illuminate\Support\Facades\Route::has('analytics.production.index') =>
                route(
                    'analytics.production.index',
                    $overview->filter->toQuery()
                ),

            default => null,
        };
    }

    $jsonFlags = JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_AMP
        | JSON_HEX_QUOT;
@endphp

<div class="{{ $columnClass ?? 'col-12 col-xl-6' }}">
    <section
        class="app-card bg-white h-100 sf-chart-card"
        aria-labelledby="{{ $chartId }}-title"
    >
        <div class="sf-chart-card__header d-flex justify-content-between align-items-start gap-3">
            <div>
                <h3
                    id="{{ $chartId }}-title"
                    class="h6 fw-bold mb-1"
                >
                    {{ $chartTitle }}
                </h3>

                @if (! empty($chartDescription))
                    <p class="small text-muted-smartfactory mb-0">
                        {{ $chartDescription }}
                    </p>
                @endif
            </div>

            @if ($chartActionUrl)
                <a
                    href="{{ $chartActionUrl }}"
                    class="btn btn-sm btn-outline-primary sf-chart-card__action"
                >
                    Open details
                </a>
            @endif
        </div>

        <div class="sf-chart-card__body">
            <div
                class="sf-chart"
                data-sf-chart
                data-sf-chart-source="{{ $chartId }}-source"
                aria-live="polite"
            ></div>

            <script
                id="{{ $chartId }}-source"
                type="application/json"
            >{!! json_encode($chartConfig, $jsonFlags) !!}</script>

            <details class="sf-chart-data-table">
                <summary>View accessible chart data</summary>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Category</th>

                                @foreach ($chartDatasets as $dataset)
                                    <th
                                        scope="col"
                                        class="text-end"
                                    >
                                        {{
                                            $dataset['label']
                                            ?? 'Value'
                                        }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($chartLabels as $labelIndex => $label)
                                <tr>
                                    <th scope="row">
                                        {{ $label }}
                                    </th>

                                    @foreach ($chartDatasets as $dataset)
                                        <td class="text-end">
                                            {{
                                                $dataset['values'][$labelIndex]
                                                ?? 0
                                            }}{{
                                                $chartConfig['valueSuffix']
                                                ?? ''
                                            }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="{{
                                            max(
                                                1,
                                                count($chartDatasets) + 1
                                            )
                                        }}"
                                        class="text-muted-smartfactory"
                                    >
                                        No chart data is available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </section>
</div>
