<?php

namespace App\DTOs\ERP;

use App\Enums\ERP\ErpPersistenceAction;
use App\Enums\ERP\ErpResource;

final readonly class ErpPersistenceResult
{
    public function __construct(
        public ErpResource $resource,
        public ErpPersistenceAction $action,
        public string $table,
        public string $externalId,
        public int|string|null $recordId
    ) {
    }

    public function wasCreated(): bool
    {
        return $this->action
            === ErpPersistenceAction::Created;
    }

    public function wasUpdated(): bool
    {
        return $this->action
            === ErpPersistenceAction::Updated;
    }

    public function wasSkipped(): bool
    {
        return $this->action
            === ErpPersistenceAction::Skipped;
    }
}