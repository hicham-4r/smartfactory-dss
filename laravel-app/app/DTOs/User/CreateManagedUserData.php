<?php

namespace App\DTOs\User;

use App\Enums\RoleName;

final readonly class CreateManagedUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public RoleName $role
    ) {
    }

    /**
     * Build the DTO from validated request data.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     role: string
     * } $data
     */
    public static function fromValidated(
        array $data
    ): self {
        return new self(
            name: trim($data['name']),
            email: mb_strtolower(trim($data['email'])),
            role: RoleName::from($data['role'])
        );
    }
}