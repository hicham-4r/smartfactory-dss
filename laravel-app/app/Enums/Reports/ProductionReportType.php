<?php

namespace App\Enums\Reports;

use App\Enums\PermissionName;
use App\Models\User;

enum ProductionReportType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case ProductionLine = 'production-line';
    case Product = 'product';
    case Shift = 'shift';
    case Executive = 'executive';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily production report',
            self::Weekly => 'Weekly production report',
            self::Monthly => 'Monthly production report',
            self::ProductionLine => 'Production by line',
            self::Product => 'Production by product',
            self::Shift => 'Production by shift',
            self::Executive => 'Executive production report',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Daily =>
                'Daily target, actual, good and rejected production.',
            self::Weekly =>
                'Weekly production trend for the selected period.',
            self::Monthly =>
                'Monthly production trend for the selected period.',
            self::ProductionLine =>
                'Target achievement and rejection by production line.',
            self::Product =>
                'Target achievement and rejection by product.',
            self::Shift =>
                'Production performance by shift.',
            self::Executive =>
                'Executive monthly summary with production KPIs.',
        };
    }

    public function requiredPermission(): PermissionName
    {
        return match ($this) {
            self::Daily =>
                PermissionName::GenerateDailyProductionReports,

            self::Weekly,
            self::Monthly =>
                PermissionName::GenerateWeeklyProductionReports,

            self::Executive =>
                PermissionName::GenerateExecutiveProductionReports,

            self::ProductionLine,
            self::Product,
            self::Shift =>
                PermissionName::ViewProductionKpis,
        };
    }

    public function canBeGeneratedBy(User $user): bool
    {
        return $user->can(
            $this->requiredPermission()->value
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
