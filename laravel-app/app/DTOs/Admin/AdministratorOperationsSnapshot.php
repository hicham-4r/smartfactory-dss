<?php

namespace App\DTOs\Admin;

use App\DTOs\ERP\Monitoring\ErpSyncHealthSnapshot;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class AdministratorOperationsSnapshot implements JsonSerializable
{
    /**
     * @param array<string, int> $users
     * @param array<string, int> $operators
     * @param array<string, mixed> $queue
     * @param array<string, array<string, mixed>> $applicationHealth
     * @param list<array<string, string|null>> $auditItems
     * @param list<array{severity:string,title:string,message:string}> $alerts
     */
    public function __construct(
        public CarbonImmutable $generatedAt,
        public array $users,
        public array $operators,
        public ?ErpSyncHealthSnapshot $erpHealth,
        public ?string $erpHealthMessage,
        public array $queue,
        public array $applicationHealth,
        public array $auditItems,
        public array $alerts,
        public string $aiStatus = 'not_implemented',
    ) {
    }

    public function overallStatus(): string
    {
        foreach ($this->alerts as $alert) {
            if (($alert['severity'] ?? null) === 'critical') {
                return 'critical';
            }
        }

        foreach ($this->alerts as $alert) {
            if (($alert['severity'] ?? null) === 'warning') {
                return 'warning';
            }
        }

        return 'healthy';
    }

    public function needsAttention(): bool
    {
        return $this->overallStatus() !== 'healthy';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt
                ->utc()
                ->toIso8601String(),
            'overall_status' => $this->overallStatus(),
            'needs_attention' => $this->needsAttention(),
            'users' => $this->users,
            'operators' => $this->operators,
            'erp_health' => $this->erpHealth?->toArray(),
            'erp_health_message' => $this->erpHealthMessage,
            'queue' => $this->queue,
            'application_health' => $this->applicationHealth,
            'audit_items' => $this->auditItems,
            'alerts' => $this->alerts,
            'ai_status' => $this->aiStatus,
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
