<div class="app-card bg-white">
    <div class="border-bottom px-4 py-3">
        <div class="fw-semibold">Automatic maintenance-risk analysis</div>
        <div class="small text-muted-smartfactory">
            Select a machine and prediction date. Laravel calculates the
            seven-day status/downtime history and thirty-day maintenance history.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('ai-insights.automatic.maintenance.risk') }}"
        class="p-4"
        data-sf-loading
        data-sf-loading-text="Preparing machine history and evaluating risk..."
    >
        @csrf

        <div class="row g-3">
            <div class="col-md-8">
                <label
                    class="form-label"
                    for="automatic_maintenance_machine"
                >
                    Machine
                </label>
                <select
                    id="automatic_maintenance_machine"
                    name="machine_id"
                    class="form-select"
                    required
                >
                    <option value="">Select a machine</option>
                    @foreach ($automaticOptions['machines'] as $machine)
                        <option
                            value="{{ $machine['id'] }}"
                            @selected(
                                (string) old('machine_id')
                                    === (string) $machine['id']
                            )
                        >
                            {{ $machine['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label
                    class="form-label"
                    for="automatic_maintenance_date"
                >
                    Prediction date
                </label>
                <input
                    id="automatic_maintenance_date"
                    name="prediction_date"
                    type="date"
                    class="form-control"
                    value="{{ old(
                        'prediction_date',
                        $automaticOptions['default_maintenance_date']
                    ) }}"
                    required
                >
            </div>
        </div>

        @if ($automaticOptions['machines'] === [])
            <div class="alert alert-secondary mt-3 mb-0">
                No machine currently has enough eligible history for automatic risk analysis.
            </div>
        @else
            <button type="submit" class="btn btn-primary mt-4">
                Prepare history and evaluate risk
            </button>
        @endif
    </form>
</div>
