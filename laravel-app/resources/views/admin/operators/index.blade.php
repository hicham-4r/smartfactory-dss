@extends('layouts.app')

@section('title', 'Operator administration')

@section('content')
    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold
                      text-secondary mb-2">
                Secure administration
            </p>

            <h1 class="h3 fw-bold mb-1">
                Operator administration
            </h1>

            <p class="text-muted-smartfactory mb-0">
                Link Operator login accounts and manage production-line
                and shift assignments.
            </p>
        </div>

        <a
            href="{{ route('admin.master-data.operators') }}"
            class="btn btn-outline-secondary"
        >
            Open ERP Operator master data
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="security-note rounded-3 p-3 mb-4 small">
        ERP Operator identity remains read-only. This page controls only
        DSS account linkage and manual DSS assignments.
    </div>

    <div class="app-card bg-white p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-lg-8">
                <label for="q" class="form-label">
                    Search
                </label>

                <input
                    type="search"
                    id="q"
                    name="q"
                    value="{{ $search }}"
                    class="form-control"
                    maxlength="100"
                    placeholder="Employee code, Operator name, account name or email"
                >
            </div>

            <div class="col-lg-4 d-flex align-items-end gap-2">
                <button
                    type="submit"
                    class="btn btn-smartfactory"
                >
                    Search
                </button>

                <a
                    href="{{
                        route(
                            'admin.operator-administration.index'
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
                    <th class="py-3">DSS account</th>
                    <th class="py-3">Current assignments</th>
                    <th class="py-3">Total history</th>
                    <th class="py-3">Status</th>
                    <th class="px-4 py-3 text-end">Action</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($operators as $operator)
                    <tr>
                        <td class="px-4">
                            <div class="fw-semibold">
                                {{ $operator->full_name }}
                            </div>

                            <div class="small font-monospace text-muted">
                                {{ $operator->employee_code }}
                            </div>
                        </td>

                        <td>
                            @if ($operator->user !== null)
                                <div class="fw-semibold">
                                    {{ $operator->user->name }}
                                </div>

                                <div class="small text-muted">
                                    {{ $operator->user->email }}
                                </div>
                            @else
                                <span class="badge text-bg-warning">
                                    Not linked
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $operator->current_assignments_count }}
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
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            No Operators match the search.
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
