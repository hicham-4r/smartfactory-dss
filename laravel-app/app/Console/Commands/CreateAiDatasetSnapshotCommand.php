<?php

namespace App\Console\Commands;

use App\DTOs\AI\Datasets\DatasetSnapshotRequest;
use App\Enums\AI\DatasetType;
use App\Services\AI\Datasets\DatasetSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class CreateAiDatasetSnapshotCommand extends Command
{
    protected $signature =
        'ai:dataset:snapshot
        {--start= : First local calendar date (YYYY-MM-DD)}
        {--end= : Last local calendar date (YYYY-MM-DD)}
        {--timezone= : IANA timezone}
        {--datasets=all : Comma-separated dataset names or all}
        {--source= : Source system identifier}
        {--json : Output a compact machine-readable receipt}';

    protected $description =
        'Create an atomic, checksummed, simulated-prototype ML dataset snapshot';

    public function handle(
        DatasetSnapshotService $snapshots
    ): int {
        try {
            $request =
                $this->request();

            $result =
                $snapshots->create(
                    $request
                );
        } catch (InvalidArgumentException $exception) {
            $this->components->error(
                $exception->getMessage()
            );

            return self::INVALID;
        } catch (Throwable $exception) {
            Log::error(
                'AI dataset snapshot generation failed safely.',
                [
                    'exception_type' =>
                        $exception::class,
                ]
            );

            $this->components->error(
                'The dataset snapshot could not be generated safely. Review the sanitized application log.'
            );

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            return $this->renderJson(
                $result->toArray()
            );
        }

        $this->components->success(
            'The simulated-prototype dataset snapshot was created.'
        );

        $this->table(
            [
                'Property',
                'Value',
            ],
            [
                [
                    'Snapshot ID',
                    $result->snapshotId,
                ],
                [
                    'Directory',
                    $result->snapshotDirectory,
                ],
                [
                    'Manifest',
                    $result->manifestPath,
                ],
                [
                    'Content fingerprint',
                    $result->contentFingerprint,
                ],
                [
                    'Total rows',
                    (string) $result->totalRows,
                ],
            ]
        );

        $this->table(
            [
                'Dataset',
                'Rows',
                'Bytes',
                'SHA-256',
            ],
            array_map(
                static fn (
                    $file
                ): array => [
                    $file->dataset->value,
                    (string) $file->rowCount,
                    (string) $file->byteSize,
                    $file->sha256,
                ],
                $result->files
            )
        );

        $this->components->info(
            'The snapshot contains sanitized simulated ERP/DSS data only. It is not real company data.'
        );

        return self::SUCCESS;
    }

    private function request(): DatasetSnapshotRequest
    {
        $timezone = trim(
            (string) (
                $this->option(
                    'timezone'
                )
                ?: config(
                    'ai-datasets.default_timezone',
                    'Africa/Casablanca'
                )
            )
        );

        $endInput = trim(
            (string) (
                $this->option('end')
                ?: CarbonImmutable::now(
                    $timezone
                )->toDateString()
            )
        );

        $lookbackDays = max(
            1,
            (int) config(
                'ai-datasets.default_lookback_days',
                180
            )
        );

        $startInput = trim(
            (string) (
                $this->option('start')
                ?: CarbonImmutable::parse(
                    $endInput,
                    $timezone
                )
                    ->subDays(
                        $lookbackDays - 1
                    )
                    ->toDateString()
            )
        );

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $startInput
            ) !== 1
            || preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $endInput
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                '--start and --end must use YYYY-MM-DD.'
            );
        }

        try {
            $startDate =
                CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    $startInput,
                    $timezone
                );

            $endDate =
                CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    $endInput,
                    $timezone
                );
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'The dataset date options are invalid.',
                previous: $exception
            );
        }

        if (
            $startDate === false
            || $endDate === false
            || $startDate->toDateString()
                !== $startInput
            || $endDate->toDateString()
                !== $endInput
        ) {
            throw new InvalidArgumentException(
                'The dataset date options are invalid.'
            );
        }

        $sourceSystem = trim(
            (string) (
                $this->option('source')
                ?: config(
                    'ai-datasets.source_system',
                    'simulated_sage'
                )
            )
        );

        return new DatasetSnapshotRequest(
            startDate:
                $startDate,
            endDate:
                $endDate,
            timezone:
                $timezone,
            datasets:
                DatasetType::parseList(
                    (string) $this->option(
                        'datasets'
                    )
                ),
            sourceSystem:
                $sourceSystem,
            maximumRangeDays:
                max(
                    1,
                    (int) config(
                        'ai-datasets.maximum_range_days',
                        366
                    )
                )
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderJson(
        array $payload
    ): int {
        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            $this->components->error(
                'The snapshot receipt could not be encoded safely.'
            );

            return self::FAILURE;
        }

        $this->line(
            $json
        );

        return self::SUCCESS;
    }
}
