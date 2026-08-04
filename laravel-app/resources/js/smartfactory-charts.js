const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

const CHART_COLORS = [
    '#164e63',
    '#0f766e',
    '#14b8a6',
    '#2563eb',
    '#7c3aed',
    '#d97706',
    '#dc2626',
    '#64748b',
];

const createSvgElement = (name, attributes = {}) => {
    const element = document.createElementNS(
        SVG_NAMESPACE,
        name,
    );

    Object.entries(attributes).forEach(([key, value]) => {
        element.setAttribute(key, String(value));
    });

    return element;
};

const finiteNumber = (value) => {
    const number = Number(value);

    return Number.isFinite(number)
        ? number
        : 0;
};

const formatValue = (value, config) => {
    const number = finiteNumber(value);
    const decimals = Number.isInteger(number)
        ? 0
        : 2;

    return new Intl.NumberFormat(undefined, {
        maximumFractionDigits: decimals,
    }).format(number)
        + (config.valueSuffix ?? '');
};

const chartHasValues = (config) => (
    Array.isArray(config.datasets)
    && config.datasets.some(
        (dataset) => (
            Array.isArray(dataset.values)
            && dataset.values.some(
                (value) => finiteNumber(value) !== 0,
            )
        ),
    )
);

const appendLegend = (container, config) => {
    if (
        ! Array.isArray(config.datasets)
        || config.datasets.length === 0
    ) {
        return;
    }

    const legend = document.createElement('ul');
    legend.className = 'sf-chart-legend';

    config.datasets.forEach((dataset, index) => {
        const item = document.createElement('li');
        const swatch = document.createElement('span');
        const label = document.createElement('span');

        swatch.className = 'sf-chart-legend__swatch';
        swatch.style.backgroundColor = (
            dataset.color
            ?? CHART_COLORS[index % CHART_COLORS.length]
        );

        label.textContent = dataset.label ?? `Series ${index + 1}`;

        item.append(swatch, label);
        legend.append(item);
    });

    container.append(legend);
};

const appendEmptyState = (container, message) => {
    const empty = document.createElement('div');
    empty.className = 'sf-chart-empty';
    empty.textContent = message;

    container.replaceChildren(empty);
};

const appendAxisLabel = (
    svg,
    text,
    x,
    y,
    options = {},
) => {
    const label = createSvgElement('text', {
        x,
        y,
        'text-anchor': options.anchor ?? 'middle',
        class: options.className ?? 'sf-chart-axis-label',
    });

    label.textContent = text;
    svg.append(label);
};

const renderGroupedBarChart = (
    container,
    config,
    horizontal = false,
) => {
    const labels = Array.isArray(config.labels)
        ? config.labels.map(String)
        : [];

    const datasets = Array.isArray(config.datasets)
        ? config.datasets
        : [];

    if (labels.length === 0 || datasets.length === 0) {
        appendEmptyState(
            container,
            config.emptyMessage ?? 'No chart data is available.',
        );
        return;
    }

    const width = 760;
    const height = Math.max(
        320,
        horizontal ? labels.length * 54 + 100 : 320,
    );
    const margin = horizontal
        ? {
            top: 28,
            right: 34,
            bottom: 46,
            left: 190,
        }
        : {
            top: 28,
            right: 24,
            bottom: 78,
            left: 64,
        };

    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    const values = datasets.flatMap(
        (dataset) => (
            Array.isArray(dataset.values)
                ? dataset.values.map(finiteNumber)
                : []
        ),
    );

    const maximum = Math.max(0, ...values);

    if (maximum <= 0) {
        appendEmptyState(
            container,
            config.emptyMessage
                ?? 'No non-zero values are available for this chart.',
        );
        return;
    }

    const svg = createSvgElement('svg', {
        viewBox: `0 0 ${width} ${height}`,
        class: 'sf-chart-svg',
        role: 'img',
        'aria-label': config.ariaLabel ?? 'Operational chart',
        preserveAspectRatio: 'xMidYMid meet',
    });

    const title = createSvgElement('title');
    title.textContent = config.ariaLabel ?? 'Operational chart';
    svg.append(title);

    const gridSteps = 4;

    for (let step = 0; step <= gridSteps; step += 1) {
        const ratio = step / gridSteps;

        if (horizontal) {
            const x = margin.left + plotWidth * ratio;
            const line = createSvgElement('line', {
                x1: x,
                y1: margin.top,
                x2: x,
                y2: margin.top + plotHeight,
                class: 'sf-chart-grid-line',
            });

            svg.append(line);

            appendAxisLabel(
                svg,
                formatValue(maximum * ratio, config),
                x,
                height - 18,
            );
        } else {
            const y = margin.top + plotHeight - plotHeight * ratio;
            const line = createSvgElement('line', {
                x1: margin.left,
                y1: y,
                x2: margin.left + plotWidth,
                y2: y,
                class: 'sf-chart-grid-line',
            });

            svg.append(line);

            appendAxisLabel(
                svg,
                formatValue(maximum * ratio, config),
                margin.left - 10,
                y + 4,
                {
                    anchor: 'end',
                },
            );
        }
    }

    if (horizontal) {
        const groupHeight = plotHeight / labels.length;
        const innerHeight = groupHeight * 0.72;
        const barHeight = innerHeight / datasets.length;

        labels.forEach((label, labelIndex) => {
            const groupTop = (
                margin.top
                + labelIndex * groupHeight
                + (groupHeight - innerHeight) / 2
            );

            appendAxisLabel(
                svg,
                label,
                margin.left - 12,
                groupTop + innerHeight / 2 + 4,
                {
                    anchor: 'end',
                    className: 'sf-chart-axis-label sf-chart-axis-label--category',
                },
            );

            datasets.forEach((dataset, datasetIndex) => {
                const value = finiteNumber(
                    dataset.values?.[labelIndex],
                );
                const barWidth = (
                    value / maximum
                ) * plotWidth;
                const y = groupTop + datasetIndex * barHeight;

                const rect = createSvgElement('rect', {
                    x: margin.left,
                    y,
                    width: Math.max(0, barWidth),
                    height: Math.max(2, barHeight - 3),
                    rx: 3,
                    class: 'sf-chart-bar',
                    fill: (
                        dataset.color
                        ?? CHART_COLORS[
                            datasetIndex % CHART_COLORS.length
                        ]
                    ),
                });

                const tooltip = createSvgElement('title');
                tooltip.textContent = `${label} — ${
                    dataset.label ?? 'Value'
                }: ${formatValue(value, config)}`;

                rect.append(tooltip);
                svg.append(rect);
            });
        });
    } else {
        const groupWidth = plotWidth / labels.length;
        const innerWidth = groupWidth * 0.78;
        const barWidth = innerWidth / datasets.length;

        labels.forEach((label, labelIndex) => {
            const groupLeft = (
                margin.left
                + labelIndex * groupWidth
                + (groupWidth - innerWidth) / 2
            );

            appendAxisLabel(
                svg,
                label.length > 18
                    ? `${label.slice(0, 16)}…`
                    : label,
                margin.left + labelIndex * groupWidth + groupWidth / 2,
                height - 46,
                {
                    className: 'sf-chart-axis-label sf-chart-axis-label--category',
                },
            );

            datasets.forEach((dataset, datasetIndex) => {
                const value = finiteNumber(
                    dataset.values?.[labelIndex],
                );
                const barHeight = (
                    value / maximum
                ) * plotHeight;

                const rect = createSvgElement('rect', {
                    x: groupLeft + datasetIndex * barWidth,
                    y: margin.top + plotHeight - barHeight,
                    width: Math.max(2, barWidth - 4),
                    height: Math.max(0, barHeight),
                    rx: 3,
                    class: 'sf-chart-bar',
                    fill: (
                        dataset.color
                        ?? CHART_COLORS[
                            datasetIndex % CHART_COLORS.length
                        ]
                    ),
                });

                const tooltip = createSvgElement('title');
                tooltip.textContent = `${label} — ${
                    dataset.label ?? 'Value'
                }: ${formatValue(value, config)}`;

                rect.append(tooltip);
                svg.append(rect);
            });
        });
    }

    container.replaceChildren(svg);
    appendLegend(container, config);
};

const renderLineChart = (container, config) => {
    const labels = Array.isArray(config.labels)
        ? config.labels.map(String)
        : [];

    const datasets = Array.isArray(config.datasets)
        ? config.datasets
        : [];

    if (labels.length === 0 || datasets.length === 0) {
        appendEmptyState(
            container,
            config.emptyMessage ?? 'No chart data is available.',
        );
        return;
    }

    const width = 760;
    const height = 340;
    const margin = {
        top: 28,
        right: 24,
        bottom: 76,
        left: 64,
    };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    const values = datasets.flatMap(
        (dataset) => (
            Array.isArray(dataset.values)
                ? dataset.values.map(finiteNumber)
                : []
        ),
    );
    const maximum = Math.max(0, ...values);

    if (maximum <= 0) {
        appendEmptyState(
            container,
            config.emptyMessage
                ?? 'No non-zero values are available for this chart.',
        );
        return;
    }

    const svg = createSvgElement('svg', {
        viewBox: `0 0 ${width} ${height}`,
        class: 'sf-chart-svg',
        role: 'img',
        'aria-label': config.ariaLabel ?? 'Operational trend chart',
        preserveAspectRatio: 'xMidYMid meet',
    });

    const title = createSvgElement('title');
    title.textContent = config.ariaLabel ?? 'Operational trend chart';
    svg.append(title);

    for (let step = 0; step <= 4; step += 1) {
        const ratio = step / 4;
        const y = margin.top + plotHeight - plotHeight * ratio;

        svg.append(
            createSvgElement('line', {
                x1: margin.left,
                y1: y,
                x2: margin.left + plotWidth,
                y2: y,
                class: 'sf-chart-grid-line',
            }),
        );

        appendAxisLabel(
            svg,
            formatValue(maximum * ratio, config),
            margin.left - 10,
            y + 4,
            {
                anchor: 'end',
            },
        );
    }

    labels.forEach((label, index) => {
        const x = labels.length === 1
            ? margin.left + plotWidth / 2
            : margin.left + (
                index / (labels.length - 1)
            ) * plotWidth;

        appendAxisLabel(
            svg,
            label.length > 16
                ? `${label.slice(0, 14)}…`
                : label,
            x,
            height - 42,
            {
                className: 'sf-chart-axis-label sf-chart-axis-label--category',
            },
        );
    });

    datasets.forEach((dataset, datasetIndex) => {
        const color = (
            dataset.color
            ?? CHART_COLORS[datasetIndex % CHART_COLORS.length]
        );

        const points = labels.map((label, index) => {
            const value = finiteNumber(
                dataset.values?.[index],
            );
            const x = labels.length === 1
                ? margin.left + plotWidth / 2
                : margin.left + (
                    index / (labels.length - 1)
                ) * plotWidth;
            const y = (
                margin.top
                + plotHeight
                - (value / maximum) * plotHeight
            );

            return {
                x,
                y,
                value,
                label,
            };
        });

        const polyline = createSvgElement('polyline', {
            points: points
                .map((point) => `${point.x},${point.y}`)
                .join(' '),
            fill: 'none',
            stroke: color,
            'stroke-width': 3,
            'stroke-linejoin': 'round',
            'stroke-linecap': 'round',
            class: 'sf-chart-line',
        });

        svg.append(polyline);

        points.forEach((point) => {
            const circle = createSvgElement('circle', {
                cx: point.x,
                cy: point.y,
                r: 4.5,
                fill: color,
                class: 'sf-chart-point',
            });

            const tooltip = createSvgElement('title');
            tooltip.textContent = `${point.label} — ${
                dataset.label ?? 'Value'
            }: ${formatValue(point.value, config)}`;

            circle.append(tooltip);
            svg.append(circle);
        });
    });

    container.replaceChildren(svg);
    appendLegend(container, config);
};

const renderDonutChart = (container, config) => {
    const labels = Array.isArray(config.labels)
        ? config.labels.map(String)
        : [];

    const dataset = Array.isArray(config.datasets)
        ? config.datasets[0]
        : null;

    const values = Array.isArray(dataset?.values)
        ? dataset.values.map(
            (value) => Math.max(0, finiteNumber(value)),
        )
        : [];

    const total = values.reduce(
        (sum, value) => sum + value,
        0,
    );

    if (
        labels.length === 0
        || values.length === 0
        || total <= 0
    ) {
        appendEmptyState(
            container,
            config.emptyMessage
                ?? 'No non-zero values are available for this chart.',
        );
        return;
    }

    const width = 520;
    const height = 300;
    const centerX = 210;
    const centerY = 150;
    const radius = 96;
    const circumference = 2 * Math.PI * radius;

    const svg = createSvgElement('svg', {
        viewBox: `0 0 ${width} ${height}`,
        class: 'sf-chart-svg sf-chart-svg--donut',
        role: 'img',
        'aria-label': config.ariaLabel ?? 'Operational composition chart',
        preserveAspectRatio: 'xMidYMid meet',
    });

    const title = createSvgElement('title');
    title.textContent = config.ariaLabel ?? 'Operational composition chart';
    svg.append(title);

    svg.append(
        createSvgElement('circle', {
            cx: centerX,
            cy: centerY,
            r: radius,
            fill: 'none',
            stroke: '#e2e8f0',
            'stroke-width': 34,
        }),
    );

    let offset = 0;

    values.forEach((value, index) => {
        if (value <= 0) {
            return;
        }

        const segmentLength = (
            value / total
        ) * circumference;
        const color = (
            dataset.colors?.[index]
            ?? CHART_COLORS[index % CHART_COLORS.length]
        );

        const circle = createSvgElement('circle', {
            cx: centerX,
            cy: centerY,
            r: radius,
            fill: 'none',
            stroke: color,
            'stroke-width': 34,
            'stroke-dasharray': `${segmentLength} ${
                circumference - segmentLength
            }`,
            'stroke-dashoffset': -offset,
            transform: `rotate(-90 ${centerX} ${centerY})`,
            class: 'sf-chart-donut-segment',
        });

        const tooltip = createSvgElement('title');
        tooltip.textContent = `${labels[index]}: ${
            formatValue(value, config)
        }`;

        circle.append(tooltip);
        svg.append(circle);
        offset += segmentLength;
    });

    const totalText = createSvgElement('text', {
        x: centerX,
        y: centerY - 4,
        'text-anchor': 'middle',
        class: 'sf-chart-donut-total',
    });
    totalText.textContent = formatValue(total, config);

    const totalLabel = createSvgElement('text', {
        x: centerX,
        y: centerY + 22,
        'text-anchor': 'middle',
        class: 'sf-chart-axis-label',
    });
    totalLabel.textContent = config.totalLabel ?? 'Total';

    svg.append(totalText, totalLabel);

    const legendX = 340;
    labels.forEach((label, index) => {
        const y = 68 + index * 38;
        const value = values[index] ?? 0;
        const color = (
            dataset.colors?.[index]
            ?? CHART_COLORS[index % CHART_COLORS.length]
        );

        svg.append(
            createSvgElement('rect', {
                x: legendX,
                y: y - 12,
                width: 14,
                height: 14,
                rx: 3,
                fill: color,
            }),
        );

        const labelText = createSvgElement('text', {
            x: legendX + 24,
            y,
            class: 'sf-chart-donut-label',
        });
        labelText.textContent = label;

        const valueText = createSvgElement('text', {
            x: legendX + 24,
            y: y + 18,
            class: 'sf-chart-axis-label',
        });
        valueText.textContent = formatValue(value, config);

        svg.append(labelText, valueText);
    });

    container.replaceChildren(svg);
};

const renderChart = (container) => {
    const sourceId = container.dataset.sfChartSource;

    if (! sourceId) {
        appendEmptyState(
            container,
            'The chart configuration is missing.',
        );
        return;
    }

    const source = document.getElementById(sourceId);

    if (! source) {
        appendEmptyState(
            container,
            'The chart configuration could not be found.',
        );
        return;
    }

    let config;

    try {
        config = JSON.parse(source.textContent ?? '{}');
    } catch {
        appendEmptyState(
            container,
            'The chart configuration is invalid.',
        );
        return;
    }

    if (! chartHasValues(config)) {
        appendEmptyState(
            container,
            config.emptyMessage
                ?? 'No non-zero values are available for this chart.',
        );
        return;
    }

    switch (config.type) {
        case 'donut':
            renderDonutChart(container, config);
            break;

        case 'line':
            renderLineChart(container, config);
            break;

        case 'horizontal-bar':
            renderGroupedBarChart(
                container,
                config,
                true,
            );
            break;

        case 'bar':
        case 'grouped-bar':
        default:
            renderGroupedBarChart(
                container,
                config,
                false,
            );
            break;
    }
};

const renderSmartFactoryCharts = () => {
    document
        .querySelectorAll('[data-sf-chart]')
        .forEach((container) => {
            if (container.dataset.sfChartRendered === 'true') {
                return;
            }

            renderChart(container);
            container.dataset.sfChartRendered = 'true';
        });
};

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        renderSmartFactoryCharts,
    );
} else {
    renderSmartFactoryCharts();
}

document.addEventListener(
    'turbo:load',
    renderSmartFactoryCharts,
);

export {
    renderSmartFactoryCharts,
};
