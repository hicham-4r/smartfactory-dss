<div class="app-card bg-white h-100">
    <div class="border-bottom px-4 py-3">
        <div class="fw-semibold">Automatic next-day forecast</div>
        <div class="small text-muted-smartfactory">
            Select the line, unit, and next day. Laravel builds the historical
            feature row from validated DSS records.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('ai-insights.automatic.production.forecast') }}"
        class="p-4"
        data-sf-loading
        data-sf-loading-text="Preparing history and running forecast..."
    >
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label
                    class="form-label"
                    for="automatic_forecast_line"
                >
                    Production line
                </label>
                <select
                    id="automatic_forecast_line"
                    name="production_line_code"
                    class="form-select"
                    required
                >
                    <option value="">Select a line</option>
                    @foreach ($automaticOptions['production_lines'] as $line)
                        <option
                            value="{{ $line['code'] }}"
                            @selected(
                                old('production_line_code')
                                    === $line['code']
                            )
                        >
                            {{ $line['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label
                    class="form-label"
                    for="automatic_forecast_unit"
                >
                    Quantity unit
                </label>
                <select
                    id="automatic_forecast_unit"
                    name="quantity_unit"
                    class="form-select"
                    required
                >
                    <option value="">Select a unit</option>
                    @foreach ($automaticOptions['quantity_units'] as $unit)
                        <option
                            value="{{ $unit }}"
                            @selected(old('quantity_unit') === $unit)
                        >
                            {{ $unit }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label
                    class="form-label"
                    for="automatic_forecast_date"
                >
                    Prediction date
                </label>
                <input
                    id="automatic_forecast_date"
                    name="prediction_date"
                    type="date"
                    class="form-control"
                    value="{{ old(
                        'prediction_date',
                        $automaticOptions['default_forecast_date']
                    ) }}"
                    required
                >
            </div>
        </div>

        @if (
            $automaticOptions['production_lines'] === []
            || $automaticOptions['quantity_units'] === []
        )
            <div class="alert alert-secondary mt-3 mb-0">
                No validated production history is currently available for
                automatic forecasting.
            </div>
        @else
            <button type="submit" class="btn btn-primary mt-4">
                Prepare data and forecast
            </button>
        @endif
    </form>
</div>
