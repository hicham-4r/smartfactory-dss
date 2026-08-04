@extends('layouts.app')

@section('title', 'New Production Order')

@section('content')
<div class="container py-4">
    @include(
        'production.supervisor.partials.alerts'
    )

    <div class="d-flex justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">
                New production order
            </h1>

            <p class="text-muted mb-0">
                The order will initially be saved as a draft.
            </p>
        </div>

        <a
            href="{{
                route(
                    'production.supervisor.index'
                )
            }}"
            class="btn btn-outline-secondary"
        >
            Cancel
        </a>
    </div>

    <form
        method="POST"
        action="{{
            route(
                'production.supervisor.orders.store'
            )
        }}"
        class="card shadow-sm"
    >
        @csrf

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label
                        for="product_id"
                        class="form-label"
                    >
                        Product
                    </label>

                    <select
                        id="product_id"
                        name="product_id"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Select a product
                        </option>

                        @foreach ($products as $product)
                            <option
                                value="{{ $product->id }}"
                                @selected(
                                    (string) old('product_id')
                                    === (string) $product->id
                                )
                            >
                                {{
                                    $product->name
                                    ?? $product->code
                                    ?? 'Product #'.$product->id
                                }}

                                @if ($product->code)
                                    — {{ $product->code }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

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
                    >
                        <option value="">
                            Select a production line
                        </option>

                        @foreach ($productionLines as $line)
                            <option
                                value="{{ $line->id }}"
                                @selected(
                                    (string) old(
                                        'production_line_id'
                                    )
                                    === (string) $line->id
                                )
                            >
                                {{
                                    $line->name
                                    ?? $line->code
                                    ?? 'Line #'.$line->id
                                }}
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
                    >
                        <option value="">
                            Any eligible shift
                        </option>

                        @foreach ($shifts as $shift)
                            <option
                                value="{{ $shift->id }}"
                                @selected(
                                    (string) old('shift_id')
                                    === (string) $shift->id
                                )
                            >
                                {{
                                    $shift->name
                                    ?? $shift->code
                                    ?? 'Shift #'.$shift->id
                                }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label
                        for="priority"
                        class="form-label"
                    >
                        Priority
                    </label>

                    <select
                        id="priority"
                        name="priority"
                        class="form-select"
                        required
                    >
                        @for ($priority = 1; $priority <= 5; $priority++)
                            <option
                                value="{{ $priority }}"
                                @selected(
                                    (int) old('priority', 3)
                                    === $priority
                                )
                            >
                                {{ $priority }}
                                @if ($priority === 1)
                                    — Highest
                                @elseif ($priority === 5)
                                    — Lowest
                                @endif
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="col-md-6">
                    <label
                        for="planned_start_at"
                        class="form-label"
                    >
                        Planned start
                    </label>

                    <input
                        id="planned_start_at"
                        name="planned_start_at"
                        type="datetime-local"
                        class="form-control"
                        value="{{ old('planned_start_at') }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="planned_end_at"
                        class="form-label"
                    >
                        Planned end
                    </label>

                    <input
                        id="planned_end_at"
                        name="planned_end_at"
                        type="datetime-local"
                        class="form-control"
                        value="{{ old('planned_end_at') }}"
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="target_quantity"
                        class="form-label"
                    >
                        Target quantity
                    </label>

                    <input
                        id="target_quantity"
                        name="target_quantity"
                        type="number"
                        min="0.001"
                        step="0.001"
                        class="form-control"
                        value="{{ old('target_quantity') }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="quantity_unit"
                        class="form-label"
                    >
                        Quantity unit
                    </label>

                    <input
                        id="quantity_unit"
                        name="quantity_unit"
                        type="text"
                        maxlength="30"
                        class="form-control"
                        value="{{ old('quantity_unit', 'bottles') }}"
                        required
                    >
                </div>

                <div class="col-12">
                    <label
                        for="instructions"
                        class="form-label"
                    >
                        Production instructions
                    </label>

                    <textarea
                        id="instructions"
                        name="instructions"
                        maxlength="5000"
                        rows="5"
                        class="form-control"
                    >{{ old('instructions') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card-footer text-end">
            <button
                type="submit"
                class="btn btn-primary"
            >
                Create draft order
            </button>
        </div>
    </form>
</div>
@endsection