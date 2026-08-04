<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use Carbon\CarbonImmutable;

final readonly class OperatorErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $employeeNumber,
        public string $name,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?CarbonImmutable $hiredOn,
        public ?string $jobTitle,
        public bool $isActive,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'employee_number' =>
                $this->employeeNumber,

            'name' => $this->name,

            'first_name' =>
                $this->firstName,

            'last_name' =>
                $this->lastName,

            'email' => $this->email,
            'phone' => $this->phone,

            'hire_date' =>
                $this->hiredOn
                    ?->toDateString(),

            'job_title' =>
                $this->jobTitle,

            'is_active' =>
                $this->isActive,
        ]);
    }
}
