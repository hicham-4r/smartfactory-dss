<?php

namespace App\Console\Commands;

use App\Services\Alerts\DeterministicAlertEvaluationService;
use Illuminate\Console\Command;

final class EvaluateDeterministicAlertsCommand extends Command
{
    protected $signature =
        'alerts:evaluate';

    protected $description =
        'Evaluate deterministic SmartFactory DSS alert conditions once';

    public function handle(
        DeterministicAlertEvaluationService $service
    ): int {
        $this->components->info(
            'Evaluating deterministic alert conditions'
        );

        $report = $service->evaluate();

        $this->table(
            [
                'Metric',
                'Value',
            ],
            [
                [
                    'Conditions evaluated',
                    $report[
                        'conditions_evaluated'
                    ],
                ],
                [
                    'Notifications created',
                    $report[
                        'notifications_created'
                    ],
                ],
                [
                    'Duplicates skipped',
                    $report[
                        'duplicate_notifications_skipped'
                    ],
                ],
                [
                    'Errors',
                    count(
                        $report['errors']
                    ),
                ],
            ]
        );

        if ($report['errors'] !== []) {
            $this->components->warn(
                'Some alert conditions could not be evaluated. Review the application log.'
            );

            return self::FAILURE;
        }

        $this->components->success(
            'Deterministic alert evaluation completed.'
        );

        return self::SUCCESS;
    }
}
