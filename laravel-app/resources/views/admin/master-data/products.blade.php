@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-2">
            Products
        </h1>

        <p class="text-muted-smartfactory mb-0">
            Product catalogue grouped by Valencia family.
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
                    placeholder="Code, SKU or name"
                >
            </div>

            <div class="col-lg-3">
                <label
                    for="product_family_id"
                    class="form-label"
                >
                    Product family
                </label>

                <select
                    id="product_family_id"
                    name="product_family_id"
                    class="form-select"
                >
                    <option value="">
                        All families
                    </option>

                    @foreach ($families as $family)
                        <option
                            value="{{ $family->id }}"
                            @selected(
                                (int) $filters[
                                    'product_family_id'
                                ]
                                === $family->id
                            )
                        >
                            {{ $family->name }}
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
                    <option
                        value="all"
                        @selected(
                            $filters['status'] === 'all'
                        )
                    >
                        All
                    </option>

                    <option
                        value="active"
                        @selected(
                            $filters['status'] === 'active'
                        )
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        @selected(
                            $filters['status'] === 'inactive'
                        )
                    >
                        Inactive
                    </option>
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
                    placeholder="simulated_sage"
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
                            'admin.master-data.products'
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
                    <th class="px-4 py-3">Code</th>
                    <th class="py-3">Product</th>
                    <th class="py-3">Family</th>
                    <th class="py-3">Format</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Source</th>
                    <th class="px-4 py-3">Last synchronization</th>
                </tr>
                </thead>

                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 font-monospace">
                            {{ $product->code }}
                        </td>

                        <td>
                            <div class="fw-semibold">
                                {{ $product->name }}
                            </div>

                            <div class="small text-muted">
                                {{ $product->sku ?? 'No SKU' }}
                            </div>
                        </td>

                        <td>
                            {{ $product->productFamily->name }}
                        </td>

                        <td>
                            {{ $product->package_format ?? '—' }}
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $product->is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }}"
                            >
                                {{
                                    $product->is_active
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </span>
                        </td>

                        <td>
                            {{ $product->source_system }}
                        </td>

                        <td class="px-4">
                            {{
                                $product->last_synced_at
                                    ?->format('Y-m-d H:i')
                                ?? 'Never'
                            }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No products match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="border-top p-3">
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection