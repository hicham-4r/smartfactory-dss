<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

final class DeactivateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::DeactivateUsers->value
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}