@php
    $tableRows = $rows ?? [];
    $tableTitle = $title ?? 'Production metrics';
    $tableDescription = $description ?? null;
    $emptyMessage = $emptyMessage ?? 'No matching production data is available.';
    $open = $open ?? false;
@endphp

<details class="card shadow-sm mb-3" @if($open) open @endif>
    <summary class="card-header fw-semibold d-flex align-items-center justify-content-between gap-2">
        <span>{{ $tableTitle }}</span>
        <span class="badge text-bg-secondary">{{ count($tableRows) }}</span>
    </summary>

    <div class="card-body">
        @if($tableDescription)
            <p class="text-muted small mb-3">{{ $tableDescription }}</p>
        @endif

        @if($tableRows === [])
            <div class="text-muted py-3">{{ $emptyMessage }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ $firstColumnLabel ?? 'Period / item' }}</th>
                            <th scope="col">Unit</th>
                            <th scope="col" class="text-end">Target</th>
                            <th scope="col" class="text-end">Actual</th>
                            <th scope="col" class="text-end">Achievement</th>
                            <th scope="col" class="text-end">Good</th>
                            <th scope="col" class="text-end">Rejected</th>
                            <th scope="col" class="text-end">Rejection</th>
                            <th scope="col" class="text-end">Yield</th>
                            <th scope="col" class="text-end">Good-output efficiency</th>
                            <th scope="col" class="text-end">Runtime</th>
                            <th scope="col" class="text-end">Downtime</th>
                            <th scope="col" class="text-end">Average rate/hour</th>
                            <th scope="col" class="text-end">Observed utilization</th>
                            <th scope="col" class="text-end">Records</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($tableRows as $row)
                            <tr>
                                <th scope="row">
                                    {{ $row->label }}

                                    @if($row->isProvisional())
                                        <span class="badge text-bg-warning ms-1">
                                            Provisional
                                        </span>
                                    @endif
                                </th>

                                <td>{{ $row->quantityUnit }}</td>

                                <td class="text-end">
                                    {{ number_format((float) $row->targetQuantity, 3) }}
                                </td>

                                <td class="text-end">
                                    {{ number_format((float) $row->actualQuantity, 3) }}
                                </td>

                                <td class="text-end">
                                    {{
                                        $row->achievementPercentage === null
                                            ? 'N/A'
                                            : number_format($row->achievementPercentage, 2).'%'
                                    }}
                                </td>

                                <td class="text-end">
                                    {{ number_format((float) $row->goodQuantity, 3) }}
                                </td>

                                <td class="text-end">
                                    {{ number_format((float) $row->rejectedQuantity, 3) }}
                                </td>

                                <td class="text-end">
                                    {{
                                        $row->rejectionPercentage === null
                                            ? 'N/A'
                                            : number_format($row->rejectionPercentage, 2).'%'
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        $row->qualityYieldPercentage === null
                                            ? 'N/A'
                                            : number_format($row->qualityYieldPercentage, 2).'%'
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        $row->goodOutputEfficiencyPercentage === null
                                            ? 'N/A'
                                            : number_format($row->goodOutputEfficiencyPercentage, 2).'%'
                                    }}
                                </td>

                                <td class="text-end">
                                    {{ number_format($row->runtimeMinutes) }} min
                                </td>

                                <td class="text-end">
                                    {{ number_format($row->downtimeMinutes) }} min
                                </td>

                                <td class="text-end">
                                    {{
                                        $row->averageProductionRatePerHour === null
                                            ? 'N/A'
                                            : number_format(
                                                (float) $row->averageProductionRatePerHour,
                                                3
                                            )
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        $row->observedUtilizationPercentage === null
                                            ? 'N/A'
                                            : number_format(
                                                $row->observedUtilizationPercentage,
                                                2
                                            ).'%'
                                    }}
                                </td>

                                <td class="text-end">
                                    {{ $row->recordCount }}

                                    @if($row->provisionalRecordCount > 0)
                                        <span class="text-muted">
                                            ({{ $row->provisionalRecordCount }} pending)
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</details>
