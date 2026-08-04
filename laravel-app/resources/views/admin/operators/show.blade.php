@extends('layouts.app')

@section('title', 'Manage Operator')

@section('content')
    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold
                      text-secondary mb-2">
                Operator administration
            </p>

            <h1 class="h3 fw-bold mb-1">
                {{ $operator->full_name }}
            </h1>

            <p class="text-muted-smartfactory mb-0">
                Employee code:
                <span class="font-monospace">
                    {{ $operator->employee_code }}
                </span>
            </p>
        </div>

        <a
            href="{{
                route(
                    'admin.operator-administration.index'
                )
            }}"
            class="btn btn-outline-secondary"
        >
            Back to Operators
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($operator->is_active)
        <div class="alert alert-warning" role="alert">
            This ERP Operator is inactive. New account linkage and new
            assignments are blocked until the source record becomes active.
        </div>
    @endunless

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="app-card bg-white h-100 p-4">
                <p class="text-uppercase small fw-semibold
                          text-secondary mb-2">
                    DSS account
                </p>

                <h2 class="h5 fw-bold mb-3">
                    Account linkage
                </h2>

                @if ($operator->user !== null)
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="fw-semibold">
                            {{ $operator->user->name }}
                        </div>

                        <div class="text-muted small">
                            {{ $operator->user->email }}
                        </div>

                        <div class="mt-2">
                            <span class="badge text-bg-success">
                                Linked
                            </span>

                            @foreach ($operator->user->roles as $role)
                                <span class="badge text-bg-light border">
                                    {{ $role->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    @can(\App\Enums\PermissionName::UpdateUsers->value)
                        <form
                            method="POST"
                            action="{{
                                route(
                                    'admin.operator-administration.account.unlink',
                                    $operator
                                )
                            }}"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="btn btn-outline-danger"
                                onclick="return confirm(
                                    'Unlink this Operator account?'
                                )"
                            >
                                Unlink account
                            </button>
                        </form>
                    @endcan
                @else
                    <p class="text-muted-smartfactory">
                        Select an active DSS account with the
                        <strong>Operator</strong> role.
                    </p>

                    @can(\App\Enums\PermissionName::UpdateUsers->value)
                        <form
                            method="POST"
                            action="{{
                                route(
                                    'admin.operator-administration.account.link',
                                    $operator
                                )
                            }}"
                        >
                            @csrf

                            <div class="mb-3">
                                <label
                                    for="user_id"
                                    class="form-label"
                                >
                                    Operator account
                                </label>

                                <select
                                    id="user_id"
                                    name="user_id"
                                    class="form-select"
                                    required
                                    @disabled(! $operator->is_active)
                                >
                                    <option value="">
                                        Select an account
                                    </option>

                                    @foreach (
                                        $accountOptions
                                        as $account
                                    )
                                        <option
                                            value="{{ $account->id }}"
                                            @selected(
                                                (int) old('user_id')
                                                === $account->id
                                            )
                                        >
                                            {{ $account->name }}
                                            — {{ $account->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            @if ($accountOptions->isEmpty())
                                <div class="alert alert-info small">
                                    No available active Operator account
                                    exists. Create an account first from
                                    User administration.
                                </div>
                            @endif

                            <button
                                type="submit"
                                class="btn btn-smartfactory"
                                @disabled(
                                    ! $operator->is_active
                                    || $accountOptions->isEmpty()
                                )
                            >
                                Link account
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        <div class="col-xl-7">
            <div class="app-card bg-white h-100 p-4">
                <p class="text-uppercase small fw-semibold
                          text-secondary mb-2">
                    Production allocation
                </p>

                <h2 class="h5 fw-bold mb-3">
                    Create manual assignment
                </h2>

                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.operator-administration.assignments.store',
                            $operator
                        )
                    }}"
                    class="row g-3"
                >
                    @csrf

                    <div class="col-md-6">
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
                            required
                            @disabled(! $operator->is_active)
                        >
                            <option value="">
                                Select a line
                            </option>

                            @foreach ($productionLines as $line)
                                <option
                                    value="{{ $line->id }}"
                                    @selected(
                                        (int) old(
                                            'production_line_id'
                                        ) === $line->id
                                    )
                                >
                                    {{ $line->code }}
                                    — {{ $line->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
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
                            required
                            @disabled(! $operator->is_active)
                        >
                            <option value="">
                                Select a shift
                            </option>

                            @foreach ($shifts as $shift)
                                <option
                                    value="{{ $shift->id }}"
                                    @selected(
                                        (int) old('shift_id')
                                        === $shift->id
                                    )
                                >
                                    {{ $shift->name }}
                                    ({{ $shift->starts_at }}
                                    – {{ $shift->ends_at }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label
                            for="starts_on"
                            class="form-label"
                        >
                            Starts on
                        </label>

                        <input
                            type="date"
                            id="starts_on"
                            name="starts_on"
                            value="{{ old('starts_on', $today) }}"
                            class="form-control"
                            required
                            @disabled(! $operator->is_active)
                        >
                    </div>

                    <div class="col-md-4">
                        <label
                            for="ends_on"
                            class="form-label"
                        >
                            Ends on
                        </label>

                        <input
                            type="date"
                            id="ends_on"
                            name="ends_on"
                            value="{{ old('ends_on') }}"
                            class="form-control"
                            @disabled(! $operator->is_active)
                        >

                        <div class="form-text">
                            Leave empty for an open assignment.
                        </div>
                    </div>

                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check mt-4">
                            <input
                                type="hidden"
                                name="is_primary"
                                value="0"
                            >

                            <input
                                type="checkbox"
                                id="is_primary"
                                name="is_primary"
                                value="1"
                                class="form-check-input"
                                @checked(old('is_primary', true))
                                @disabled(! $operator->is_active)
                            >

                            <label
                                for="is_primary"
                                class="form-check-label"
                            >
                                Primary assignment
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button
                            type="submit"
                            class="btn btn-smartfactory"
                            @disabled(
                                ! $operator->is_active
                                || $productionLines->isEmpty()
                                || $shifts->isEmpty()
                            )
                        >
                            Assign line and shift
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="app-card bg-white overflow-hidden">
        <div class="border-bottom p-4">
            <h2 class="h5 fw-bold mb-1">
                Assignment history
            </h2>

            <p class="text-muted-smartfactory mb-0">
                ERP-synchronized assignments are read-only. Manual DSS
                assignments can be edited or ended.
            </p>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th class="px-4 py-3">Line and shift</th>
                    <th class="py-3">Effective period</th>
                    <th class="py-3">Priority</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Source</th>
                    <th class="px-4 py-3 text-end">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($operator->assignments as $assignment)
                    @php
                        $manualAssignment = in_array(
                            $assignment->source_system,
                            ['manual', 'manual_dss'],
                            true
                        );
                    @endphp

                    <tr>
                        <td class="px-4">
                            <div class="fw-semibold">
                                {{
                                    $assignment
                                        ->productionLine
                                        ->code
                                }}
                                —
                                {{
                                    $assignment
                                        ->productionLine
                                        ->name
                                }}
                            </div>

                            <div class="small text-muted">
                                {{ $assignment->shift->name }}
                            </div>
                        </td>

                        <td>
                            {{
                                $assignment
                                    ->starts_on
                                    ->format('Y-m-d')
                            }}
                            —
                            {{
                                $assignment
                                    ->ends_on
                                    ?->format('Y-m-d')
                                ?? 'Open'
                            }}
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $assignment->is_primary
                                        ? 'text-bg-primary'
                                        : 'text-bg-light border'
                                }}"
                            >
                                {{
                                    $assignment->is_primary
                                        ? 'Primary'
                                        : 'Secondary'
                                }}
                            </span>
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

                        <td>
                            {{ $assignment->source_system }}
                        </td>

                        <td class="px-4 text-end">
                            @if (
                                $manualAssignment
                                && $assignment->is_active
                            )
                                <details class="text-start d-inline-block">
                                    <summary
                                        class="btn btn-sm
                                               btn-outline-primary"
                                    >
                                        Edit
                                    </summary>

                                    <div
                                        class="border rounded-3 bg-white
                                               shadow-sm p-3 mt-2"
                                        style="min-width: 340px;"
                                    >
                                        <form
                                            method="POST"
                                            action="{{
                                                route(
                                                    'admin.operator-administration.assignments.update',
                                                    [
                                                        $operator,
                                                        $assignment,
                                                    ]
                                                )
                                            }}"
                                            class="row g-2"
                                        >
                                            @csrf
                                            @method('PUT')

                                            <div class="col-12">
                                                <label
                                                    class="form-label small"
                                                >
                                                    Production line
                                                </label>

                                                <select
                                                    name="production_line_id"
                                                    class="form-select
                                                           form-select-sm"
                                                    required
                                                >
                                                    @foreach (
                                                        $productionLines
                                                        as $line
                                                    )
                                                        <option
                                                            value="{{ $line->id }}"
                                                            @selected(
                                                                $assignment
                                                                    ->production_line_id
                                                                === $line->id
                                                            )
                                                        >
                                                            {{ $line->code }}
                                                            — {{ $line->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-12">
                                                <label
                                                    class="form-label small"
                                                >
                                                    Shift
                                                </label>

                                                <select
                                                    name="shift_id"
                                                    class="form-select
                                                           form-select-sm"
                                                    required
                                                >
                                                    @foreach ($shifts as $shift)
                                                        <option
                                                            value="{{ $shift->id }}"
                                                            @selected(
                                                                $assignment
                                                                    ->shift_id
                                                                === $shift->id
                                                            )
                                                        >
                                                            {{ $shift->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-6">
                                                <label
                                                    class="form-label small"
                                                >
                                                    Starts on
                                                </label>

                                                <input
                                                    type="date"
                                                    name="starts_on"
                                                    value="{{
                                                        $assignment
                                                            ->starts_on
                                                            ->format('Y-m-d')
                                                    }}"
                                                    class="form-control
                                                           form-control-sm"
                                                    required
                                                >
                                            </div>

                                            <div class="col-6">
                                                <label
                                                    class="form-label small"
                                                >
                                                    Ends on
                                                </label>

                                                <input
                                                    type="date"
                                                    name="ends_on"
                                                    value="{{
                                                        $assignment
                                                            ->ends_on
                                                            ?->format('Y-m-d')
                                                    }}"
                                                    class="form-control
                                                           form-control-sm"
                                                >
                                            </div>

                                            <div class="col-12">
                                                <input
                                                    type="hidden"
                                                    name="is_primary"
                                                    value="0"
                                                >

                                                <div class="form-check">
                                                    <input
                                                        type="checkbox"
                                                        name="is_primary"
                                                        value="1"
                                                        class="form-check-input"
                                                        id="primary-{{
                                                            $assignment->id
                                                        }}"
                                                        @checked(
                                                            $assignment
                                                                ->is_primary
                                                        )
                                                    >

                                                    <label
                                                        class="form-check-label"
                                                        for="primary-{{
                                                            $assignment->id
                                                        }}"
                                                    >
                                                        Primary assignment
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <button
                                                    type="submit"
                                                    class="btn btn-sm
                                                           btn-smartfactory"
                                                >
                                                    Save changes
                                                </button>
                                            </div>
                                        </form>

                                        <hr>

                                        <form
                                            method="POST"
                                            action="{{
                                                route(
                                                    'admin.operator-administration.assignments.end',
                                                    [
                                                        $operator,
                                                        $assignment,
                                                    ]
                                                )
                                            }}"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <label
                                                class="form-label small"
                                            >
                                                End assignment on
                                            </label>

                                            <div class="input-group input-group-sm">
                                                <input
                                                    type="date"
                                                    name="ends_on"
                                                    value="{{ $today }}"
                                                    max="{{ $today }}"
                                                    min="{{
                                                        $assignment
                                                            ->starts_on
                                                            ->format('Y-m-d')
                                                    }}"
                                                    class="form-control"
                                                    required
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-danger"
                                                    onclick="return confirm(
                                                        'End and deactivate this assignment?'
                                                    )"
                                                >
                                                    End
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            @elseif (! $manualAssignment)
                                <span class="small text-muted">
                                    ERP read-only
                                </span>
                            @else
                                <span class="small text-muted">
                                    Ended
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            This Operator has no assignment history.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
