<?php

namespace App\Services\AI\Reports;

use App\DTOs\AI\Explanations\AiExplanationResult;
use App\DTOs\AI\Inference\AiInferenceResult;
use App\DTOs\AI\Reports\AiReportExplanation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class AiInferenceReportStore
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $metrics
     */
    public function store(
        Request $request,
        AiInferenceResult $result,
        array $context,
        ?array $metrics,
    ): ?string {
        $user = $request->user();

        if (! $result->succeeded() || ! $user instanceof User) {
            return null;
        }

        $token = Str::uuid()->toString();
        $reports = $this->reports($request);
        $reports[$token] = [
            'token' => $token,
            'user_id' => (int) $user->getKey(),
            'generated_by_name' => (string) $user->name,
            'created_at' => now()->utc()->toIso8601String(),
            'operation' => $result->operation,
            'request_id' => $result->requestId,
            'context' => $this->safeContext($context),
            'result' => $result->data,
            'metrics' => $metrics,
            'explanation' => null,
            'data_classification' => 'simulated_prototype',
        ];

        $this->persistReports($request, $reports);

        return $token;
    }

    public function attachExplanation(
        Request $request,
        string $reportToken,
        AiExplanationResult $result,
        string $inferenceRequestId,
    ): bool {
        if (
            ! $result->succeeded()
            || ! Str::isUuid($reportToken)
            || ($result->data['request_id'] ?? null) !== $result->requestId
        ) {
            return false;
        }

        $snapshot = $this->retrieve($request, $reportToken);
        if (! is_array($snapshot)) {
            return false;
        }

        $storedInferenceRequestId = $snapshot['request_id'] ?? null;
        $operation = $snapshot['operation'] ?? null;

        if (
            ! is_string($storedInferenceRequestId)
            || ! is_string($operation)
            || ! hash_equals(
                $storedInferenceRequestId,
                $inferenceRequestId,
            )
        ) {
            return false;
        }

        try {
            $explanation = AiReportExplanation::fromGeneratedResult(
                payload: $result->data,
                operation: $operation,
                inferenceRequestId: $inferenceRequestId,
            );
        } catch (Throwable) {
            return false;
        }

        $reports = $this->reports($request);
        if (! isset($reports[$reportToken]) || ! is_array($reports[$reportToken])) {
            return false;
        }

        $reports[$reportToken]['explanation'] = $explanation->toArray();
        $this->persistReports($request, $reports);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrieve(Request $request, string $token): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || ! Str::isUuid($token)) {
            return null;
        }

        $reports = $this->reports($request);
        $snapshot = $reports[$token] ?? null;

        if (
            ! is_array($snapshot)
            || (int) ($snapshot['user_id'] ?? 0) !== (int) $user->getKey()
            || ($snapshot['data_classification'] ?? null) !== 'simulated_prototype'
        ) {
            return null;
        }

        $created = strtotime((string) ($snapshot['created_at'] ?? ''));
        $retention = $this->retentionSeconds();

        if ($created === false || $created < (time() - $retention)) {
            unset($reports[$token]);
            $request->session()->put($this->sessionKey(), $reports);

            return null;
        }

        $rawExplanation = $snapshot['explanation'] ?? null;
        if ($rawExplanation !== null) {
            if (
                ! is_array($rawExplanation)
                || ! is_string($snapshot['operation'] ?? null)
                || ! is_string($snapshot['request_id'] ?? null)
            ) {
                return null;
            }

            try {
                AiReportExplanation::fromArray(
                    payload: $rawExplanation,
                    operation: $snapshot['operation'],
                    reportRequestId: $snapshot['request_id'],
                );
            } catch (Throwable) {
                return null;
            }
        }

        return $snapshot;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reports(Request $request): array
    {
        $value = $request->session()->get($this->sessionKey(), []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $reports
     */
    private function persistReports(Request $request, array $reports): void
    {
        $reports = array_filter(
            $reports,
            static fn (mixed $report): bool =>
                is_array($report)
                && is_string($report['created_at'] ?? null),
        );

        uasort(
            $reports,
            static fn (array $left, array $right): int =>
                strcmp((string) $right['created_at'], (string) $left['created_at']),
        );

        $maximum = max(
            1,
            min(
                20,
                (int) config(
                    'ai-model-reports.maximum_reports_per_session',
                    5,
                ),
            ),
        );

        $request->session()->put(
            $this->sessionKey(),
            array_slice($reports, 0, $maximum, true),
        );
    }

    private function sessionKey(): string
    {
        $value = config(
            'ai-model-reports.session_key',
            'smartfactory.ai.inference_reports',
        );

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : 'smartfactory.ai.inference_reports';
    }

    private function retentionSeconds(): int
    {
        return max(
            300,
            min(
                86400,
                (int) config(
                    'ai-model-reports.retention_seconds',
                    3600,
                ),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safeContext(array $context): array
    {
        $allowed = [
            'prediction_date',
            'event_time_utc',
            'production_line_code',
            'quantity_unit',
            'product_family_code',
            'product_code',
            'shift_code',
            'machine_code',
            'machine_type',
            'production_record_id',
        ];

        $safe = [];
        foreach ($allowed as $key) {
            $value = $context[$key] ?? null;
            if (
                is_string($value)
                && strlen($value) <= 200
                && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1
            ) {
                $safe[$key] = $value;
            } elseif (is_int($value)) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
