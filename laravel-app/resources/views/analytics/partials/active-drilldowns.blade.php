@php
    $links = [];
    $query = array_filter(
        $filter->toArray(),
        static fn (mixed $value): bool =>
            $value !== null
            && $value !== ''
    );

    $labelFor = static function (
        iterable $items,
        int $id,
        string $fallback
    ): string {
        foreach ($items as $item) {
            if ((int) ($item->id ?? 0) === $id) {
                return (string) (
                    $item->name
                    ?? $item->order_number
                    ?? $fallback
                );
            }
        }

        return $fallback;
    };

    if ($domain === 'production') {
        if ($filter->productionLineId !== null) {
            $id = $filter->productionLineId;

            $links[] = [
                'label' => 'Line: '.$labelFor(
                    $productionLines ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.production.lines.show',
                    array_merge(
                        ['productionLine' => $id],
                        $query
                    )
                ),
            ];
        }

        if ($filter->shiftId !== null) {
            $id = $filter->shiftId;

            $links[] = [
                'label' => 'Shift: '.$labelFor(
                    $shifts ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.production.shifts.show',
                    array_merge(
                        ['shift' => $id],
                        $query
                    )
                ),
            ];
        }

        if ($filter->productId !== null) {
            $id = $filter->productId;

            $links[] = [
                'label' => 'Product: '.$labelFor(
                    $products ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.production.products.show',
                    array_merge(
                        ['product' => $id],
                        $query
                    )
                ),
            ];
        }

        if ($filter->productFamilyId !== null) {
            $id = $filter->productFamilyId;

            $links[] = [
                'label' => 'Family: '.$labelFor(
                    $productFamilies ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.production.product-families.show',
                    array_merge(
                        ['productFamily' => $id],
                        $query
                    )
                ),
            ];
        }

        if ($filter->productionOrderId !== null) {
            $id = $filter->productionOrderId;

            $links[] = [
                'label' => 'Order: '.$labelFor(
                    $productionOrders ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.production.orders.show',
                    array_merge(
                        ['productionOrder' => $id],
                        $query
                    )
                ),
            ];
        }
    } elseif ($domain === 'maintenance') {
        if ($filter->machineId !== null) {
            $id = $filter->machineId;

            $links[] = [
                'label' => 'Machine: '.$labelFor(
                    $machines ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.maintenance.machines.show',
                    array_merge(
                        ['machine' => $id],
                        $query
                    )
                ),
            ];
        }
    } elseif ($domain === 'quality') {
        if ($filter->productionLineId !== null) {
            $id = $filter->productionLineId;

            $links[] = [
                'label' => 'Line: '.$labelFor(
                    $productionLines ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.quality.lines.show',
                    array_merge(
                        ['productionLine' => $id],
                        $query
                    )
                ),
            ];
        }

        if ($filter->productId !== null) {
            $id = $filter->productId;

            $links[] = [
                'label' => 'Product: '.$labelFor(
                    $products ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.quality.products.show',
                    array_merge(
                        ['product' => $id],
                        $query
                    )
                ),
            ];
        }

        if ($filter->productFamilyId !== null) {
            $id = $filter->productFamilyId;

            $links[] = [
                'label' => 'Family: '.$labelFor(
                    $productFamilies ?? [],
                    $id,
                    '#'.$id
                ),
                'url' => route(
                    'analytics.quality.product-families.show',
                    array_merge(
                        ['productFamily' => $id],
                        $query
                    )
                ),
            ];
        }
    }
@endphp

@if ($links !== [])
    <section
        class="app-card bg-white p-3 mb-4"
        aria-labelledby="{{ $domain }}-active-drilldowns-title"
    >
        <div class="d-flex flex-wrap align-items-center gap-2">
            <strong
                id="{{ $domain }}-active-drilldowns-title"
                class="me-1"
            >
                Focused drill-downs:
            </strong>

            @foreach ($links as $link)
                <a
                    href="{{ $link['url'] }}"
                    class="btn btn-sm btn-outline-primary"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </section>
@endif
