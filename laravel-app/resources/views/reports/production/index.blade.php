@extends('layouts.app')

@section('title', 'Production Reports')

@section('content')
    {{-- STEP21O_AI_NAVIGATION_START --}}
    @include('ai.insights.partials.navigation-card', ['context' => 'reports'])
    {{-- STEP21O_AI_NAVIGATION_END --}}
@php
    $exportQuery = array_filter(
        [
            'report_type' => $reportType->value,
            ...$filter->toArray(),
        ],
        static fn (mixed $value): bool =>
            $value !== null
            && $value !== ''
    );
@endphp

<div class="container py-4">
    <div
        class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"
    >
        <div>
            <h1 class="h3 mb-1">
                Production reporting
            </h1>

            <p class="text-muted mb-0">
                Secure deterministic reports generated from validated
                production KPI services.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a
                href="{{ route('analytics.production.index', $filter->toArray()) }}"
                class="btn btn-outline-secondary"
            >
                Production analytics
            </a>

            <a
                href="{{ route('dashboard') }}"
                class="btn btn-outline-secondary"
            >
                Dashboard
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div
            class="alert alert-danger"
            role="alert"
        >
            <div class="fw-semibold mb-2">
                The report filters could not be applied.
            </div>

            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            Report configuration
        </div>

        <div class="card-body">
            <form
                method="GET"
                action="{{ route('reports.index') }}"
                class="row g-3"
            >
                <div class="col-md-6 col-xl-3">
                    <label
                        for="report_type"
                        class="form-label"
                    >
                        Report type
                    </label>

                    <select
                        id="report_type"
                        name="report_type"
                        class="form-select"
                        required
                    >
                        @foreach ($reportTypes as $type)
                            <option
                                value="{{ $type->value }}"
                                @selected(
                                    $reportType === $type
                                )
                            >
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 col-xl-2">
                    <label
                        for="start_date"
                        class="form-label"
                    >
                        Start date
                    </label>

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
                    <label
                        for="end_date"
                        class="form-label"
                    >
                        End date
                    </label>

                    <input
                        id="end_date"
                        name="end_date"
                        type="date"
                        class="form-control"
                        value="{{ $filter->endDateString() }}"
                        required
                    >
                </div>

                <div class="col-md-4 col-xl-2">
                    <label
                        for="timezone"
                        class="form-label"
                    >
                        Timezone
                    </label>

                    <select
                        id="timezone"
                        name="timezone"
                        class="form-select"
                        required
                    >
                        @foreach ($timezoneOptions as $timezone)
                            <option
                                value="{{ $timezone }}"
                                @selected(
                                    $filter->timezone
                                    === $timezone
                                )
                            >
                                {{ $timezone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label
                        for="production_line_id"
                        class="form-label"
                    >
                        Production line
                    </label>

                    <select
                        id="production_line_id"
                        name="production_line_id"
                        class="form-select"
                    >
                        <option value="">
                            All lines
                        </option>

                        @foreach ($productionLines as $line)
                            <option
                                value="{{ $line->id }}"
                                @selected(
                                    $filter->productionLineId
                                    === (int) $line->id
                                )
                            >
                                {{ $line->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label
                        for="product_family_id"
                        class="form-label"
                    >
                        Product family
                    </label>

                    <select
                        id="product_family_id"
                        name="product_family_id"
                        class="form-select"
                    >
                        <option value="">
                            All families
                        </option>

                        @foreach ($productFamilies as $family)
                            <option
                                value="{{ $family->id }}"
                                @selected(
                                    $filter->productFamilyId
                                    === (int) $family->id
                                )
                            >
                                {{ $family->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label
                        for="product_id"
                        class="form-label"
                    >
                        Product
                    </label>

                    <select
                        id="product_id"
                        name="product_id"
                        class="form-select"
                    >
                        <option value="">
                            All products
                        </option>

                        @foreach ($products as $product)
                            <option
                                value="{{ $product->id }}"
                                @selected(
                                    $filter->productId
                                    === (int) $product->id
                                )
                            >
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-2">
                    <label
                        for="shift_id"
                        class="form-label"
                    >
                        Shift
                    </label>

                    <select
                        id="shift_id"
                        name="shift_id"
                        class="form-select"
                    >
                        <option value="">
                            All shifts
                        </option>

                        @foreach ($shifts as $shift)
                            <option
                                value="{{ $shift->id }}"
                                @selected(
                                    $filter->shiftId
                                    === (int) $shift->id
                                )
                            >
                                {{ $shift->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-3">
                    <label
                        for="production_order_id"
                        class="form-label"
                    >
                        Production order
                    </label>

                    <select
                        id="production_order_id"
                        name="production_order_id"
                        class="form-select"
                    >
                        <option value="">
                            All orders
                        </option>

                        @foreach ($productionOrders as $order)
                            <option
                                value="{{ $order->id }}"
                                @selected(
                                    $filter->productionOrderId
                                    === (int) $order->id
                                )
                            >
                                {{ $order->order_number }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-xl-2">
                    <label
                        for="status"
                        class="form-label"
                    >
                        Execution status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >
                        <option value="">
                            All execution statuses
                        </option>

                        @foreach ($orderStatuses as $status)
                            <option
                                value="{{ $status->value }}"
                                @selected(
                                    $filter->status
                                    === $status->value
                                )
                            >
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div
                    class="col-12 d-flex flex-wrap align-items-end gap-2"
                >
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Generate preview
                    </button>

                    <a
                        href="{{ route('reports.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div
            class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3"
        >
            <div>
                <div class="fw-semibold">
                    {{ $report->title }}
                </div>

                <div class="small text-muted">
                    Generated by {{ $report->generatedByName }}
                    on
                    {{
                        $report->generatedAt
                            ->setTimezone(
                                $filter->timezone
                            )
                            ->format('Y-m-d H:i:s T')
                    }}
                </div>
            </div>

            @if ($canExport)
                <div class="d-flex flex-wrap gap-2">
                    <a
                        href="{{
                            route(
                                'reports.production.export',
                                [
                                    'format' => 'csv',
                                    ...$exportQuery,
                                ]
                            )
                        }}"
                        class="btn btn-sm btn-outline-primary"
                    >
                        Download CSV
                    </a>

                    <a
                        href="{{
                            route(
                                'reports.production.export',
                                [
                                    'format' => 'xlsx',
                                    ...$exportQuery,
                                ]
                            )
                        }}"
                        class="btn btn-sm btn-outline-success"
                    >
                        Download Excel
                    </a>

                    <a
                        href="{{
                            route(
                                'reports.production.export',
                                [
                                    'format' => 'pdf',
                                    ...$exportQuery,
                                ]
                            )
                        }}"
                        class="btn btn-sm btn-outline-danger"
                    >
                        Download PDF
                    </a>
                </div>
            @endif
        </div>

        <div class="card-body">
            <div class="row g-3">
                @foreach ($report->appliedFilters as $label => $value)
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted">
                                {{ $label }}
                            </div>

                            <div class="fw-semibold">
                                {{ $value }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            Production KPI summary
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">
                            Unit
                        </th>
                        <th scope="col">
                            Target
                        </th>
                        <th scope="col">
                            Actual
                        </th>
                        <th scope="col">
                            Good
                        </th>
                        <th scope="col">
                            Rejected
                        </th>
                        <th scope="col">
                            Achievement
                        </th>
                        <th scope="col">
                            Rejection
                        </th>
                        <th scope="col">
                            Runtime
                        </th>
                        <th scope="col">
                            Downtime
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($report->summary->units as $unit)
                        <tr>
                            <td>
                                {{ $unit->quantityUnit }}
                            </td>
                            <td>
                                {{ $unit->targetQuantity }}
                            </td>
                            <td>
                                {{ $unit->actualQuantity }}
                            </td>
                            <td>
                                {{ $unit->goodQuantity }}
                            </td>
                            <td>
                                {{ $unit->rejectedQuantity }}
                            </td>
                            <td>
                                {{
                                    $unit->achievementPercentage
                                    === null
                                        ? 'N/A'
                                        : number_format(
                                            $unit->achievementPercentage,
                                            2
                                        ).'%'
                                }}
                            </td>
                            <td>
                                {{
                                    $unit->rejectionPercentage
                                    === null
                                        ? 'N/A'
                                        : number_format(
                                            $unit->rejectionPercentage,
                                            2
                                        ).'%'
                                }}
                            </td>
                            <td>
                                {{ $unit->runtimeMinutes }} min
                            </td>
                            <td>
                                {{ $unit->downtimeMinutes }} min
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="9"
                                class="text-center text-muted py-4"
                            >
                                No matching production data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            {{ $report->primaryDimensionLabel() }} breakdown
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">
                            {{ $report->primaryDimensionLabel() }}
                        </th>
                        <th scope="col">
                            Unit
                        </th>
                        <th scope="col">
                            Target
                        </th>
                        <th scope="col">
                            Actual
                        </th>
                        <th scope="col">
                            Good
                        </th>
                        <th scope="col">
                            Rejected
                        </th>
                        <th scope="col">
                            Achievement
                        </th>
                        <th scope="col">
                            Rejection
                        </th>
                        <th scope="col">
                            Records
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($report->primaryRows() as $row)
                        <tr>
                            <td>
                                {{ $row->label }}
                            </td>
                            <td>
                                {{ $row->quantityUnit }}
                            </td>
                            <td>
                                {{ $row->targetQuantity }}
                            </td>
                            <td>
                                {{ $row->actualQuantity }}
                            </td>
                            <td>
                                {{ $row->goodQuantity }}
                            </td>
                            <td>
                                {{ $row->rejectedQuantity }}
                            </td>
                            <td>
                                {{
                                    $row->achievementPercentage
                                    === null
                                        ? 'N/A'
                                        : number_format(
                                            $row->achievementPercentage,
                                            2
                                        ).'%'
                                }}
                            </td>
                            <td>
                                {{
                                    $row->rejectionPercentage
                                    === null
                                        ? 'N/A'
                                        : number_format(
                                            $row->rejectionPercentage,
                                            2
                                        ).'%'
                                }}
                            </td>
                            <td>
                                {{ $row->recordCount }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="9"
                                class="text-center text-muted py-4"
                            >
                                No matching breakdown data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer small text-muted">
            {{ $report->dataBasisLabel() }}
        </div>
    </div>
</div>
@endsection
