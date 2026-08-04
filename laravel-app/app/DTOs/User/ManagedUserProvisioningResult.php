<?php

namespace App\DTOs\User;

use App\Models\User;

final readonly class ManagedUserProvisioningResult
{
    public function __construct(
        public User $user,
        public string $temporaryPassword
    ) {
    }
}