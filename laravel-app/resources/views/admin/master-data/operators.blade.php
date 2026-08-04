@extends('layouts.app')

@section('title', 'Operators')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-2">
            ERP operators
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Employee master-data records, separate from DSS login accounts.
        </p>
    </div>

    @include('admin.master-data._navigation')

    <div
        class="security-note rounded-3 p-3 mb-4 small"
    >
        Personal phone numbers and email addresses are deliberately
        excluded from this overview. Only operational identity and
        account-linkage status are displayed.
    </div>

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
                    placeholder="Employee code or name"
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
                            'admin.master-data.operators'
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
                    <th class="px-4 py-3">Employee code</th>
                    <th class="py-3">Operator</th>
                    <th class="py-3">DSS account</th>
                    <th class="py-3">Assignments</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Source</th>
                    <th class="px-4 py-3 text-end">Action</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($operators as $operator)
                    <tr>
                        <td class="px-4 font-monospace">
                            {{ $operator->employee_code }}
                        </td>

                        <td class="fw-semibold">
                            {{ $operator->full_name }}
                        </td>

                        <td>
                            @if ($operator->user_id !== null)
                                <span class="badge text-bg-success">
                                    Linked
                                </span>
                            @else
                                <span class="badge text-bg-light border">
                                    Not linked
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $operator->assignments_count }}
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $operator->is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }}"
                            >
                                {{
                                    $operator->is_active
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </span>
                        </td>

                        <td>
                            {{ $operator->source_system }}
                        </td>

                        <td class="px-4 text-end">
                            <a
                                href="{{
                                    route(
                                        'admin.operator-administration.show',
                                        $operator
                                    )
                                }}"
                                class="btn btn-sm btn-smartfactory"
                            >
                                Manage
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No operators match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($operators->hasPages())
            <div class="border-top p-3">
                {{ $operators->links() }}
            </div>
        @endif
    </div>
@endsection
