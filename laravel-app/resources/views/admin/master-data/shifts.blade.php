@extends('layouts.app')

@section('title', 'Shifts')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-2">
            Production shifts
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Working periods used for production and operator allocation.
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
                    placeholder="Shift code or name"
                >
            </div>

            <div class="col-lg-3">
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
                >
            </div>

            <div class="col-lg-2 d-flex align-items-end gap-2">
                <button
                    type="submit"
                    class="btn btn-smartfactory"
                >
                    Filter
                </button>

                <a
                    href="{{
                        route(
                            'admin.master-data.shifts'
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
                    <th class="py-3">Shift</th>
                    <th class="py-3">Starts</th>
                    <th class="py-3">Ends</th>
                    <th class="py-3">Night crossing</th>
                    <th class="py-3">Assignments</th>
                    <th class="py-3">Status</th>
                    <th class="px-4 py-3">Source</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($shifts as $shift)
                    <tr>
                        <td class="px-4 font-monospace">
                            {{ $shift->code }}
                        </td>

                        <td class="fw-semibold">
                            {{ $shift->name }}
                        </td>

                        <td>
                            {{
                                substr(
                                    (string) $shift->starts_at,
                                    0,
                                    5
                                )
                            }}
                        </td>

                        <td>
                            {{
                                substr(
                                    (string) $shift->ends_at,
                                    0,
                                    5
                                )
                            }}
                        </td>

                        <td>
                            {{
                                $shift->crosses_midnight
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </td>

                        <td>
                            {{
                                $shift
                                    ->operator_assignments_count
                            }}
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $shift->is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }}"
                            >
                                {{
                                    $shift->is_active
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </span>
                        </td>

                        <td class="px-4">
                            {{ $shift->source_system }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="8"
                            class="text-center text-muted py-5"
                        >
                            No shifts match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($shifts->hasPages())
            <div class="border-top p-3">
                {{ $shifts->links() }}
            </div>
        @endif
    </div>
@endsection