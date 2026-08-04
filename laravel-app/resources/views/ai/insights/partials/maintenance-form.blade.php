<div class="app-card bg-white">
    <div class="border-bottom px-4 py-3">
        <div class="fw-semibold">Maintenance risk and prioritization</div>
        <div class="small text-muted-smartfactory">
            Estimates seven-day failure probability and unplanned downtime for one machine.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('ai-insights.maintenance.risk') }}"
        class="p-4"
        data-sf-loading
        data-sf-loading-text="Evaluating maintenance risk..."
    >
        @csrf

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="maintenance_prediction_date">
                    Prediction date
                </label>
                <input
                    id="maintenance_prediction_date"
                    name="prediction_date"
                    type="date"
                    class="form-control"
                    value="{{ old('prediction_date', request('prediction_date')) }}"
                    required
                >
            </div>

            <div class="col-md-8">
                <label class="form-label" for="maintenance_model_run_id">
                    Model run ID <span class="text-muted">(optional)</span>
                </label>
                <input
                    id="maintenance_model_run_id"
                    name="model_run_id"
                    type="text"
                    class="form-control"
                    value="{{ old('model_run_id', request('model_run_id')) }}"
                    placeholder="Uses MODELS_LATEST when blank"
                >
            </div>

            @php
                $maintenanceTextFields = [
                    'production_line_code' => 'Production line code',
                    'machine_code' => 'Machine code',
                    'machine_type' => 'Machine type',
                ];
                $maintenanceIntegerFields = [
                    'days_observed' => 'Days observed',
                    'status_event_count_7d' => 'Status events, 7 days',
                    'fault_status_event_count_7d' => 'Fault-status events, 7 days',
                    'running_minutes_7d' => 'Running minutes, 7 days',
                    'fault_minutes_7d' => 'Fault minutes, 7 days',
                    'downtime_event_count_7d' => 'Downtime events, 7 days',
                    'unplanned_downtime_event_count_7d' => 'Unplanned downtime events, 7 days',
                    'total_downtime_minutes_7d' => 'Total downtime minutes, 7 days',
                    'unplanned_downtime_minutes_7d' => 'Unplanned downtime minutes, 7 days',
                    'maintenance_event_count_30d' => 'Maintenance events, 30 days',
                    'preventive_maintenance_count_30d' => 'Preventive interventions, 30 days',
                    'corrective_maintenance_count_30d' => 'Corrective interventions, 30 days',
                    'maintenance_downtime_minutes_30d' => 'Maintenance downtime minutes, 30 days',
                ];
                $maintenanceOptionalIntegers = [
                    'days_since_last_failure' => 'Days since last failure',
                    'days_since_last_maintenance' => 'Days since last maintenance',
                ];
            @endphp

            @foreach ($maintenanceTextFields as $field => $label)
                <div class="col-md-4">
                    <label class="form-label" for="maintenance_{{ $field }}">
                        {{ $label }}
                    </label>
                    <input
                        id="maintenance_{{ $field }}"
                        name="features[{{ $field }}]"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                        required
                    >
                </div>
            @endforeach

            <div class="col-md-4">
                <label class="form-label" for="maintenance_is_critical">
                    Critical machine
                </label>
                <select
                    id="maintenance_is_critical"
                    name="features[is_critical]"
                    class="form-select"
                    required
                >
                    <option value="">Select</option>
                    <option
                        value="1"
                        @selected((string) old('features.is_critical', request('features.is_critical')) === '1')
                    >
                        Yes
                    </option>
                    <option
                        value="0"
                        @selected((string) old('features.is_critical', request('features.is_critical')) === '0')
                    >
                        No
                    </option>
                </select>
            </div>

            @foreach ($maintenanceIntegerFields as $field => $label)
                <div class="col-md-4">
                    <label class="form-label" for="maintenance_{{ $field }}">
                        {{ $label }}
                    </label>
                    <input
                        id="maintenance_{{ $field }}"
                        name="features[{{ $field }}]"
                        type="number"
                        min="0"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                        required
                    >
                </div>
            @endforeach

            @foreach ($maintenanceOptionalIntegers as $field => $label)
                <div class="col-md-4">
                    <label class="form-label" for="maintenance_{{ $field }}">
                        {{ $label }} <span class="text-muted">(optional)</span>
                    </label>
                    <input
                        id="maintenance_{{ $field }}"
                        name="features[{{ $field }}]"
                        type="number"
                        min="0"
                        class="form-control"
                        value="{{ old('features.'.$field, request('features.'.$field)) }}"
                    >
                </div>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary mt-4">
            Evaluate maintenance risk
        </button>
    </form>
</div>
