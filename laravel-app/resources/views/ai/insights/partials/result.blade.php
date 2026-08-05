@php
    $metadata = $result->succeeded()
        ? ($result->data['metadata'] ?? null)
        : null;
@endphp

<div class="app-card bg-white mb-4">
    <div class="border-bottom px-4 py-3 d-flex flex-wrap justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Latest inference result</div>
            <div class="small text-muted-smartfactory">
                {{ str_replace('_', ' ', (string) $operation) }}
            </div>
        </div>

        <span class="badge {{
            $result->succeeded()
                ? 'text-bg-success'
                : 'text-bg-warning'
        }}">
            {{ $result->status->value }}
        </span>
    </div>

    <div class="p-4">
        @if (! $result->succeeded())
            <div class="alert alert-warning mb-0" role="status">
                {{ $result->message ?? 'The inference could not be completed.' }}
                <div class="small mt-2">
                    Request ID: <code>{{ $result->requestId }}</code>
                </div>
            </div>
        @elseif ($operation === 'production_forecast')
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="small text-muted-smartfactory">
                        Predicted good quantity for the next day
                    </div>
                    <div class="display-6">
                        {{
                            number_format(
                                (float) $result->data[
                                    'predicted_good_quantity_next_day'
                                ],
                                3
                            )
                        }}
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="small text-muted-smartfactory">Prediction date</div>
                    <div class="fw-semibold">
                        {{ $result->data['prediction_date'] }}
                    </div>
                </div>
            </div>
        @elseif ($operation === 'production_anomaly')
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="small text-muted-smartfactory">Anomaly decision</div>
                    <span class="badge {{
                        $result->data['is_anomaly']
                            ? 'text-bg-danger'
                            : 'text-bg-success'
                    }} fs-6">
                        {{
                            $result->data['is_anomaly']
                                ? 'Potential anomaly'
                                : 'Not flagged'
                        }}
                    </span>
                </div>
                <div class="col-sm-4">
                    <div class="small text-muted-smartfactory">Score</div>
                    <div class="h4 mb-0">
                        {{ number_format((float) $result->data['anomaly_score'], 6) }}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="small text-muted-smartfactory">Threshold</div>
                    <div class="h4 mb-0">
                        {{ number_format((float) $result->data['threshold'], 6) }}
                    </div>
                </div>
            </div>
        @elseif ($operation === 'maintenance_risk')
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="small text-muted-smartfactory">
                        Failure probability, next 7 days
                    </div>
                    <div class="display-6">
                        {{
                            number_format(
                                (float) $result->data[
                                    'failure_probability_next_7d'
                                ] * 100,
                                1
                            )
                        }}%
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="small text-muted-smartfactory">
                        Predicted unplanned downtime
                    </div>
                    <div class="display-6">
                        {{
                            number_format(
                                (float) $result->data[
                                    'predicted_unplanned_downtime_minutes_next_7d'
                                ],
                                1
                            )
                        }}
                        <span class="fs-6">min</span>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="small text-muted-smartfactory">Priority</div>
                    <span class="badge {{
                        match ($result->data['priority']) {
                            'critical' => 'text-bg-danger',
                            'high' => 'text-bg-warning',
                            'medium' => 'text-bg-info',
                            default => 'text-bg-success',
                        }
                    }} fs-6">
                        {{ $result->data['priority'] }}
                    </span>
                </div>
            </div>
        @endif

        @if ($metadata !== null)
            <hr>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="small text-muted-smartfactory">Selected model</div>
                    <div class="fw-semibold">{{ $metadata['model_name'] }}</div>
                </div>
                <div class="col-lg-6">
                    <div class="small text-muted-smartfactory">Model run</div>
                    <code class="text-break">{{ $metadata['model_run_id'] }}</code>
                </div>
                <div class="col-12">
                    <div class="small text-muted-smartfactory mb-1">Limitations</div>
                    <ul class="mb-0">
                        @foreach ($metadata['limitations'] as $limitation)
                            <li>{{ $limitation }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif


        @if ($result->succeeded() && isset($reportToken) && is_string($reportToken))
            <hr>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">AI analysis report</div>
                    <div class="small text-muted-smartfactory">
                        Reuses this exact inference result and its checksum-verified model metrics.
                        No second prediction is executed. After a successful guarded explanation,
                        exports include it in clearly separated narrative sections.
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (['pdf' => 'PDF', 'xlsx' => 'Excel', 'csv' => 'CSV'] as $format => $label)
                        <a
                            href="{{ route('reports.ai.export', ['token' => $reportToken, 'format' => $format]) }}"
                            class="btn btn-sm {{
                                $format === 'pdf'
                                    ? 'btn-outline-danger'
                                    : ($format === 'xlsx'
                                        ? 'btn-outline-success'
                                        : 'btn-outline-primary')
                            }}"
                        >
                            Download {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if (
            $result->succeeded()
            && ($canGenerateExplanation ?? false)
            && isset($explanationToken)
            && is_string($explanationToken)
        )
            <hr>

            <div
                class="d-flex flex-column flex-lg-row
                       justify-content-between align-items-lg-end gap-3"
                data-step22e-generate-explanation
            >
                <div>
                    <div class="fw-semibold">
                        Guarded role-aware explanation
                    </div>
                    <div class="small text-muted-smartfactory">
                        Uses this exact verified result. No second inference is
                        executed, and Ollama cannot change any numeric value.
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('ai-insights.explanations.generate') }}"
                    class="d-flex flex-wrap align-items-end gap-2"
                    data-sf-loading
                    data-sf-loading-text="Generating guarded explanation..."
                >
                    @csrf

                    <input
                        type="hidden"
                        name="snapshot_token"
                        value="{{ $explanationToken }}"
                    >

                    <div>
                        <label
                            class="form-label small mb-1"
                            for="explanation_language"
                        >
                            Language
                        </label>
                        <select
                            id="explanation_language"
                            name="language"
                            class="form-select form-select-sm"
                        >
                            <option value="en">English</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        Generate explanation
                    </button>
                </form>
            </div>
        @endif

        <div class="small text-muted-smartfactory mt-3">
            Request ID: <code>{{ $result->requestId }}</code>
        </div>
    </div>
</div>

@if (isset($explanationResult) && $explanationResult !== null)
    @include('ai.insights.partials.explanation', [
        'explanationResult' => $explanationResult,
        'explanationIncludedInReport' =>
            $explanationIncludedInReport ?? false,
        'reportToken' => $reportToken ?? null,
    ])
@endif