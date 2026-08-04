<?php

namespace App\Http\Controllers\AI;

use App\Contracts\AI\Explanations\AiExplanationClientInterface;
use App\Contracts\AI\Inference\AiInferenceClientInterface;
use App\DTOs\AI\Explanations\AiExplanationResult;
use App\DTOs\AI\Inference\AiInferenceResult;
use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Exceptions\AI\AiExplanationPreparationException;
use App\Exceptions\AI\InferenceFeaturePreparationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\AutomaticMaintenanceRiskRequest;
use App\Http\Requests\AI\AutomaticProductionAnomalyRequest;
use App\Http\Requests\AI\AutomaticProductionForecastRequest;
use App\Http\Requests\AI\GenerateAiExplanationRequest;
use App\Http\Requests\AI\MaintenanceRiskInferenceRequest;
use App\Http\Requests\AI\ProductionAnomalyInferenceRequest;
use App\Http\Requests\AI\ProductionForecastInferenceRequest;
use App\Models\User;
use App\Services\AI\Explanations\AiExplanationPayloadFactory;
use App\Services\AI\Explanations\AiExplanationSnapshotStore;
use App\Services\AI\Inference\AiModelMetricsClient;
use App\Services\AI\Inference\AutomaticInferenceFeatureService;
use App\Services\AI\Reports\AiInferenceReportStore;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AiInsightsController extends Controller
{
    public function __construct(
        private readonly AiInferenceClientInterface $client,
        private readonly AiExplanationClientInterface $explanationClient,
        private readonly AutomaticInferenceFeatureService $automaticFeatures,
        private readonly AiInferenceReportStore $reportStore,
        private readonly AiExplanationPayloadFactory $explanationPayloads,
        private readonly AiExplanationSnapshotStore $explanationSnapshots,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function index(Request $request): Response
    {
        $this->assertOverviewAccess($request);

        return $this->render($request);
    }

    public function generateExplanation(
        GenerateAiExplanationRequest $request,
    ): Response {
        $validated = $request->validated();
        $snapshot = $this->explanationSnapshots->retrieve(
            request: $request,
            token: $validated['snapshot_token'],
        );

        if ($snapshot === null) {
            throw ValidationException::withMessages([
                'snapshot_token' =>
                    'The explanation snapshot is invalid, expired, or belongs to another session.',
            ]);
        }

        $this->assertExplanationAccess(
            request: $request,
            operation: $snapshot->operation,
        );

        $requestId = $this->requestId('explanation');
        $payload = null;

        try {
            $payload = $this->explanationPayloads->make(
                user: $request->user(),
                snapshot: $snapshot,
                language: $validated['language'],
            );

            $explanationResult = $this->explanationClient->generate(
                payload: $payload,
                requestId: $requestId,
            );
        } catch (AiExplanationPreparationException) {
            $explanationResult = AiExplanationResult::rejected(
                requestId: $requestId,
                message:
                    'The verified inference snapshot could not be prepared safely for explanation.',
            );
        }

        $explanationIncludedInReport = false;

        if (
            $explanationResult->succeeded()
            && is_string($snapshot->reportToken)
        ) {
            $explanationIncludedInReport = $this->reportStore
                ->attachExplanation(
                    request: $request,
                    reportToken: $snapshot->reportToken,
                    result: $explanationResult,
                    inferenceRequestId: $snapshot->inferenceRequestId,
                );

            if (! $explanationIncludedInReport) {
                Log::warning(
                    'AI explanation could not be attached to its verified report snapshot.',
                    [
                        'inference_request_id' => $snapshot->inferenceRequestId,
                        'explanation_request_id' => $explanationResult->requestId,
                        'operation' => $snapshot->operation,
                    ],
                );
            }
        }

        $this->recordExplanationAudit(
            request: $request,
            result: $explanationResult,
            operation: $snapshot->operation,
            inferenceRequestId: $snapshot->inferenceRequestId,
            explanationId: is_array($payload)
                && is_string($payload['explanation_id'] ?? null)
                    ? $payload['explanation_id']
                    : null,
            language: $validated['language'],
            includedInReport: $explanationIncludedInReport,
        );

        return $this->render(
            request: $request,
            activeOperation: $snapshot->operation,
            inferenceResult: AiInferenceResult::success(
                operation: $snapshot->operation,
                requestId: $snapshot->inferenceRequestId,
                data: $snapshot->inferenceData,
            ),
            reportToken: $snapshot->reportToken,
            explanationToken: $snapshot->token,
            explanationResult: $explanationResult,
            explanationIncludedInReport: $explanationIncludedInReport,
        );
    }

    public function automaticForecast(
        AutomaticProductionForecastRequest $request,
    ): Response {
        $this->assertProductionAccess($request);
        $validated = $request->validated();

        try {
            $payload = $this->automaticFeatures->forecastPayload(
                productionLineCode: $validated['production_line_code'],
                quantityUnit: $validated['quantity_unit'],
                predictionDate: $validated['prediction_date'],
                modelRunId: $validated['model_run_id'] ?? null,
            );
        } catch (
            InferenceFeaturePreparationException $exception
        ) {
            throw ValidationException::withMessages([
                'automatic_forecast' => $exception->getMessage(),
            ]);
        }

        $result = $this->client->forecast(
            payload: $payload,
            requestId: $this->requestId(
                'automatic-forecast'
            ),
        );

        return $this->render(
            request: $request,
            activeOperation: 'production_forecast',
            inferenceResult: $result,
            inferencePayload: $payload,
            reportToken: $this->captureReport(
                request: $request,
                result: $result,
                payload: $payload,
            ),
        );
    }

    public function automaticAnomaly(
        AutomaticProductionAnomalyRequest $request,
    ): Response {
        $this->assertProductionAccess($request);
        $validated = $request->validated();

        try {
            $payload = $this->automaticFeatures->anomalyPayload(
                productionRecordId: (int) $validated[
                        'production_record_id'
                    ],
                modelRunId: $validated['model_run_id'] ?? null,
            );
        } catch (
            InferenceFeaturePreparationException $exception
        ) {
            throw ValidationException::withMessages([
                'automatic_anomaly' => $exception->getMessage(),
            ]);
        }

        $result = $this->client->anomaly(
            payload: $payload,
            requestId: $this->requestId(
                'automatic-anomaly'
            ),
        );

        return $this->render(
            request: $request,
            activeOperation: 'production_anomaly',
            inferenceResult: $result,
            inferencePayload: $payload,
            reportToken: $this->captureReport(
                request: $request,
                result: $result,
                payload: $payload,
            ),
        );
    }

    public function automaticMaintenanceRisk(
        AutomaticMaintenanceRiskRequest $request,
    ): Response {
        $this->assertMaintenanceAccess($request);
        $validated = $request->validated();

        try {
            $payload =
                $this->automaticFeatures
                    ->maintenancePayload(
                        machineId: (int) $validated[
                                'machine_id'
                            ],
                        predictionDate: $validated[
                                'prediction_date'
                            ],
                        modelRunId: $validated[
                                'model_run_id'
                            ] ?? null,
                    );
        } catch (
            InferenceFeaturePreparationException $exception
        ) {
            throw ValidationException::withMessages([
                'automatic_maintenance' => $exception->getMessage(),
            ]);
        }

        $result = $this->client->maintenanceRisk(
            payload: $payload,
            requestId: $this->requestId(
                'automatic-maintenance'
            ),
        );

        return $this->render(
            request: $request,
            activeOperation: 'maintenance_risk',
            inferenceResult: $result,
            inferencePayload: $payload,
            reportToken: $this->captureReport(
                request: $request,
                result: $result,
                payload: $payload,
            ),
        );
    }

    public function forecast(
        ProductionForecastInferenceRequest $request,
    ): Response {
        $this->assertProductionAccess($request);

        $payload = $request->inferencePayload();
        $result = $this->client->forecast(
            payload: $payload,
            requestId: $this->requestId('forecast'),
        );

        return $this->render(
            request: $request,
            activeOperation: 'production_forecast',
            inferenceResult: $result,
            inferencePayload: $payload,
            reportToken: $this->captureReport(
                request: $request,
                result: $result,
                payload: $payload,
            ),
        );
    }

    public function anomaly(
        ProductionAnomalyInferenceRequest $request,
    ): Response {
        $this->assertProductionAccess($request);

        $payload = $request->inferencePayload();
        $result = $this->client->anomaly(
            payload: $payload,
            requestId: $this->requestId('anomaly'),
        );

        return $this->render(
            request: $request,
            activeOperation: 'production_anomaly',
            inferenceResult: $result,
            inferencePayload: $payload,
            reportToken: $this->captureReport(
                request: $request,
                result: $result,
                payload: $payload,
            ),
        );
    }

    public function maintenanceRisk(
        MaintenanceRiskInferenceRequest $request,
    ): Response {
        $this->assertMaintenanceAccess($request);

        $payload = $request->inferencePayload();
        $result = $this->client->maintenanceRisk(
            payload: $payload,
            requestId: $this->requestId('maintenance'),
        );

        return $this->render(
            request: $request,
            activeOperation: 'maintenance_risk',
            inferenceResult: $result,
            inferencePayload: $payload,
            reportToken: $this->captureReport(
                request: $request,
                result: $result,
                payload: $payload,
            ),
        );
    }

    /**
     * @param  array<string, mixed>|null  $inferencePayload
     */
    private function render(
        Request $request,
        ?string $activeOperation = null,
        ?AiInferenceResult $inferenceResult = null,
        ?array $inferencePayload = null,
        ?string $reportToken = null,
        ?string $explanationToken = null,
        ?AiExplanationResult $explanationResult = null,
        bool $explanationIncludedInReport = false,
    ): Response {
        if (
            $explanationToken === null
            && $inferenceResult?->succeeded()
            && $inferencePayload !== null
        ) {
            $explanationToken = $this->explanationSnapshots->store(
                request: $request,
                result: $inferenceResult,
                inferencePayload: $inferencePayload,
                reportToken: $reportToken,
            );
        }

        $registry = $this->client->models(
            $this->requestId('models'),
        );

        return response()
            ->view('ai.insights.index', [
                'registry' => $registry,
                'activeOperation' => $activeOperation,
                'inferenceResult' => $inferenceResult,
                'reportToken' => $reportToken,
                'explanationToken' => $explanationToken,
                'explanationResult' => $explanationResult,
                'explanationIncludedInReport' =>
                    $explanationIncludedInReport,
                'canGenerateExplanation' =>
                    $inferenceResult?->succeeded() === true
                    && $this->canGenerateExplanation(
                        request: $request,
                        operation: $activeOperation,
                    ),
                'canUseProductionModels' => $this->canUseProductionModels(
                    $request,
                ),
                'canUseMaintenanceModels' => $this->canUseMaintenanceModels(
                    $request,
                ),
                'automaticOptions' => $this->automaticFeatures
                    ->options(),
            ])
            ->header(
                'Cache-Control',
                'private, no-store, no-cache, must-revalidate',
            )
            ->header('Pragma', 'no-cache');
    }

    private function assertOverviewAccess(Request $request): void
    {
        abort_unless(
            $this->canUseProductionModels($request)
            || $this->canUseMaintenanceModels($request),
            403,
        );
    }

    private function assertProductionAccess(Request $request): void
    {
        abort_unless($this->canUseProductionModels($request), 403);
    }

    private function assertMaintenanceAccess(Request $request): void
    {
        abort_unless($this->canUseMaintenanceModels($request), 403);
    }

    private function assertExplanationAccess(
        Request $request,
        string $operation,
    ): void {
        abort_unless(
            $this->canGenerateExplanation(
                request: $request,
                operation: $operation,
            ),
            403,
        );
    }

    private function canGenerateExplanation(
        Request $request,
        ?string $operation,
    ): bool {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        return match ($operation) {
            'production_forecast',
            'production_anomaly' => $user->can(
                PermissionName::ViewProductionAiExplanations->value,
            ),
            'maintenance_risk' => $user->can(
                PermissionName::ViewMaintenanceAiRecommendations->value,
            ),
            default => false,
        };
    }

    private function canUseProductionModels(Request $request): bool
    {
        return $this->userCanAny($request, [
            PermissionName::ViewAdministratorDashboard->value,
            PermissionName::ViewProductionManagerDashboard->value,
            PermissionName::ViewProductionSupervisorDashboard->value,
        ]);
    }

    private function canUseMaintenanceModels(Request $request): bool
    {
        return $this->userCanAny($request, [
            PermissionName::ViewAdministratorDashboard->value,
            PermissionName::ViewMaintenanceManagerDashboard->value,
        ]);
    }

    /**
     * @param  list<string>  $abilities
     */
    private function userCanAny(
        Request $request,
        array $abilities,
    ): bool {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        foreach ($abilities as $ability) {
            if ($user->can($ability)) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param array<string, mixed> $payload
     */
    private function captureReport(
        Request $request,
        AiInferenceResult $result,
        array $payload,
    ): ?string {
        if (! $result->succeeded()) {
            return null;
        }

        $metadata = $result->data['metadata'] ?? null;
        if (! is_array($metadata)) {
            return null;
        }

        $modelRunId = $metadata['model_run_id'] ?? null;
        if (! is_string($modelRunId)) {
            return null;
        }

        $task = match ($result->operation) {
            'production_forecast' => 'production_forecasting',
            'production_anomaly' => 'production_anomaly',
            'maintenance_risk' => 'maintenance_risk',
            default => null,
        };

        if ($task === null) {
            return null;
        }

        $metrics = null;
        if (! app()->environment('testing')) {
            try {
                $metrics = app(AiModelMetricsClient::class)->fetch(
                    modelRunId: $modelRunId,
                    task: $task,
                    requestId: $this->requestId('metrics'),
                );
            } catch (Throwable) {
                $metrics = null;
            }
        }

        return $this->reportStore->store(
            request: $request,
            result: $result,
            context: $this->reportContext($payload),
            metrics: $metrics,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function reportContext(array $payload): array
    {
        $features = is_array($payload['features'] ?? null)
            ? $payload['features']
            : [];

        return array_filter(
            [
                'prediction_date' => $payload['prediction_date'] ?? null,
                'event_time_utc' => $payload['event_time_utc'] ?? null,
                'production_record_id' => $payload['production_record_id'] ?? null,
                'production_line_code' => $features['production_line_code'] ?? null,
                'quantity_unit' => $features['quantity_unit'] ?? null,
                'product_family_code' => $features['product_family_code'] ?? null,
                'product_code' => $features['product_code'] ?? null,
                'shift_code' => $features['shift_code'] ?? null,
                'machine_code' => $features['machine_code'] ?? null,
                'machine_type' => $features['machine_type'] ?? null,
            ],
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    private function recordExplanationAudit(
        Request $request,
        AiExplanationResult $result,
        string $operation,
        string $inferenceRequestId,
        ?string $explanationId,
        string $language,
        bool $includedInReport,
    ): void {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        try {
            $this->auditLogs->record(
                action: $result->succeeded()
                    ? AuditAction::AiExplanationGenerated
                    : AuditAction::AiExplanationFailed,
                actor: $user,
                auditable: $user,
                metadata: [
                    'operation' => $operation,
                    'status' => $result->status->value,
                    'language' => $language,
                    'inference_request_id' => $inferenceRequestId,
                    'explanation_request_id' => $result->requestId,
                    'explanation_id' => $explanationId,
                    'http_status' => $result->httpStatus,
                    'included_in_report' => $includedInReport,
                ],
                request: $request,
            );
        } catch (Throwable $exception) {
            Log::warning(
                'AI explanation audit recording failed safely.',
                [
                    'request_id' => $result->requestId,
                    'operation' => $operation,
                    'exception_type' => $exception::class,
                ],
            );
        }
    }

    private function requestId(string $operation): string
    {
        return sprintf(
            'laravel-ai-%s-%s',
            $operation,
            Str::uuid()->toString(),
        );
    }
}
