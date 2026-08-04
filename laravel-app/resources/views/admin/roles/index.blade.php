@extends('layouts.app')

@section('title', 'Roles and permissions')

@section('content')
    <div class="mb-4">
        <p class="text-uppercase small fw-semibold
                  text-secondary mb-2">
            Access control
        </p>

        <h1 class="h3 fw-bold mb-2">
            Roles and permissions
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Review the fixed SmartFactory DSS roles and their
            least-privilege permission assignments.
        </p>
    </div>

    <div
        class="security-note rounded-3 p-3 mb-4 small"
        role="status"
    >
        Role names are fixed by the project architecture. The
        Administrator role is protected and cannot be edited.
        Operational roles may only receive permissions from their
        approved domain allowlist.
    </div>

    <div class="row g-4">
        @foreach ($roles as $role)
            @php
                $isAdministrator =
                    $role->name
                    === \App\Enums\RoleName
                        ::Administrator->value;
            @endphp

            <div class="col-xl-6">
                <div class="app-card bg-white h-100 p-4">
                    <div
                        class="d-flex justify-content-between
                               align-items-start gap-3 mb-3"
                    >
                        <div>
                            <h2 class="h5 fw-bold mb-1">
                                {{
                                    $roleLabels[$role->name]
                                    ?? $role->name
                                }}
                            </h2>

                            <div class="small text-muted">
                                {{ $role->name }}
                            </div>
                        </div>

                        @if ($isAdministrator)
                            <span class="badge text-bg-dark">
                                Protected
                            </span>
                        @else
                            <span class="badge text-bg-primary">
                                Operational role
                            </span>
                        @endif
                    </div>

                    <p class="mb-3">
                        <strong>
                            {{ $role->permissions->count() }}
                        </strong>
                        assigned permissions
                    </p>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @foreach (
                            $role->permissions
                                ->sortBy('name')
                                ->take(6)
                            as $permission
                        )
                            <span
                                class="badge rounded-pill
                                       text-bg-light border"
                            >
                                {{ $permission->name }}
                            </span>
                        @endforeach

                        @if ($role->permissions->count() > 6)
                            <span
                                class="badge rounded-pill
                                       text-bg-secondary"
                            >
                                +{{
                                    $role->permissions->count()
                                    - 6
                                }} more
                            </span>
                        @endif
                    </div>

                    @if (! $isAdministrator)
                        @can('update', $role)
                            <a
                                href="{{
                                    route(
                                        'admin.roles.edit',
                                        $role
                                    )
                                }}"
                                class="btn btn-outline-primary"
                            >
                                Review permissions
                            </a>
                        @endcan
                    @else
                        <p class="small text-muted mb-0">
                            The Administrator always retains every
                            registered system permission.
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection