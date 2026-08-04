@extends('layouts.app')

@section('title', 'Production lines')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-2">
            Production lines
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Production capacity and machine allocation by line.
        </p>
    </div>

    @include('admin.master-data._navigation')

    <div class="app-card bg-white p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-lg-4">
                <label for="q" class="form-label">
                    Search
                </label>

                <input
                    type="search"
                    id="q"
                    name="q"
                    value="{{ $filters['q'] }}"
                    class="form-control"
                    maxlength="100"
                    placeholder="Code, line name or area"
                >
            </div>

            <div class="col-lg-2">
                <label for="status" class="form-label">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                    class="form-select"
                >
                    @foreach (
                        [
                            'all' => 'All',
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                        ]
                        as $value => $label
                    )
                        <option
                            value="{{ $value }}"
                            @selected(
                                $filters['status'] === $value
                            )
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-3">
                <label
                    for="source_system"
                    class="form-label"
                >
                    Source
                </label>

                <input
                    type="text"
                    id="source_system"
                    name="source_system"
                    value="{{ $filters['source_system'] }}"
                    class="form-control"
                    maxlength="50"
                    placeholder="simulated_sage"
                >
            </div>

            <div class="col-lg-3 d-flex align-items-end gap-2">
                <button
                    type="submit"
                    class="btn btn-smartfactory"
                >
                    Filter
                </button>

                <a
                    href="{{
                        route(
                            'admin.master-data.production-lines'
                        )
                    }}"
                    class="btn btn-outline-secondary"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="app-card bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="py-3">Production line</th>
                    <th class="py-3">Area</th>
                    <th class="py-3">Nominal capacity</th>
                    <th class="py-3">Machines</th>
                    <th class="py-3">Status</th>
                    <th class="px-4 py-3">Source</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($productionLines as $line)
                    <tr>
                        <td class="px-4 font-monospace">
                            {{ $line->code }}
                        </td>

                        <td>
                            <div class="fw-semibold">
                                {{ $line->name }}
                            </div>

                            <div class="small text-muted">
                                {{ $line->description ?? '—' }}
                            </div>
                        </td>

                        <td>
                            {{ $line->plant_area ?? '—' }}
                        </td>

                        <td>
                            @if (
                                $line
                                    ->nominal_capacity_per_hour
                                !== null
                            )
                                {{
                                    $line
                                        ->nominal_capacity_per_hour
                                }}
                                {{ $line->capacity_unit }}
                            @else
                                —
                            @endif
                        </td>

                        <td>
                            {{ $line->machines_count }}
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $line->is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }}"
                            >
                                {{
                                    $line->is_active
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </span>
                        </td>

                        <td class="px-4">
                            {{ $line->source_system }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No production lines match the filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($productionLines->hasPages())
            <div class="border-top p-3">
                {{ $productionLines->links() }}
            </div>
        @endif
    </div>
@endsection