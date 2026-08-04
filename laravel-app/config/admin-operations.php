<?php

return [
    'audit_limit' => (int) env(
        'ADMIN_OPERATIONS_AUDIT_LIMIT',
        10
    ),

    'queue_backlog_warning_threshold' => (int) env(
        'ADMIN_OPERATIONS_QUEUE_BACKLOG_WARNING_THRESHOLD',
        50
    ),

    'failed_jobs_warning_threshold' => (int) env(
        'ADMIN_OPERATIONS_FAILED_JOBS_WARNING_THRESHOLD',
        1
    ),
];
