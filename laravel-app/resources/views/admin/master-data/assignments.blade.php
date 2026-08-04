@extends('layouts.app')

@section('title', 'Operator assignments')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-2">
            Operator assignments
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Operator allocation by production line and shift.
        </p>
    </div>

    @include('admin.master-data._navigation')

    <div class="app-card bg-white p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-lg-3">
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
                    placeholder="Operator, line or shift"
                >
            </div>

            <div class="col-lg-3">
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
                        All production lines
                    </option>

                    @foreach ($productionLines as $line)
                        <option
                            value="{{ $line->id }}"
                            @selected(
                                (int) $filters[
                                    'production_line_id'
                                ]
                                === $line->id
                            )
                        >
                            {{ $line->code }} — {{ $line->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-2">
                <label for="shift_id" class="form-label">
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
                                (int) $filters['shift_id']
                                === $shift->id
                            )
                        >
                            {{ $shift->name }}
                        </option>
                    @endforeach
                </select>
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
                            'admin.master-data.assignments'
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
                    <th class="px-4 py-3">Operator</th>
                    <th class="py-3">Production line</th>
                    <th class="py-3">Shift</th>
                    <th class="py-3">Effective period</th>
                    <th class="py-3">Priority</th>
                    <th class="py-3">Status</th>
                    <th class="px-4 py-3">Source</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($assignments as $assignment)
                    <tr>
                        <td class="px-4">
                            <div class="fw-semibold">
                                {{ $assignment->operator->full_name }}
                            </div>

                            <div class="small font-monospace text-muted">
                                {{
                                    $assignment
                                        ->operator
                                        ->employee_code
                                }}
                            </div>
                        </td>

                        <td>
                            <div class="fw-semibold">
                                {{
                                    $assignment
                                        ->productionLine
                                        ->code
                                }}
                            </div>

                            <div class="small text-muted">
                                {{
                                    $assignment
                                        ->productionLine
                                        ->name
                                }}
                            </div>
                        </td>

                        <td>
                            {{ $assignment->shift->name }}
                        </td>

                        <td>
                            {{
                                $assignment
                                    ->starts_on
                                    ->format('Y-m-d')
                            }}

                            —

                            {{
                                $assignment->ends_on
                                    ?->format('Y-m-d')
                                ?? 'Open'
                            }}
                        </td>

                        <td>
                            @if ($assignment->is_primary)
                                <span class="badge text-bg-primary">
                                    Primary
                                </span>
                            @else
                                <span class="badge text-bg-light border">
                                    Secondary
                                </span>
                            @endif
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $assignment->is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }}"
                            >
                                {{
                                    $assignment->is_active
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </span>
                        </td>

                        <td class="px-4">
                            {{ $assignment->source_system }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No assignments match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($assignments->hasPages())
            <div class="border-top p-3">
                {{ $assignments->links() }}
            </div>
        @endif
    </div>
@endsection