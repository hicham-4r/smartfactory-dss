<?php

namespace App\Services\AI\Inference;

use App\Exceptions\AI\InferenceFeaturePreparationException;
use Carbon\CarbonImmutable;

class AutomaticInferenceFeatureService
{
    public function __construct(
        private readonly EloquentAutomaticInferenceSourceRepository $repository,
        private readonly InferenceFeatureCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->repository->options();
    }

    /**
     * @return array<string, mixed>
     */
    public function forecastPayload(
        string $productionLineCode,
        string $quantityUnit,
        string $predictionDate,
        ?string $modelRunId = null,
    ): array {
        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $predictionDate,
            'UTC'
        );

        if ($date === false) {
            throw InferenceFeaturePreparationException::invalidSelection(
                'The forecast prediction date is invalid.'
            );
        }

        $payload = $this->calculator->forecast(
            rows: $this->repository->forecastRows(
                productionLineCode: $productionLineCode,
                quantityUnit: $quantityUnit,
                predictionDate: $date,
            ),
            predictionDate: $date,
            productionLineCode: $productionLineCode,
            quantityUnit: $quantityUnit,
        );

        $payload['model_run_id'] = $modelRunId;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function anomalyPayload(
        int $productionRecordId,
        ?string $modelRunId = null,
    ): array {
        $row = $this->repository->productionRecord(
            $productionRecordId
        );

        if ($row === null) {
            throw InferenceFeaturePreparationException::invalidSelection(
                'The selected validated production record is unavailable.'
            );
        }

        $payload = $this->calculator->anomaly($row);
        $payload['model_run_id'] = $modelRunId;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function maintenancePayload(
        int $machineId,
        string $predictionDate,
        ?string $modelRunId = null,
    ): array {
        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $predictionDate,
            'UTC'
        );

        if ($date === false) {
            throw InferenceFeaturePreparationException::invalidSelection(
                'The maintenance prediction date is invalid.'
            );
        }

        $context = $this->repository->machineContext(
            machineId: $machineId,
            predictionDate: $date,
        );

        if ($context === null) {
            throw InferenceFeaturePreparationException::invalidSelection(
                'The selected active machine is unavailable.'
            );
        }

        $payload = $this->calculator->maintenance(
            context: $context,
            predictionDate: $date,
        );

        $payload['model_run_id'] = $modelRunId;

        return $payload;
    }
}
