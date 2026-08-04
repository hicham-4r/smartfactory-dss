<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class DashboardFilterOption implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $label,
        public string $filterValue,
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
            'filter_value' => $this->filterValue,
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
