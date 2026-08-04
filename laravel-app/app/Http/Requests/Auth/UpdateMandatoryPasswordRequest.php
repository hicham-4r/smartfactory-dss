<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateMandatoryPasswordRequest extends FormRequest
{
    /**
     * Only authenticated users may submit this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Secure password-change validation.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'max:128',
                'current_password:web',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::default(),
            ],
        ];
    }

    /**
     * Custom messages that do not expose sensitive account details.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' =>
                'The current password is incorrect.',

            'password.different' =>
                'The new password must be different from the temporary password.',
        ];
    }
}