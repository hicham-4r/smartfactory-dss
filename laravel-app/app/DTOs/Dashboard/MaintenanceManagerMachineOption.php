<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class MaintenanceManagerMachineOption implements
    JsonSerializable
{
    public function __construct(
        public int $id,
        public string $label,
        public int $productionLineId,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'production_line_id' =>
                $this->productionLineId,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
