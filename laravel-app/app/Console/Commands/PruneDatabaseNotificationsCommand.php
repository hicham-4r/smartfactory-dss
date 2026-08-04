<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneDatabaseNotificationsCommand extends Command
{
    protected $signature =
        'notifications:prune
        {--days= : Retention age in days}
        {--include-unread : Also delete expired unread notifications}';

    protected $description =
        'Delete old SmartFactory DSS database notifications';

    public function handle(
        AuditLogService $audit
    ): int {
        $days = $this->retentionDays();

        if ($days === null) {
            return self::INVALID;
        }

        $query = DB::table(
            'notifications'
        )->where(
            'created_at',
            '<',
            now()->subDays($days)
        );

        if (! $this->option('include-unread')) {
            $query->whereNotNull('read_at');
        }

        $deleted = $query->delete();

        $audit->record(
            action:
                'notifications.retention.pruned',
            metadata: [
                'retention_days' =>
                    $days,
                'include_unread' =>
                    (bool) $this->option(
                        'include-unread'
                    ),
                'deleted_count' =>
                    $deleted,
            ]
        );

        $this->components->success(
            "{$deleted} notification(s) were deleted."
        );

        return self::SUCCESS;
    }

    private function retentionDays(): ?int
    {
        $value =
            $this->option('days');

        if (
            $value === null
            || $value === ''
        ) {
            $value = config(
                'smartfactory-notifications.retention_days',
                90
            );
        }

        $days = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 3650,
                ],
            ]
        );

        if ($days === false) {
            $this->components->error(
                '--days must be an integer between 1 and 3650.'
            );

            return null;
        }

        return (int) $days;
    }
}
