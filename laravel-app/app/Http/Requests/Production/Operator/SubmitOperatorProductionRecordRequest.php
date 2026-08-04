<?php

namespace App\Http\Requests\Production\Operator;

use App\Models\ProductionRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SubmitOperatorProductionRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $record = $this->route(
            'productionRecord'
        );

        return $user !== null
            && $record instanceof ProductionRecord
            && Gate::forUser($user)->allows(
                'submit',
                $record
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}