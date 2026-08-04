<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class OperatorDashboardSnapshot implements JsonSerializable
{
    /**
     * @param list<OperatorDashboardAssignmentItem> $assignments
     * @param list<OperatorDashboardOrderItem> $assignedOrders
     * @param list<OperatorDashboardRecordItem> $recentRecords
     * @param list<OperatorDashboardEventItem> $recentEvents
     * @param list<OperatorDashboardUnitSummary> $quantityUnits
     */
    public function __construct(
        public DashboardFilter $filter,
        public bool $profileLinked,
        public bool $operatorActive,
        public ?int $operatorId,
        public ?string $employeeCode,
        public ?string $operatorName,
        public array $assignments,
        public array $assignedOrders,
        public array $recentRecords,
        public array $recentEvents,
        public array $quantityUnits,
        public int $recordCount,
        public int $draftRecordCount,
        public int $submittedRecordCount,
        public int $pendingValidationCount,
        public int $validatedRecordCount,
        public int $rejectedRecordCount,
        public int $runtimeMinutes,
        public int $downtimeMinutes,
        public int $reportedDowntimeCount,
        public int $reportedMachineIncidentCount,
        public int $unresolvedEventCount,
    ) {
    }

    public function hasActiveAssignment(): bool
    {
        return $this->assignments !== [];
    }

    public function needsAttention(): bool
    {
        return ! $this->profileLinked
            || ! $this->operatorActive
            || ! $this->hasActiveAssignment()
            || $this->rejectedRecordCount > 0
            || $this->unresolvedEventCount > 0;
    }

    public function dataBasisLabel(): string
    {
        return 'This dashboard is restricted to the authenticated operator. '
            .'Current assignments and active work use the current local date. '
            .'Record totals, quantities, runtime, downtime and reported incidents use only this operator\'s records within the selected period. '
            .'Quantities with different units remain separated. '
            .'No company-wide production, maintenance, quality, executive, administrator or ERP-monitoring data is exposed.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter->toArray(),
            'profile_linked' => $this->profileLinked,
            'operator_active' => $this->operatorActive,
            'operator_id' => $this->operatorId,
            'employee_code' => $this->employeeCode,
            'operator_name' => $this->operatorName,
            'has_active_assignment' =>
                $this->hasActiveAssignment(),
            'needs_attention' => $this->needsAttention(),
            'record_count' => $this->recordCount,
            'draft_record_count' =>
                $this->draftRecordCount,
            'submitted_record_count' =>
                $this->submittedRecordCount,
            'pending_validation_count' =>
                $this->pendingValidationCount,
            'validated_record_count' =>
                $this->validatedRecordCount,
            'rejected_record_count' =>
                $this->rejectedRecordCount,
            'runtime_minutes' => $this->runtimeMinutes,
            'downtime_minutes' =>
                $this->downtimeMinutes,
            'reported_downtime_count' =>
                $this->reportedDowntimeCount,
            'reported_machine_incident_count' =>
                $this->reportedMachineIncidentCount,
            'unresolved_event_count' =>
                $this->unresolvedEventCount,
            'data_basis' => $this->dataBasisLabel(),
            'assignments' => array_map(
                static fn (
                    OperatorDashboardAssignmentItem $item
                ): array => $item->toArray(),
                $this->assignments
            ),
            'assigned_orders' => array_map(
                static fn (
                    OperatorDashboardOrderItem $item
                ): array => $item->toArray(),
                $this->assignedOrders
            ),
            'recent_records' => array_map(
                static fn (
                    OperatorDashboardRecordItem $item
                ): array => $item->toArray(),
                $this->recentRecords
            ),
            'recent_events' => array_map(
                static fn (
                    OperatorDashboardEventItem $item
                ): array => $item->toArray(),
                $this->recentEvents
            ),
            'quantity_units' => array_map(
                static fn (
                    OperatorDashboardUnitSummary $item
                ): array => $item->toArray(),
                $this->quantityUnits
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
