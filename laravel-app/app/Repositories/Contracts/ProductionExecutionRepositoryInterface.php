<?php

namespace App\Repositories\Contracts;

use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use Illuminate\Support\Collection;

interface ProductionExecutionRepositoryInterface
{
    public function findOrder(
        int $orderId
    ): ProductionOrder;

    public function findBatch(
        int $batchId
    ): ProductionBatch;

    public function findRecord(
        int $recordId
    ): ProductionRecord;

    public function findEvent(
        int $eventId
    ): ProductionEvent;

    /**
     * @param array<string, mixed> $changes
     */
    public function updateOrder(
        ProductionOrder $order,
        array $changes,
        int $expectedVersion
    ): ProductionOrder;

    /**
     * @param array<string, mixed> $changes
     */
    public function updateBatch(
        ProductionBatch $batch,
        array $changes,
        int $expectedVersion
    ): ProductionBatch;

    /**
     * @param array<string, mixed> $changes
     */
    public function updateRecord(
        ProductionRecord $record,
        array $changes,
        int $expectedVersion
    ): ProductionRecord;

    /**
     * @param array<string, mixed> $changes
     */
    public function updateEvent(
        ProductionEvent $event,
        array $changes,
        int $expectedVersion
    ): ProductionEvent;

    /**
     * @return Collection<int, ProductionRecord>
     */
    public function pendingRecordsForValidation(
        int $limit = 50
    ): Collection;

    /**
     * @return Collection<int, ProductionEvent>
     */
    public function unresolvedCriticalEvents(
        int $limit = 50
    ): Collection;
}