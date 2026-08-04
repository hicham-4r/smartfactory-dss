@extends('layouts.app')

@section('title', 'Machines')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-2">
            Machines
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Ordered machine composition for each production line.
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
                    placeholder="Machine code or type"
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

            <div class="col-lg-2">
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
                            'admin.master-data.machines'
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
                    <th class="px-4 py-3">Sequence</th>
                    <th class="py-3">Machine</th>
                    <th class="py-3">Type</th>
                    <th class="py-3">Production line</th>
                    <th class="py-3">Criticality</th>
                    <th class="py-3">Status</th>
                    <th class="px-4 py-3">Source</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($machines as $machine)
                    <tr>
                        <td class="px-4">
                            {{ $machine->sequence_number ?? '—' }}
                        </td>

                        <td>
                            <div class="fw-semibold">
                                {{ $machine->name }}
                            </div>

                            <div class="small font-monospace text-muted">
                                {{ $machine->code }}
                            </div>
                        </td>

                        <td>
                            {{ $machine->machine_type }}
                        </td>

                        <td>
                            {{ $machine->productionLine->code }}
                        </td>

                        <td>
                            @if ($machine->is_critical)
                                <span class="badge text-bg-danger">
                                    Critical
                                </span>
                            @else
                                <span class="badge text-bg-light border">
                                    Standard
                                </span>
                            @endif
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $machine->is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }}"
                            >
                                {{
                                    $machine->is_active
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </span>
                        </td>

                        <td class="px-4">
                            {{ $machine->source_system }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No machines match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($machines->hasPages())
            <div class="border-top p-3">
                {{ $machines->links() }}
            </div>
        @endif
    </div>
@endsection