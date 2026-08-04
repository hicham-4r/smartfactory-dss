<div class="app-card bg-white mb-4" data-step22e-explanation>
    <div class="border-bottom px-4 py-3 d-flex flex-wrap justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Guarded AI explanation</div>
            <div class="small text-muted-smartfactory">
                Generated on demand from the exact verified inference snapshot.
            </div>
        </div>

        <span class="badge {{
            $explanationResult->succeeded()
                ? 'text-bg-success'
                : 'text-bg-warning'
        }}">
            {{ $explanationResult->status->value }}
        </span>
    </div>

    <div class="p-4">
        @if (! $explanationResult->succeeded())
            <div class="alert alert-warning mb-0" role="status">
                {{
                    $explanationResult->message
                        ?? 'The guarded explanation could not be generated.'
                }}

                @if ($explanationResult->retryAfterSeconds !== null)
                    <div class="small mt-2">
                        Retry after approximately
                        {{ $explanationResult->retryAfterSeconds }} seconds.
                    </div>
                @endif

                <div class="small mt-2">
                    The verified numeric inference result above remains valid and unchanged.
                </div>
            </div>
        @else
            @php
                $narrative = $explanationResult->data['narrative'];
            @endphp

            <p class="lead fs-6">
                {{ $narrative['summary'] }}
            </p>

            <div class="row g-4">
                <div class="col-lg-6">
                    <h3 class="h6">Verified observations</h3>
                    <ul class="mb-0">
                        @foreach ($narrative['observations'] as $observation)
                            <li>{{ $observation }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="col-lg-6">
                    <h3 class="h6">Suggested human checks</h3>
                    <ul class="mb-0">
                        @foreach (
                            $narrative['suggested_human_checks']
                            as $check
                        )
                            <li>{{ $check }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="col-12">
                    <h3 class="h6">Limitations</h3>
                    <ul class="mb-0">
                        @foreach ($narrative['limitations'] as $limitation)
                            <li>{{ $limitation }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @if ($explanationIncludedInReport ?? false)
                <div class="alert alert-info mt-4 mb-0" role="status">
                    This explanation is attached to the exact verified report
                    snapshot. PDF, Excel, and CSV exports keep verified facts
                    separate from the guarded AI narrative.
                </div>
            @elseif (isset($reportToken) && is_string($reportToken))
                <div class="alert alert-secondary mt-4 mb-0" role="status">
                    The explanation is visible here, but it could not be attached
                    to the report snapshot. Existing verified report exports remain
                    available without narrative content.
                </div>
            @endif
        @endif

        <div class="small text-muted-smartfactory mt-3">
            Explanation request ID:
            <code>{{ $explanationResult->requestId }}</code>
        </div>
    </div>
</div>
