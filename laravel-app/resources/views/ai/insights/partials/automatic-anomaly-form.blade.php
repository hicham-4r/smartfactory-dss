<div class="app-card bg-white h-100">
    <div class="border-bottom px-4 py-3">
        <div class="fw-semibold">Automatic production anomaly check</div>
        <div class="small text-muted-smartfactory">
            Select one validated production record. Laravel calculates all
            ratios and sends one prepared feature row to FastAPI.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('ai-insights.automatic.production.anomaly') }}"
        class="p-4"
        data-sf-loading
        data-sf-loading-text="Preparing record and scoring anomaly..."
    >
        @csrf

        <label
            class="form-label"
            for="automatic_anomaly_record"
        >
            Validated production record
        </label>
        <select
            id="automatic_anomaly_record"
            name="production_record_id"
            class="form-select"
            required
        >
            <option value="">Select a recent record</option>
            @foreach ($automaticOptions['production_records'] as $record)
                <option
                    value="{{ $record['id'] }}"
                    @selected(
                        (string) old('production_record_id')
                            === (string) $record['id']
                    )
                >
                    {{ $record['label'] }}
                </option>
            @endforeach
        </select>

        @if ($automaticOptions['production_records'] === [])
            <div class="alert alert-secondary mt-3 mb-0">
                No validated production record is currently eligible for
                anomaly scoring.
            </div>
        @else
            <button type="submit" class="btn btn-primary mt-4">
                Prepare data and score anomaly
            </button>
        @endif
    </form>
</div>
