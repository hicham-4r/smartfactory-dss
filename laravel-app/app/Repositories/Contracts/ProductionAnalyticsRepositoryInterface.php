<?php

namespace App\Repositories\Contracts;

use App\DTOs\Analytics\AnalyticsFilter;
use App\Enums\Analytics\ProductionBreakdownDimension;
use Illuminate\Support\Collection;

interface ProductionAnalyticsRepositoryInterface
{
    /**
     * Aggregate final validated production records in the selected period.
     * Kept for backward compatibility with existing callers and tests.
     *
     * @return list<object>
     */
    public function validatedProductionByUnit(
        AnalyticsFilter $filter
    ): array;

    /**
     * Aggregate production records according to the selected order status.
     *
     * Completed views use validated records only. In-progress and the default
     * execution overview may also include pending records, which are reported
     * as provisional. Rejected records are always excluded.
     *
     * @return list<object>
     */
    public function productionByUnit(
        AnalyticsFilter $filter
    ): array;

    /**
     * Aggregate execution targets in the selected period. When a shift is
     * selected, batch planned quantities are used because the simulator assigns
     * shifts at batch/record level rather than reliably at order level.
     *
     * @return list<object>
     */
    public function scheduledTargetsByUnit(
        AnalyticsFilter $filter
    ): array;


    /**
     * Return daily execution metrics, grouped by production date and quantity
     * unit. Values are already aggregated by the database.
     *
     * @return list<object>
     */
    public function dailyProductionMetrics(
        AnalyticsFilter $filter
    ): array;

    /**
     * Return daily scheduled targets. Without a shift filter, production-order
     * targets are assigned to planned-start dates. With a shift filter, each
     * matching batch planned quantity is counted once and assigned to the first
     * eligible record date for that batch.
     *
     * @return list<object>
     */
    public function dailyScheduledTargets(
        AnalyticsFilter $filter
    ): array;

    /**
     * Return actual production grouped by one supported business dimension.
     *
     * @return list<object>
     */
    public function productionBreakdown(
        AnalyticsFilter $filter,
        ProductionBreakdownDimension $dimension
    ): array;

    /**
     * Return scheduled targets grouped by one supported business dimension.
     * Shift targets represent batch exposure and are not additive when one
     * batch spans several shifts.
     *
     * @return list<object>
     */
    public function scheduledTargetBreakdown(
        AnalyticsFilter $filter,
        ProductionBreakdownDimension $dimension
    ): array;

    /**
     * Return a bounded list for the production-order filter.
     *
     * @return Collection<int, object>
     */
    public function filterableProductionOrders(
        AnalyticsFilter $filter,
        int $limit = 250
    ): Collection;

    /**
     * Return canonical, data-backed product-family choices for the selected
     * period. Exact-name duplicates are represented once.
     *
     * @return Collection<int, object>
     */
    public function filterableProductFamilies(
        AnalyticsFilter $filter
    ): Collection;

    /**
     * Return canonical, data-backed product choices for the selected period.
     * Exact-name duplicates are represented once.
     *
     * @return Collection<int, object>
     */
    public function filterableProducts(
        AnalyticsFilter $filter
    ): Collection;

    /**
     * Return canonical, data-backed production-line choices for the selected
     * period. Exact-name duplicates are represented once.
     *
     * @return Collection<int, object>
     */
    public function filterableProductionLines(
        AnalyticsFilter $filter
    ): Collection;

    /**
     * Return canonical, data-backed shifts for the selected period.
     * Case-only duplicates with the same time window are represented once.
     *
     * @return Collection<int, object>
     */
    public function filterableShifts(
        AnalyticsFilter $filter
    ): Collection;

    /**
     * Return period-specific canonical combinations used by the browser to
     * hide impossible choices instead of displaying disabled grey options.
     *
     * @return list<array{
     *     production_line_id:int,
     *     product_family_id:int,
     *     product_id:int,
     *     shift_key:?string,
     *     status:string
     * }>
     */
    public function filterCompatibilityRows(
        AnalyticsFilter $filter,
        int $limit = 5000
    ): array;
}
