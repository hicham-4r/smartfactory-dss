@extends('layouts.app')

@section('title', 'Edit role permissions')

@section('content')
    @php
        $submittedPermissions = old(
            'permissions',
            $selectedPermissions
        );
    @endphp

    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="mb-4">
                <a
                    href="{{ route('admin.roles.index') }}"
                    class="text-decoration-none small fw-semibold"
                >
                    ← Return to roles
                </a>

                <h1 class="h3 fw-bold mt-3 mb-2">
                    {{ $roleName->label() }}
                </h1>

                <p class="text-muted-smartfactory mb-0">
                    Select optional permissions within this role’s
                    approved business scope.
                </p>
            </div>

            @if ($errors->any())
                <div
                    class="alert alert-danger"
                    role="alert"
                >
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{
                    route(
                        'admin.roles.permissions.update',
                        $role
                    )
                }}"
            >
                @csrf
                @method('PUT')

                @foreach (
                    $permissionGroups
                    as $group => $permissions
                )
                    <div class="app-card bg-white p-4 mb-4">
                        <h2 class="h5 fw-bold mb-3">
                            {{ $group }}
                        </h2>

                        <div class="row g-3">
                            @foreach (
                                $permissions as $permission
                            )
                                <div class="col-md-6">
                                    <div
                                        class="border rounded-3
                                               p-3 h-100"
                                    >
                                        @if (
                                            $permission['mandatory']
                                        )
                                            <input
                                                type="hidden"
                                                name="permissions[]"
                                                value="{{
                                                    $permission['value']
                                                }}"
                                            >
                                        @endif

                                        <div class="form-check">
                                            <input
                                                type="checkbox"
                                                id="{{
                                                    'permission-'
                                                    .$loop
                                                        ->parent
                                                        ->index
                                                    .'-'
                                                    .$loop->index
                                                }}"
                                                name="permissions[]"
                                                value="{{
                                                    $permission['value']
                                                }}"
                                                class="form-check-input"
                                                @checked(
                                                    $permission['mandatory']
                                                    || in_array(
                                                        $permission['value'],
                                                        $submittedPermissions,
                                                        true
                                                    )
                                                )
                                                @disabled(
                                                    $permission['mandatory']
                                                )
                                            >

                                            <label
                                                class="form-check-label
                                                       fw-semibold"
                                                for="{{
                                                    'permission-'
                                                    .$loop
                                                        ->parent
                                                        ->index
                                                    .'-'
                                                    .$loop->index
                                                }}"
                                            >
                                                {{
                                                    $permission['label']
                                                }}
                                            </label>
                                        </div>

                                        <div
                                            class="small text-muted
                                                   mt-2"
                                        >
                                            {{
                                                $permission['value']
                                            }}
                                        </div>

                                        @if (
                                            $permission['mandatory']
                                        )
                                            <span
                                                class="badge
                                                       text-bg-secondary
                                                       mt-2"
                                            >
                                                Mandatory
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div
                    class="security-note rounded-3
                           p-3 mb-4 small"
                >
                    Permission changes require password confirmation
                    and are recorded in the append-oriented audit log.
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a
                        href="{{ route('admin.roles.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-smartfactory"
                    >
                        Save permission changes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection