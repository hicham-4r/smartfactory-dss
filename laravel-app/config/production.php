<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Operator production-record edit window
    |--------------------------------------------------------------------------
    |
    | An operator may update their own draft record only within this
    | period after the record is created.
    |
    */

    'operator_record_edit_window_hours' => (int) env(
        'PRODUCTION_RECORD_EDIT_WINDOW_HOURS',
        48
    ),
];