@extends('layouts.app')

@section('title', 'Production KPI Summary')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Production KPI Summary</h1>
            <p class="text-muted mb-0">
                Deterministic indicators from DSS production orders and production records.
            </p>
        </div>

        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            Dashboard
        </a>
    </div>

    @include(
        'analytics.partials.active-drilldowns',
        [
            'domain' => 'production',
            'filter' => $filter,
        ]
    )

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-2">The filters could not be applied.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-secondary" role="note">
        <strong>Filter behavior:</strong>
        selected filters use <strong>AND</strong>. The dropdowns now contain only
        canonical, data-backed choices for the selected period. Exact-name duplicates
        are merged, and incompatible choices are hidden instead of displayed in grey.
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Filters</div>

        <div class="card-body">
            <form
                method="GET"
                action="{{ route('analytics.production.index') }}"
                class="row g-3"
                id="production-kpi-filter-form"
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
                        @foreach ($timezoneOptions as $timezone)
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
                    <label for="production_line_id" class="form-label">
                        Production line
                    </label>
                    <select
                        id="production_line_id"
                        name="production_line_id"
                        class="form-select"
                        data-filter-key="production_line_id"
                    >
                        <option value="">All lines</option>
                        @foreach ($productionLines as $line)
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
                    <label for="product_family_id" class="form-label">
                        Product family
                    </label>
                    <select
                        id="product_family_id"
                        name="product_family_id"
                        class="form-select"
                        data-filter-key="product_family_id"
                    >
                        <option value="">All families</option>
                        @foreach ($productFamilies as $family)
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
                        <option value="">All products</option>
                        @foreach ($products as $product)
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

                <div class="col-md-4 col-xl-2">
                    <label for="shift_id" class="form-label">Shift</label>
                    <select
                        id="shift_id"
                        name="shift_id"
                        class="form-select"
                        data-filter-key="shift_key"
                    >
                        <option value="">All shifts</option>
                        @foreach ($shifts as $shift)
                            <option
                                value="{{ $shift->id }}"
                                data-filter-value="{{ $shift->shift_key }}"
                                @selected($filter->shiftId === (int) $shift->id)
                            >
                                {{ $shift->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="production_order_id" class="form-label">
                        Production order
                    </label>
                    <select
                        id="production_order_id"
                        name="production_order_id"
                        class="form-select"
                    >
                        <option value="">All orders</option>
                        @foreach ($productionOrders as $order)
                            <option
                                value="{{ $order->id }}"
                                @selected($filter->productionOrderId === (int) $order->id)
                            >
                                {{ $order->order_number }} Ã¢â‚¬â€ {{ str_replace('_', ' ', $order->status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label for="status" class="form-label">Order status</label>
                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >
                        <option value="">All execution statuses</option>
                        @foreach ($orderStatuses as $status)
                            <option
                                value="{{ $status->value }}"
                                @selected($filter->status === $status->value)
                            >
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        In-progress orders may include pending records as provisional.
                        Completed orders use validated production records only.
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <a
                        href="{{ route('analytics.production.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset all filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="alert {{ $summary->isProvisional() ? 'alert-warning' : 'alert-info' }}" role="status">
        <div>
            Period:
            <strong>{{ $filter->startDateString() }}</strong>
            to
            <strong>{{ $filter->endDateString() }}</strong>
            in
            <strong>{{ $filter->timezone }}</strong>.
        </div>
        <div class="mt-1">
            <strong>Data basis:</strong> {{ $summary->dataBasisLabel() }}
        </div>
        <div class="mt-1 small">
            {{ $summary->targetOrderCount }} contributing order(s),
            {{ $summary->validatedRecordCount }} validated record(s),
            {{ $summary->provisionalRecordCount }} provisional record(s).
        </div>
    </div>

    @if ($summary->isEmpty())
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <h2 class="h5">No matching production data</h2>
                <p class="text-muted mb-3">
                    No production order or eligible production record matches the selected period and filters.
                </p>
                <a
                    href="{{ route('analytics.production.index') }}"
                    class="btn btn-outline-primary"
                >
                    Reset all filters
                </a>
            </div>
        </div>
    @elseif ($summary->primaryUnit())
        @php($unit = $summary->primaryUnit())
        @php($hasActual = $unit->hasActualProduction())

        <div class="row g-3 mb-4">
            <div class="col-md-4 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">
                            {{ $filter->shiftId !== null ? 'Scheduled batch quantity' : 'Target quantity' }}
                        </div>
                        <div class="h3 mb-0">{{ number_format((float) $unit->targetQuantity, 3) }}</div>
                        <div class="small text-muted">{{ $unit->quantityUnit }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">
                            Actual quantity
                            @if ($unit->isProvisional())
                                <span class="badge text-bg-warning ms-1">Provisional</span>
                            @endif
                        </div>
                        <div class="h3 mb-0">
                            {{ $hasActual ? number_format((float) $unit->actualQuantity, 3) : 'N/A' }}
                        </div>
                        <div class="small text-muted">{{ $unit->quantityUnit }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Good quantity</div>
                        <div class="h3 mb-0">
                            {{ $hasActual ? number_format((float) $unit->goodQuantity, 3) : 'N/A' }}
                        </div>
                        <div class="small text-muted">{{ $unit->quantityUnit }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Rejected quantity</div>
                        <div class="h3 mb-0">
                            {{ $hasActual ? number_format((float) $unit->rejectedQuantity, 3) : 'N/A' }}
                        </div>
                        <div class="small text-muted">{{ $unit->quantityUnit }}</div>
                    </div>
                </div>
            </div>

            @foreach ([
                ['Target achievement', $unit->achievementPercentage, '%'],
                ['Rejection rate', $unit->rejectionPercentage, '%'],
                ['Observed utilization', $unit->observedUtilizationPercentage, '%'],
            ] as [$label, $value, $suffix])
                <div class="col-md-4 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="h3 mb-0">
                                {{ $value === null ? 'N/A' : number_format($value, 2).$suffix }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="col-md-4 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Average production rate</div>
                        <div class="h3 mb-0">
                            {{ $unit->averageProductionRatePerHour === null ? 'N/A' : number_format((float) $unit->averageProductionRatePerHour, 3) }}
                        </div>
                        <div class="small text-muted">{{ $unit->quantityUnit }}/hour</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Operating-time summary</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-muted small">Runtime</div>
                        <div class="h4 mb-0">
                            {{ $hasActual ? number_format($unit->runtimeMinutes).' minutes' : 'N/A' }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Downtime</div>
                        <div class="h4 mb-0">
                            {{ $hasActual ? number_format($unit->downtimeMinutes).' minutes' : 'N/A' }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Validated records</div>
                        <div class="h4 mb-0">{{ number_format($unit->validatedRecordCount) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Provisional records</div>
                        <div class="h4 mb-0">{{ number_format($unit->provisionalRecordCount) }}</div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning" role="alert">
            The selected period contains more than one quantity unit. Units are shown separately to prevent invalid aggregation.
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">KPI summary by quantity unit</div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th class="text-end">Target</th>
                            <th class="text-end">Actual</th>
                            <th class="text-end">Good</th>
                            <th class="text-end">Rejected</th>
                            <th class="text-end">Achievement</th>
                            <th class="text-end">Rejection</th>
                            <th class="text-end">Validated</th>
                            <th class="text-end">Provisional</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary->units as $unit)
                            <tr>
                                <td>{{ $unit->quantityUnit }}</td>
                                <td class="text-end">{{ number_format((float) $unit->targetQuantity, 3) }}</td>
                                <td class="text-end">{{ $unit->hasActualProduction() ? number_format((float) $unit->actualQuantity, 3) : 'N/A' }}</td>
                                <td class="text-end">{{ $unit->hasActualProduction() ? number_format((float) $unit->goodQuantity, 3) : 'N/A' }}</td>
                                <td class="text-end">{{ $unit->hasActualProduction() ? number_format((float) $unit->rejectedQuantity, 3) : 'N/A' }}</td>
                                <td class="text-end">{{ $unit->achievementPercentage === null ? 'N/A' : number_format($unit->achievementPercentage, 2).'%' }}</td>
                                <td class="text-end">{{ $unit->rejectionPercentage === null ? 'N/A' : number_format($unit->rejectionPercentage, 2).'%' }}</td>
                                <td class="text-end">{{ $unit->validatedRecordCount }}</td>
                                <td class="text-end">{{ $unit->provisionalRecordCount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@include(
    'analytics.production.partials.breakdowns',
    ['report' => $breakdownReport]
)

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = @json($filterCompatibilityRows);
    const form = document.getElementById('production-kpi-filter-form');

    if (!form || !Array.isArray(rows)) {
        return;
    }

    const fields = {
        production_line_id: document.getElementById('production_line_id'),
        product_family_id: document.getElementById('product_family_id'),
        product_id: document.getElementById('product_id'),
        shift_key: document.getElementById('shift_id'),
    };

    const statusField = document.getElementById('status');
    const productionOrder = document.getElementById('production_order_id');

    const selectedValue = (key) => {
        const select = fields[key];

        if (!select || select.value === '') {
            return null;
        }

        const option = select.options[select.selectedIndex];

        return option.dataset.filterValue ?? select.value;
    };

    const currentSelections = () => ({
        production_line_id: selectedValue('production_line_id'),
        product_family_id: selectedValue('product_family_id'),
        product_id: selectedValue('product_id'),
        shift_key: selectedValue('shift_key'),
    });

    const rowMatches = (row, selections, ignoredKey = null) => {
        return Object.entries(selections).every(([key, value]) => {
            if (key === ignoredKey || value === null) {
                return true;
            }

            return String(row[key] ?? '') === String(value);
        });
    };

    const refreshAvailability = () => {
        const selections = currentSelections();

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
                    const optionFamilyId = option.dataset.familyId ?? null;

                    if (
                        familyId !== null
                        && String(optionFamilyId) !== String(familyId)
                    ) {
                        option.hidden = true;
                        option.disabled = true;
                        return;
                    }
                }

                const candidate = option.dataset.filterValue ?? option.value;
                const compatible = rows.some((row) => {
                    if (String(row[key] ?? '') !== String(candidate)) {
                        return false;
                    }

                    return rowMatches(row, selections, key);
                });

                // Incompatible choices disappear. They are never left visible
                // as confusing grey/disabled options.
                option.hidden = !compatible;
                option.disabled = !compatible;
            });
        });
    };

    const clearHiddenSelections = () => {
        Object.values(fields).forEach((select) => {
            if (!select || select.value === '') {
                return;
            }

            const selected = select.options[select.selectedIndex];

            if (selected.hidden || selected.disabled) {
                select.value = '';
            }
        });
    };

    Object.values(fields).forEach((field) => {
        field?.addEventListener('change', () => {
            if (productionOrder) {
                productionOrder.value = '';
            }

            refreshAvailability();
            clearHiddenSelections();
            refreshAvailability();
        });
    });

    statusField?.addEventListener('change', () => {
        if (productionOrder) {
            productionOrder.value = '';
        }
    });

    ['start_date', 'end_date', 'timezone'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            if (productionOrder) {
                productionOrder.value = '';
            }
        });
    });

    refreshAvailability();
    clearHiddenSelections();
    refreshAvailability();
});
</script>
@endsection
