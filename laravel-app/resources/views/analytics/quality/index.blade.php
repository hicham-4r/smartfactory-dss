@extends('layouts.app')

@section('title', 'Quality KPI Summary')

@section('content')
@php
    $percentage = static fn (?float $value): string =>
        $value === null ? 'N/A' : number_format($value, 2).'%';
@endphp

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Quality KPI Summary</h1>
            <p class="text-muted mb-0">
                Deterministic inspection, finished-lot, and nonconformity indicators from synchronized DSS data.
            </p>
        </div>

        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            Dashboard
        </a>
    </div>

    @include(
        'analytics.partials.active-drilldowns',
        [
            'domain' => 'quality',
            'filter' => $filter,
        ]
    )

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>The filters could not be applied.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info" role="note">
        <strong>Data basis:</strong> {{ $summary->dataBasisLabel() }}
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Filters</div>
        <div class="card-body">
            <form
                method="GET"
                action="{{ route('analytics.quality.index') }}"
                class="row g-3"
                id="quality-kpi-filter-form"
                data-sf-loading
                data-sf-loading-text="Applying filters..."
            >
                <div class="col-md-3 col-xl-2">
                    <label for="start_date" class="form-label">Start date</label>
                    <input
                        id="start_date"
                        name="start_date"
                        type="date"
                        class="form-control"
                        value="{{ $filter->startDateString() }}"
                        required
                    >
                </div>

                <div class="col-md-3 col-xl-2">
                    <label for="end_date" class="form-label">End date</label>
                    <input
                        id="end_date"
                        name="end_date"
                        type="date"
                        class="form-control"
                        value="{{ $filter->endDateString() }}"
                        required
                    >
                </div>

                <div class="col-md-3 col-xl-2">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select" required>
                        @foreach($timezoneOptions as $timezone)
                            <option
                                value="{{ $timezone }}"
                                @selected($filter->timezone === $timezone)
                            >
                                {{ $timezone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 col-xl-3">
                    <label for="production_line_id" class="form-label">Production line</label>
                    <select
                        id="production_line_id"
                        name="production_line_id"
                        class="form-select"
                        data-filter-key="production_line_id"
                    >
                        <option value="">All lines with quality data</option>
                        @foreach($productionLines as $line)
                            <option
                                value="{{ $line->id }}"
                                @selected($filter->productionLineId === (int) $line->id)
                            >
                                {{ $line->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="product_family_id" class="form-label">Product family</label>
                    <select
                        id="product_family_id"
                        name="product_family_id"
                        class="form-select"
                        data-filter-key="product_family_id"
                    >
                        <option value="">All families with quality data</option>
                        @foreach($productFamilies as $family)
                            <option
                                value="{{ $family->id }}"
                                @selected($filter->productFamilyId === (int) $family->id)
                            >
                                {{ $family->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="product_id" class="form-label">Product</label>
                    <select
                        id="product_id"
                        name="product_id"
                        class="form-select"
                        data-filter-key="product_id"
                    >
                        <option value="">All products with quality data</option>
                        @foreach($products as $product)
                            <option
                                value="{{ $product->id }}"
                                data-family-id="{{ $product->product_family_id }}"
                                @selected($filter->productId === (int) $product->id)
                            >
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="inspection_result" class="form-label">Inspection result</label>
                    <select id="inspection_result" name="inspection_result" class="form-select">
                        <option value="">All inspection results</option>
                        @foreach($inspectionResults as $result)
                            <option
                                value="{{ $result->value }}"
                                @selected($filter->inspectionResult === $result->value)
                            >
                                {{ $result->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="lot_status" class="form-label">Finished-lot status</label>
                    <select id="lot_status" name="lot_status" class="form-select">
                        <option value="">All lot statuses</option>
                        @foreach($lotStatuses as $status)
                            <option
                                value="{{ $status->value }}"
                                @selected($filter->lotStatus === $status->value)
                            >
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="nonconformity_severity" class="form-label">NC severity</label>
                    <select
                        id="nonconformity_severity"
                        name="nonconformity_severity"
                        class="form-select"
                    >
                        <option value="">All severities</option>
                        @foreach($nonconformitySeverities as $severity)
                            <option
                                value="{{ $severity->value }}"
                                @selected($filter->nonconformitySeverity === $severity->value)
                            >
                                {{ $severity->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="nonconformity_status" class="form-label">NC status</label>
                    <select
                        id="nonconformity_status"
                        name="nonconformity_status"
                        class="form-select"
                    >
                        <option value="">All NC statuses</option>
                        @foreach($nonconformityStatuses as $status)
                            <option
                                value="{{ $status->value }}"
                                @selected($filter->nonconformityStatus === $status->value)
                            >
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="lot_number" class="form-label">Lot number contains</label>
                    <input
                        id="lot_number"
                        name="lot_number"
                        type="text"
                        maxlength="120"
                        class="form-control"
                        value="{{ $filter->lotNumber }}"
                        placeholder="Example: LOT-2026"
                    >
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <a href="{{ route('analytics.quality.index') }}" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if($summary->isEmpty())
        <div class="alert alert-secondary" role="status">
            No matching quality data exists for the selected filters.
        </div>
    @else
        <div class="row g-3 mb-4">
            @php
                $cards = [
                    ['Inspections', number_format($summary->inspectionCount), $summary->passedInspectionCount.' passed / '.$summary->failedInspectionCount.' failed'],
                    ['Inspection pass rate', $percentage($summary->inspectionPassPercentage), $summary->conditionalInspectionCount.' conditional / '.$summary->pendingInspectionCount.' pending'],
                    ['Finished lots', number_format($summary->lotCount), $summary->releasedLotCount.' released / '.$summary->blockedLotCount.' blocked / '.$summary->rejectedLotCount.' rejected'],
                    ['Released-lot rate', $percentage($summary->releasedLotPercentage), $percentage($summary->heldRejectedLotPercentage).' blocked or rejected'],
                    ['Nonconformities', number_format($summary->nonconformityCount), $summary->openNonconformityCount.' open/investigating / '.$summary->resolvedNonconformityCount.' resolved'],
                    ['NC rate', $summary->nonconformitiesPer100Inspections === null ? 'N/A' : number_format($summary->nonconformitiesPer100Inspections, 2), 'Nonconformities per 100 inspections'],
                    ['Critical NCs', number_format($summary->criticalNonconformityCount), $summary->majorNonconformityCount.' major / '.$summary->minorNonconformityCount.' minor'],
                ];
            @endphp

            @foreach($cards as [$title, $value, $note])
                <div class="col-md-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted">{{ $title }}</div>
                            <div class="display-6 fs-2">{{ $value }}</div>
                            <div class="small text-muted">{{ $note }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Finished-lot quantities by unit</div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th class="text-end">Lots</th>
                            <th class="text-end">Produced</th>
                            <th class="text-end">Released</th>
                            <th class="text-end">Released rate</th>
                            <th class="text-end">Rejected</th>
                            <th class="text-end">Rejected rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary->quantityUnits as $unit)
                            <tr>
                                <th scope="row">{{ $unit->quantityUnit }}</th>
                                <td class="text-end">{{ number_format($unit->lotCount) }}</td>
                                <td class="text-end">{{ number_format((float) $unit->producedQuantity, 3) }}</td>
                                <td class="text-end">{{ number_format((float) $unit->releasedQuantity, 3) }}</td>
                                <td class="text-end">{{ $percentage($unit->releasedQuantityPercentage) }}</td>
                                <td class="text-end">{{ number_format((float) $unit->rejectedQuantity, 3) }}</td>
                                <td class="text-end">{{ $percentage($unit->rejectedQuantityPercentage) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No quantity data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @foreach([
            'Quality status by production line' => $summary->byProductionLine,
            'Quality status by product family' => $summary->byProductFamily,
            'Quality status by product' => $summary->byProduct,
        ] as $title => $rows)
            <details class="card shadow-sm mb-4" @if($loop->first) open @endif>
                <summary class="card-header fw-semibold">{{ $title }}</summary>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th class="text-end">Inspections</th>
                                <th class="text-end">Passed</th>
                                <th class="text-end">Pass rate</th>
                                <th class="text-end">Lots</th>
                                <th class="text-end">Released</th>
                                <th class="text-end">Release rate</th>
                                <th class="text-end">NCs</th>
                                <th class="text-end">NC / 100 inspections</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <th scope="row">{{ $row->label }}</th>
                                    <td class="text-end">{{ $row->inspectionCount }}</td>
                                    <td class="text-end">{{ $row->passedInspectionCount }}</td>
                                    <td class="text-end">{{ $percentage($row->inspectionPassPercentage) }}</td>
                                    <td class="text-end">{{ $row->lotCount }}</td>
                                    <td class="text-end">{{ $row->releasedLotCount }}</td>
                                    <td class="text-end">{{ $percentage($row->releasedLotPercentage) }}</td>
                                    <td class="text-end">{{ $row->nonconformityCount }}</td>
                                    <td class="text-end">{{ $row->nonconformitiesPer100Inspections === null ? 'N/A' : number_format($row->nonconformitiesPer100Inspections, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-4">No matching rows.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Nonconformities by category</div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Open / investigating</th>
                            <th class="text-end">Resolved</th>
                            <th class="text-end">Minor</th>
                            <th class="text-end">Major</th>
                            <th class="text-end">Critical</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary->nonconformityCategories as $category)
                            <tr>
                                <th scope="row">{{ $category->category }}</th>
                                <td class="text-end">{{ $category->nonconformityCount }}</td>
                                <td class="text-end">{{ $category->openCount }}</td>
                                <td class="text-end">{{ $category->resolvedCount }}</td>
                                <td class="text-end">{{ $category->minorCount }}</td>
                                <td class="text-end">{{ $category->majorCount }}</td>
                                <td class="text-end">{{ $category->criticalCount }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No matching nonconformities.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="small text-muted mb-0">
        Generated at {{ $summary->generatedAt->setTimezone($filter->timezone)->format('Y-m-d H:i:s P') }}.
        All displayed records are simulated ERP or DSS prototype data; they are not real company operational results.
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = @json($filterCompatibilityRows);
    const fields = {
        production_line_id: document.getElementById('production_line_id'),
        product_family_id: document.getElementById('product_family_id'),
        product_id: document.getElementById('product_id'),
    };

    const selectedValue = (key) => {
        const select = fields[key];
        return !select || select.value === '' ? null : select.value;
    };

    const selections = () => ({
        production_line_id: selectedValue('production_line_id'),
        product_family_id: selectedValue('product_family_id'),
        product_id: selectedValue('product_id'),
    });

    const matchesOtherSelections = (row, current, ignoredKey) =>
        Object.entries(current).every(([key, value]) =>
            key === ignoredKey
            || value === null
            || String(row[key] ?? '') === String(value)
        );

    const refresh = () => {
        const current = selections();

        Object.entries(fields).forEach(([key, select]) => {
            if (!select) {
                return;
            }

            Array.from(select.options).forEach((option) => {
                if (option.value === '') {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                if (key === 'product_id') {
                    const familyId = selectedValue('product_family_id');
                    const optionFamily = option.dataset.familyId ?? null;

                    if (
                        familyId !== null
                        && String(optionFamily) !== String(familyId)
                    ) {
                        option.hidden = true;
                        option.disabled = true;
                        return;
                    }
                }

                const compatible = rows.some((row) =>
                    String(row[key] ?? '') === String(option.value)
                    && matchesOtherSelections(row, current, key)
                );

                option.hidden = !compatible;
                option.disabled = !compatible;
            });
        });
    };

    const clearUnavailableSelections = () => {
        Object.values(fields).forEach((select) => {
            if (!select || select.value === '') {
                return;
            }

            const option = select.options[select.selectedIndex];
            if (option.hidden || option.disabled) {
                select.value = '';
            }
        });
    };

    Object.values(fields).forEach((field) => {
        field?.addEventListener('change', () => {
            refresh();
            clearUnavailableSelections();
            refresh();
        });
    });

    refresh();
    clearUnavailableSelections();
    refresh();
});
</script>
@endsection
