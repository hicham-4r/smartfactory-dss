@if (session('erp_sync_status'))
    <div class="alert alert-success mb-4">
        {{ session('erp_sync_status') }}
    </div>
@endif

@error('manual_sync')
    <div class="alert alert-warning mb-4">
        {{ $message }}
    </div>
@enderror

@can(
    \App\Enums\PermissionName
        ::RunManualSynchronization
        ->value
)
    <div class="app-card bg-white p-4 mb-4">
        <div
            class="d-flex flex-column flex-xl-row
                   justify-content-between gap-4"
        >
            <div>
                <h2 class="h5 fw-bold mb-2">
                    Manual incremental synchronization
                </h2>

                <p class="text-muted-smartfactory mb-0">
                    Queue all ERP dependency groups in safe order.
                    The scheduled and manual processes share one
                    distributed lock, so they cannot overlap.
                </p>
            </div>

            <form
                method="POST"
                action="{{
                    route(
                        'admin.erp-monitoring.synchronize'
                    )
                }}"
                class="row g-3 align-items-end"
            >
                @csrf

                <div class="col-sm-auto">
                    <label
                        for="manual_sync_per_page"
                        class="form-label"
                    >
                        Page size
                    </label>

                    <select
                        id="manual_sync_per_page"
                        name="per_page"
                        class="form-select
                               @error('per_page') is-invalid @enderror"
                    >
                        @foreach ([25, 50, 100, 200] as $pageSize)
                            <option
                                value="{{ $pageSize }}"
                                @selected(
                                    (int) old(
                                        'per_page',
                                        config(
                                            'erp-manual-sync.per_page',
                                            100
                                        )
                                    ) === $pageSize
                                )
                            >
                                {{ $pageSize }}
                            </option>
                        @endforeach
                    </select>

                    @error('per_page')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="col-sm-auto">
                    <label
                        for="manual_sync_max_pages"
                        class="form-label"
                    >
                        Maximum pages
                    </label>

                    <input
                        id="manual_sync_max_pages"
                        name="max_pages"
                        type="number"
                        min="1"
                        max="1000"
                        value="{{
                            old(
                                'max_pages',
                                config(
                                    'erp-manual-sync.max_pages',
                                    100
                                )
                            )
                        }}"
                        class="form-control
                               @error('max_pages') is-invalid @enderror"
                    >

                    @error('max_pages')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="col-sm-auto">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Queue synchronization
                    </button>
                </div>
            </form>
        </div>

        <div class="small text-muted mt-3">
            Password confirmation is required. This interface never
            accepts or displays ERP credentials.
        </div>
    </div>
@endcan
