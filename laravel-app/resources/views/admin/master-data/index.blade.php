@extends('layouts.app')

@section('title', 'Production master data')

@section('content')
    <div class="mb-4">
        <p class="text-uppercase small fw-semibold
                  text-secondary mb-2">
            ERP-aligned information
        </p>

        <h1 class="h3 fw-bold mb-2">
            Production master data
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Read-only view of validated DSS records and their
            synchronization traceability.
        </p>
    </div>

    @include('admin.master-data._navigation')

    <div
        class="security-note rounded-3 p-3 mb-4 small"
        role="status"
    >
        These pages are read-only. Changes will later be performed
        through controlled synchronization or explicitly authorized
        administration services.
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <a
                href="{{ route('admin.master-data.products') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <div class="small text-muted mb-2">
                        Product families
                    </div>

                    <div class="display-6 fw-bold">
                        {{ $counts['product_families'] }}
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a
                href="{{ route('admin.master-data.products') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <div class="small text-muted mb-2">
                        Products
                    </div>

                    <div class="display-6 fw-bold">
                        {{ $counts['products'] }}
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a
                href="{{
                    route(
                        'admin.master-data.production-lines'
                    )
                }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <div class="small text-muted mb-2">
                        Production lines
                    </div>

                    <div class="display-6 fw-bold">
                        {{ $counts['production_lines'] }}
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a
                href="{{ route('admin.master-data.machines') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <div class="small text-muted mb-2">
                        Machines
                    </div>

                    <div class="display-6 fw-bold">
                        {{ $counts['machines'] }}
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-4">
            <a
                href="{{ route('admin.master-data.shifts') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <div class="small text-muted mb-2">
                        Shifts
                    </div>

                    <div class="display-6 fw-bold">
                        {{ $counts['shifts'] }}
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-4">
            <a
                href="{{ route('admin.master-data.operators') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <div class="small text-muted mb-2">
                        Operators
                    </div>

                    <div class="display-6 fw-bold">
                        {{ $counts['operators'] }}
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-4">
            <a
                href="{{ route('admin.master-data.assignments') }}"
                class="text-decoration-none text-dark"
            >
                <div class="app-card bg-white h-100 p-4">
                    <div class="small text-muted mb-2">
                        Operator assignments
                    </div>

                    <div class="display-6 fw-bold">
                        {{ $counts['operator_assignments'] }}
                    </div>
                </div>
            </a>
        </div>
    </div>
@endsection