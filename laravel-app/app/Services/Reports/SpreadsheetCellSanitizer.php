<?php

namespace App\Services\Reports;

final class SpreadsheetCellSanitizer
{
    public function sanitize(
        mixed $value
    ): string|int|float {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $text = $value === null
            ? ''
            : (string) $value;

        if (
            $text !== ''
            && in_array(
                $text[0],
                [
                    '=',
                    '+',
                    '-',
                    '@',
                    "\t",
                    "\r",
                ],
                true
            )
        ) {
            return "'".$text;
        }

        return $text;
    }
}
