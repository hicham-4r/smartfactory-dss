@extends('layouts.app')

@section('title', 'User administration')

@section('content')
    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold
                      text-secondary mb-2">
                Secure administration
            </p>

            <h1 class="h3 fw-bold mb-1">
                User accounts
            </h1>

            <p class="text-muted-smartfactory mb-0">
                Create, activate and secure internal DSS accounts.
            </p>
        </div>

        @can(\App\Enums\PermissionName::CreateUsers->value)
            <a
                href="{{ route('admin.users.create') }}"
                class="btn btn-smartfactory"
            >
                Create user
            </a>
        @endcan
    </div>

    @if (session('temporary_password'))
        <div
            class="alert alert-warning border-warning mb-4"
            role="alert"
        >
            <h2 class="h6 fw-bold">
                Temporary password — displayed once
            </h2>

            <p class="mb-2">
                Account:
                <strong>
                    {{ session('temporary_password_email') }}
                </strong>
            </p>

            <div
                class="bg-dark text-white rounded-3
                       p-3 mb-3 font-monospace text-break"
            >
                {{ session('temporary_password') }}
            </div>

            <p class="mb-0 small">
                Communicate this password through an approved private
                channel. Do not place it in screenshots, source files,
                audit logs, reports or Git.
            </p>
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

    <div class="app-card bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="py-3">Role</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Password</th>
                    <th class="py-3">Last login</th>
                    <th class="px-4 py-3 text-end">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($users as $managedUser)
                    @php
                        $roleName =
                            $managedUser->roles->first()?->name;

                        $roleLabel =
                            $roleLabels[$roleName]
                            ?? 'No role';
                    @endphp

                    <tr>
                        <td class="px-4">
                            <div class="fw-semibold">
                                {{ $managedUser->name }}
                            </div>

                            <div class="small text-muted">
                                {{ $managedUser->email }}
                            </div>
                        </td>

                        <td>
                            {{ $roleLabel }}
                        </td>

                        <td>
                            @if ($managedUser->is_active)
                                <span class="badge text-bg-success">
                                    Active
                                </span>
                            @else
                                <span class="badge text-bg-secondary">
                                    Inactive
                                </span>
                            @endif
                        </td>

                        <td>
                            @if ($managedUser->must_change_password)
                                <span class="badge text-bg-warning">
                                    Change required
                                </span>
                            @else
                                <span class="badge text-bg-success">
                                    Confirmed
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $managedUser->last_login_at
                                ?->format('Y-m-d H:i')
                                ?? 'Never' }}
                        </td>

                        <td class="px-4">
                            <div class="d-flex flex-wrap
                                        justify-content-end gap-2">
                                @if ($managedUser->is_active)
                                    @can(
                                        \App\Enums\PermissionName
                                            ::ResetUserPasswords
                                            ->value
                                    )
                                        @if (
                                            $managedUser->id
                                            !== auth()->id()
                                        )
                                            <form
                                                method="POST"
                                                action="{{
                                                    route(
                                                        'admin.users.reset-password',
                                                        $managedUser
                                                    )
                                                }}"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm
                                                           btn-outline-warning"
                                                >
                                                    Reset password
                                                </button>
                                            </form>
                                        @endif
                                    @endcan

                                    @can(
                                        \App\Enums\PermissionName
                                            ::DeactivateUsers
                                            ->value
                                    )
                                        @if (
                                            $managedUser->id
                                            !== auth()->id()
                                        )
                                            <form
                                                method="POST"
                                                action="{{
                                                    route(
                                                        'admin.users.deactivate',
                                                        $managedUser
                                                    )
                                                }}"
                                            >
                                                @csrf
                                                @method('PATCH')

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm
                                                           btn-outline-danger"
                                                >
                                                    Deactivate
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                @else
                                    @can(
                                        \App\Enums\PermissionName
                                            ::ActivateUsers
                                            ->value
                                    )
                                        <form
                                            method="POST"
                                            action="{{
                                                route(
                                                    'admin.users.activate',
                                                    $managedUser
                                                )
                                            }}"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <button
                                                type="submit"
                                                class="btn btn-sm
                                                       btn-outline-success"
                                            >
                                                Activate
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            No user accounts were found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-top p-3">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection