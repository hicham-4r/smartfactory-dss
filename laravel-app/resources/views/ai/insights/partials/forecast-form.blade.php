<div class="app-card bg-white h-100">
    <div class="border-bottom px-4 py-3">
        <div class="fw-semibold">Next-day production forecast</div>
        <div class="small text-muted-smartfactory">
            Submit one validated feature row. No database query is performed by FastAPI.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('ai-insights.production.forecast') }}"
        class="p-4"
        data-sf-loading
        data-sf-loading-text="Running forecast..."
    >
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="forecast_prediction_date">
                    Prediction date
                </label>
                <input
                    id="forecast_prediction_date"
                    name="prediction_date"
                    type="date"
                    class="form-control"
                    value="{{ old('prediction_date', request('prediction_date')) }}"
                    required
                >
            </div>

            <div class="col-md-6">
                <label class="form-label" for="forecast_model_run_id">
                    Model run ID <span class="text-muted">(optional)</span>
                </label>
                <input
                    id="forecast_model_run_id"
                    name="model_run_id"
                    type="text"
                    class="form-control"
                    value="{{ old('model_run_id', request('model_run_id')) }}"
                    placeholder="Uses MODELS_LATEST when blank"
                >
            </div>

            <div class="col-md-6">
                <label class="form-label" for="forecast_line">
                    Production line code
                </label>
                <input
                    id="forecast_line"
                    name="features[production_line_code]"
                    class="form-control"
                    value="{{ old('features.production_line_code', request('features.production_line_code')) }}"
                    required
                >
            </div>

            <div class="col-md-6">
                <label class="form-label" for="forecast_unit">Quantity unit</label>
                <input
                    id="forecast_unit"
                    name="features[quantity_unit]"
                    class="form-control"
                    value="{{ old('features.quantity_unit', request('features.quantity_unit')) }}"
                    required
                >
            </div>

            @php
                $forecastIntegerFields = [
                    'days_of_history' => ['Days of history', 1, null],
                    'rolling_observation_count_7d' => ['7-day observations', 1, 7],
                    'day_of_week' => ['Day of week (0–6)', 0, 6],
                    'month' => ['Month (1–12)', 1, 12],
                    'runtime_minutes_lag_1d' => ['Previous runtime minutes', 0, null],
                    'downtime_minutes_lag_1d' => ['Previous downtime minutes', 0, null],
                ];
                $forecastNumberFields = [
                    'good_quantity_lag_1d' => 'Good quantity, previous day',
                    'good_quantity_lag_7d' => 'Good quantity, 7-day lag (optional)',
                    'good_quantity_mean_7d' => 'Good quantity, 7-day mean',
                    'good_quantity_min_7d' => 'Good quantity, 7-day minimum',
                    'good_quantity_max_7d' => 'Good quantity, 7-day maximum',
                    'produced_quantity_lag_1d' => 'Produced quantity, previous day',
                    'target_quantity_lag_1d' => 'Target quantity, previous day',
                    'rejection_rate_lag_1d' => 'Previous rejection ratio (optional)',
                    'achievement_rate_lag_1d' => 'Previous achievement ratio (optional)',
                ];
            @endphp

            @foreach ($forecastIntegerFields as $field => [$label, $minimum, $maximum])
                <div class="col-md-6">
                    <label class="form-label" for="forecast_{{ $field }}">
                        {{ $label }}
                    </label>
                    <input
                        id="forecast_{{ $field }}"
                        name="features[{{ $field }}]"
                        type="number"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                        min="{{ $minimum }}"
                        @if ($maximum !== null) max="{{ $maximum }}" @endif
                        required
                    >
                </div>
            @endforeach

            @foreach ($forecastNumberFields as $field => $label)
                @php
                    $optional = in_array(
                        $field,
                        [
                            'good_quantity_lag_7d',
                            'rejection_rate_lag_1d',
                            'achievement_rate_lag_1d',
                        ],
                        true
                    );
                @endphp
                <div class="col-md-6">
                    <label class="form-label" for="forecast_{{ $field }}">
                        {{ $label }}
                    </label>
                    <input
                        id="forecast_{{ $field }}"
                        name="features[{{ $field }}]"
                        type="number"
                        step="any"
                        min="0"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                        @required(! $optional)
                    >
                </div>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary mt-4">
            Run production forecast
        </button>
    </form>
</div>
