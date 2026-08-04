<?php

namespace App\Repositories\Contracts;

use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\Enums\Analytics\QualityBreakdownDimension;
use Illuminate\Support\Collection;

interface QualityAnalyticsRepositoryInterface
{
    public function inspectionTotals(
        QualityAnalyticsFilter $filter
    ): object;

    public function lotTotals(
        QualityAnalyticsFilter $filter
    ): object;

    public function nonconformityTotals(
        QualityAnalyticsFilter $filter
    ): object;

    /** @return list<object> */
    public function lotQuantitiesByUnit(
        QualityAnalyticsFilter $filter
    ): array;

    /** @return list<object> */
    public function inspectionsByDimension(
        QualityAnalyticsFilter $filter,
        QualityBreakdownDimension $dimension,
    ): array;

    /** @return list<object> */
    public function lotsByDimension(
        QualityAnalyticsFilter $filter,
        QualityBreakdownDimension $dimension,
    ): array;

    /** @return list<object> */
    public function nonconformitiesByDimension(
        QualityAnalyticsFilter $filter,
        QualityBreakdownDimension $dimension,
    ): array;

    /** @return list<object> */
    public function nonconformityCategories(
        QualityAnalyticsFilter $filter
    ): array;

    /** @return Collection<int, object> */
    public function filterableProductionLines(
        QualityAnalyticsFilter $filter
    ): Collection;

    /** @return Collection<int, object> */
    public function filterableProductFamilies(
        QualityAnalyticsFilter $filter
    ): Collection;

    /** @return Collection<int, object> */
    public function filterableProducts(
        QualityAnalyticsFilter $filter
    ): Collection;

    /**
     * @return list<array{
     *     production_line_id:int,
     *     product_family_id:int,
     *     product_id:int
     * }>
     */
    public function filterCompatibilityRows(
        QualityAnalyticsFilter $filter
    ): array;
}
