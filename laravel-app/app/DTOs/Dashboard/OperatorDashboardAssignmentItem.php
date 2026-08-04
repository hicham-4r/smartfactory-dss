<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class OperatorDashboardAssignmentItem implements
    JsonSerializable
{
    public function __construct(
        public int $id,
        public int $productionLineId,
        public string $productionLineCode,
        public string $productionLineName,
        public int $shiftId,
        public string $shiftCode,
        public string $shiftName,
        public string $startsOn,
        public ?string $endsOn,
        public bool $isPrimary,
    ) {
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_line_id' =>
                $this->productionLineId,
            'production_line_code' =>
                $this->productionLineCode,
            'production_line_name' =>
                $this->productionLineName,
            'shift_id' => $this->shiftId,
            'shift_code' => $this->shiftCode,
            'shift_name' => $this->shiftName,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'is_primary' => $this->isPrimary,
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
