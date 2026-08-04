<div class="app-card bg-white h-100">
    <div class="border-bottom px-4 py-3">
        <div class="fw-semibold">Production anomaly scoring</div>
        <div class="small text-muted-smartfactory">
            Higher scores are more anomalous; the stored model threshold makes the flag.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('ai-insights.production.anomaly') }}"
        class="p-4"
        data-sf-loading
        data-sf-loading-text="Scoring production row..."
    >
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="anomaly_event_time">
                    Event time (UTC)
                </label>
                <input
                    id="anomaly_event_time"
                    name="event_time_utc"
                    type="datetime-local"
                    class="form-control"
                    value="{{ old('event_time_utc', request('event_time_utc')) }}"
                    required
                >
            </div>

            <div class="col-md-6">
                <label class="form-label" for="anomaly_model_run_id">
                    Model run ID <span class="text-muted">(optional)</span>
                </label>
                <input
                    id="anomaly_model_run_id"
                    name="model_run_id"
                    type="text"
                    class="form-control"
                    value="{{ old('model_run_id', request('model_run_id')) }}"
                    placeholder="Uses MODELS_LATEST when blank"
                >
            </div>

            @php
                $anomalyTextFields = [
                    'production_line_code' => 'Production line code',
                    'product_family_code' => 'Product family code',
                    'product_code' => 'Product code',
                    'shift_code' => 'Shift code',
                    'quantity_unit' => 'Quantity unit',
                ];
                $anomalyRequiredNumbers = [
                    'production_order_priority' => ['Order priority', '1'],
                    'target_quantity' => ['Target quantity', 'any'],
                    'produced_quantity' => ['Produced quantity', 'any'],
                    'good_quantity' => ['Good quantity', 'any'],
                    'rejected_quantity' => ['Rejected quantity', 'any'],
                    'runtime_minutes' => ['Runtime minutes', '1'],
                    'downtime_minutes' => ['Downtime minutes', '1'],
                ];
                $anomalyOptionalNumbers = [
                    'achievement_ratio' => 'Achievement ratio',
                    'rejection_ratio' => 'Rejection ratio',
                    'good_yield_ratio' => 'Good-yield ratio',
                    'throughput_per_hour' => 'Throughput per hour',
                    'downtime_ratio' => 'Downtime ratio',
                ];
            @endphp

            @foreach ($anomalyTextFields as $field => $label)
                <div class="col-md-6">
                    <label class="form-label" for="anomaly_{{ $field }}">
                        {{ $label }}
                    </label>
                    <input
                        id="anomaly_{{ $field }}"
                        name="features[{{ $field }}]"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                        required
                    >
                </div>
            @endforeach

            @foreach ($anomalyRequiredNumbers as $field => [$label, $step])
                <div class="col-md-6">
                    <label class="form-label" for="anomaly_{{ $field }}">
                        {{ $label }}
                    </label>
                    <input
                        id="anomaly_{{ $field }}"
                        name="features[{{ $field }}]"
                        type="number"
                        step="{{ $step }}"
                        min="0"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                        required
                    >
                </div>
            @endforeach

            @foreach ($anomalyOptionalNumbers as $field => $label)
                <div class="col-md-6">
                    <label class="form-label" for="anomaly_{{ $field }}">
                        {{ $label }} <span class="text-muted">(optional)</span>
                    </label>
                    <input
                        id="anomaly_{{ $field }}"
                        name="features[{{ $field }}]"
                        type="number"
                        step="any"
                        min="0"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                    >
                </div>
            @endforeach

            <div class="col-md-6">
                <label class="form-label" for="anomaly_is_validated">
                    Production record validation
                </label>
                <select
                    id="anomaly_is_validated"
                    name="features[is_validated]"
                    class="form-select"
                    required
                >
                    <option value="">Select</option>
                    <option
                        value="1"
                        @selected((string) old('features.is_validated', request('features.is_validated')) === '1')
                    >
                        Validated
                    </option>
                    <option
                        value="0"
                        @selected((string) old('features.is_validated', request('features.is_validated')) === '0')
                    >
                        Not validated
                    </option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-4">
            Score production anomaly
        </button>
    </form>
</div>
