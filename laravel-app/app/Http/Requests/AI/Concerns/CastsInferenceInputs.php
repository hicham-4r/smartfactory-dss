<?php

namespace App\Http\Requests\AI\Concerns;

trait CastsInferenceInputs
{
    /**
     * @param  list<string>  $integerFields
     * @param  list<string>  $floatFields
     * @param  list<string>  $nullableFloatFields
     * @param  list<string>  $nullableIntegerFields
     * @param  list<string>  $booleanFields
     */
    protected function castFeatureInputs(
        array $integerFields = [],
        array $floatFields = [],
        array $nullableFloatFields = [],
        array $nullableIntegerFields = [],
        array $booleanFields = [],
    ): void {
        $features = $this->input('features', []);

        if (! is_array($features)) {
            return;
        }

        foreach ($integerFields as $field) {
            if (
                array_key_exists($field, $features)
                && $features[$field] !== ''
            ) {
                $integer = filter_var(
                    $features[$field],
                    FILTER_VALIDATE_INT,
                );

                if ($integer !== false) {
                    $features[$field] = $integer;
                }
            }
        }

        foreach ($floatFields as $field) {
            if (
                array_key_exists($field, $features)
                && $features[$field] !== ''
                && is_numeric($features[$field])
            ) {
                $features[$field] = (float) $features[$field];
            }
        }

        foreach ($nullableFloatFields as $field) {
            if (array_key_exists($field, $features)) {
                if ($features[$field] === '') {
                    $features[$field] = null;
                } elseif (is_numeric($features[$field])) {
                    $features[$field] = (float) $features[$field];
                }
            }
        }

        foreach ($nullableIntegerFields as $field) {
            if (array_key_exists($field, $features)) {
                if ($features[$field] === '') {
                    $features[$field] = null;
                } else {
                    $integer = filter_var(
                        $features[$field],
                        FILTER_VALIDATE_INT,
                    );

                    if ($integer !== false) {
                        $features[$field] = $integer;
                    }
                }
            }
        }

        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $features)) {
                $features[$field] = filter_var(
                    $features[$field],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                );
            }
        }

        $this->merge(['features' => $features]);

        if ($this->input('model_run_id') === '') {
            $this->merge(['model_run_id' => null]);
        }
    }
}
