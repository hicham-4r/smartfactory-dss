<div class="app-card bg-white p-3 mb-4">
    <div class="d-flex flex-wrap gap-2">
        <a
            href="{{ route('admin.master-data.index') }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.master-data.index'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Overview
        </a>

        <a
            href="{{ route('admin.master-data.products') }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.master-data.products'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Products
        </a>

        <a
            href="{{
                route(
                    'admin.master-data.production-lines'
                )
            }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.master-data.production-lines'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Production lines
        </a>

        <a
            href="{{ route('admin.master-data.machines') }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.master-data.machines'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Machines
        </a>

        <a
            href="{{ route('admin.master-data.shifts') }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.master-data.shifts'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Shifts
        </a>

        <a
            href="{{ route('admin.master-data.operators') }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.master-data.operators'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Operators
        </a>

        <a
            href="{{
                route(
                    'admin.operator-administration.index'
                )
            }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.operator-administration.*'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Manage Operators
        </a>

        <a
            href="{{ route('admin.master-data.assignments') }}"
            class="btn btn-sm {{
                request()->routeIs(
                    'admin.master-data.assignments'
                )
                    ? 'btn-smartfactory'
                    : 'btn-outline-secondary'
            }}"
        >
            Assignments
        </a>
    </div>
</div>
