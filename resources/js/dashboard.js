import axios from 'axios';

axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

const REVENUE_PREDICTION_RANGES = {
    '7d': { label: 'Próximos 7 días', limit: 7 },
    '15d': { label: 'Próximos 15 días', limit: 15 },
    '30d': { label: 'Próximos 30 días', limit: 30 },
    all: { label: 'Todo el horizonte', limit: null },
};

const REVENUE_EVALUATION_RANGES = {
    '7d': { label: 'Últimos 7 días', limit: 7 },
    '15d': { label: 'Últimos 15 días', limit: 15 },
    '30d': { label: 'Últimos 30 días', limit: 30 },
    '60d': { label: 'Últimos 60 días', limit: 60 },
    '90d': { label: 'Últimos 90 días', limit: 90 },
    all: { label: 'Todo el histórico', limit: null },
};

let revenuePredictionData = null;
let revenuePredictionChart = null;
let activeRevenuePredictionRange = '30d';
let revenuePredictionRangeBound = false;

let revenueEvaluationData = null;
let revenueEvaluationChart = null;
let activeRevenueEvaluationRange = '30d';
let revenueEvaluationRangeBound = false;

document.addEventListener('DOMContentLoaded', function () {
    axios.get(`/dashboard/data`)
        .then(({ data }) => {
            const metrics = data.metrics ?? {};

            animateCounter('totalComprasMonto', metrics.totalComprasMonto ?? 0);
            animateCounter('totalVentasMonto', metrics.totalVentasMonto ?? 0);
            animateCounter('totalComprasTransacciones', metrics.totalComprasTransacciones ?? 0);
            animateCounter('totalVentasTransacciones', metrics.totalVentasTransacciones ?? 0);
            animateCounter('pedidosCompletados', metrics.pedidosCompletados ?? 0);
            animateCounter('pedidosTotal', metrics.pedidosTotal ?? 0);
            animateCounter('pedidosPendientes', metrics.pedidosPendientes ?? 0);
            const completionRate = Number(metrics.pedidosCompletionRate ?? 0);
            updateText('pedidosCompletionRate', `${completionRate.toFixed(2)}%`);
            animateCounter('totalGanancia', metrics.totalGanancia ?? 0);
            const targetProgress = Number(metrics.salesTargetProgress ?? 0);
            animateCounter('salesTargetProgress', Math.round(targetProgress));
            updateText('salesTargetAmount', formatCompactCurrency(metrics.salesTargetAmount ?? 0));
            updateText('salesTargetRemaining', formatCompactCurrency(metrics.salesTargetRemaining ?? 0));

            const monthsTimeline = data.monthsRange ?? [];
            const resumenFallbackMonths = buildResumenFallbackMonths(data.ventas, data.compras);

            // Ventas vs Compras (mensual)
            const ventasComprasChart = document.querySelector("#ventasComprasChart");
            if (ventasComprasChart) {
                const initialRange = '6m';
                const labelEl = document.getElementById('ventasComprasOrdenLabel');
                if (labelEl) {
                    labelEl.textContent = labelForRange(initialRange);
                }
                const chartInstance = new ApexCharts(ventasComprasChart, buildResumenChartOptions([], []));
                chartInstance.render();

                const rangeButtons = document.querySelectorAll('.ventas-compras-order');
                rangeButtons.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const range = btn.getAttribute('data-order');
                        if (labelEl) {
                            labelEl.textContent = labelForRange(range);
                        }
                        renderResumenChart(chartInstance, data.ventas, data.compras, range, monthsTimeline, resumenFallbackMonths);
                    });
                });

                renderResumenChart(chartInstance, data.ventas, data.compras, initialRange, monthsTimeline, resumenFallbackMonths);
            }

            if (data.ordersSummary) {
                initOrdersPerformanceChart(data.ordersSummary);
            }

            // Distribucion de productos vendidos/comprados con filtros
            const distributionDefaultRange = '6m';
            const ventasDistributionRanges = data.ventasProductosByRange ?? {};

            const ventasDefaultEntries = extractDistributionEntries(ventasDistributionRanges, distributionDefaultRange, data.ventasProductos ?? []);

            const ventasDistribucionChart = initEchartsPieChart(
                'ventasProductosChart',
                ventasDefaultEntries.labels,
                ventasDefaultEntries.values,
                ['#405189', '#0AB39C', '#F7B84B', '#F06548', '#6F42C1']
            );


            setupDistributionFilter({
                prefix: 'ventas',
                chartRef: ventasDistribucionChart,
                dataset: ventasDistributionRanges,
                fallback: data.ventasProductos ?? [],
                defaultRange: distributionDefaultRange,
            });

            // Top clientes (barra horizontal con marcador de montos, doble escala)
            const topClientesRanges = data.topClientesByRange ?? {};
            const topClientesFallback = data.topClientes ?? [];
            const topClientesDefaultRange = '6m';
            const pedidosColor = '#0AB39C';
            const montoColor = '#405189';
            const topClientesChartEl = document.querySelector("#topClientesChart");

            if (topClientesChartEl) {
                const initialClientes = extractTopClientesEntries(topClientesRanges, topClientesDefaultRange, topClientesFallback);
                const { series: initialTopSeries, axis: initialAxis } = buildTopClientesSeries(initialClientes, montoColor);

                const topClientesChart = new ApexCharts(topClientesChartEl, {
                    chart: { type: 'bar', height: 360, toolbar: { show: false } },
                    colors: [pedidosColor],
                    series: initialTopSeries,
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            barHeight: '70%',
                            borderRadius: 6,
                            colors: { ranges: [{ from: 0, to: Number.MAX_VALUE, color: pedidosColor }] },
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: val => formatVelzonNumber(val),
                        style: { colors: ['#fff'], fontSize: '13px', fontWeight: 500 },
                    },
                    xaxis: {
                        labels: {
                            formatter: val => Math.round(Number(val)).toLocaleString('es-PE'),
                        },
                        ...initialAxis,
                    },
                    yaxis: {
                        labels: {
                            style: { colors: '#6c757d', fontSize: '13px' },
                            maxWidth: 230,
                        },
                        axisTicks: { show: false },
                        axisBorder: { show: false },
                    },
                    grid: { padding: { left: 24, right: 0 } },
                    legend: {
                        show: true,
                        showForSingleSeries: true,
                        position: 'bottom',
                        horizontalAlign: 'center',
                        markers: { width: 10, height: 10 },
                        labels: { colors: '#6c757d' },
                        itemMargin: { horizontal: 12 },
                        onItemClick: { toggleDataSeries: false },
                    },
                    tooltip: {
                        shared: false,
                        followCursor: true,
                        custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                            const point = w.config.series[seriesIndex].data[dataPointIndex];
                            const pedidos = series[seriesIndex][dataPointIndex];
                            const monto = point.goalAmount ?? 0;
                            return `
                                <div class="apexcharts-tooltip-title" style="font-size:13px;font-weight:600;color:#5b6280;font-family:'Poppins',sans-serif;">
                                    ${point.x}
                                </div>
                                <div style="padding:8px 14px 10px;font-family:'Poppins',sans-serif;">
                                    <div class="apexcharts-tooltip-series-group apexcharts-active" style="display:flex;align-items:center;margin-bottom:8px;">
                                        <span class="apexcharts-tooltip-marker" style="background:${pedidosColor};width:10px;height:10px;border-radius:50%;margin-right:10px;"></span>
                                        <div class="apexcharts-tooltip-text" style="display:flex;gap:6px;font-size:12px;color:#8a94a6;">
                                            <div class="apexcharts-tooltip-label">Pedidos:</div>
                                            <div class="apexcharts-tooltip-value" style="color:#405189;font-weight:600;">${formatVelzonNumber(pedidos)}</div>
                                        </div>
                                    </div>
                                    <div class="apexcharts-tooltip-series-group apexcharts-active" style="display:flex;align-items:center;">
                                        <span class="apexcharts-tooltip-marker" style="width:12px;height:3px;border-radius:2px;background:${montoColor};margin-right:10px;"></span>
                                        <div class="apexcharts-tooltip-text" style="display:flex;gap:6px;font-size:12px;color:#8a94a6;">
                                            <div class="apexcharts-tooltip-label">Monto:</div>
                                            <div class="apexcharts-tooltip-value" style="color:#405189;font-weight:600;">${formatCurrency(monto)}</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        },
                    },
                });

                topClientesChart.render().then(() => {
                    enhanceTopClientesLegend(topClientesChartEl, pedidosColor, montoColor);
                });

                setupTopClientesFilter({
                    chart: topClientesChart,
                    container: topClientesChartEl,
                    dataset: topClientesRanges,
                    fallback: topClientesFallback,
                    defaultRange: topClientesDefaultRange,
                    labelId: 'topClientesRangeLabel',
                    pedidosColor,
                    montoColor,
                });
            }

        })
        .catch(error => {
            console.error("Error al cargar dashboard:", error);
        });

    loadRevenuePredictions();
    loadTopPredictedProducts();
    loadRevenueEvaluation();
});

function renderResumenChart(chartInstance, ventas, compras, range, timeline = [], fallbackMonths = []) {
    const ventasMap = createMonthlyMap(ventas);
    const comprasMap = createMonthlyMap(compras);
    const monthsSequence = selectResumenMonths(timeline.length ? timeline : fallbackMonths, range);
    const ventasData = monthsSequence.map(month => Number(ventasMap.get(month) ?? 0));
    const comprasData = monthsSequence.map(month => Number(comprasMap.get(month) ?? 0));

    chartInstance.updateOptions(buildResumenChartOptions(monthsSequence.map(monthLabel), ventasData, comprasData));

    const totalVentas = ventasData.reduce((sum, value) => sum + value, 0);
    const totalCompras = comprasData.reduce((sum, value) => sum + value, 0);
    const ratio = totalVentas ? ((totalVentas - totalCompras) / totalVentas) * 100 : 0;

    updateText('ventasComparacionTotal', formatCompactCurrency(totalVentas));
    updateText('comprasComparacionTotal', formatCompactCurrency(totalCompras));
    updateText('ventasComparacionRatio', `${ratio.toFixed(1)}%`);
}

function enhanceTopClientesLegend(container, pedidosColor, montoColor) {
    if (!container) {
        return;
    }
    const legend = container.querySelector('.apexcharts-legend');
    if (!legend) {
        setTimeout(() => enhanceTopClientesLegend(container, pedidosColor, montoColor), 120);
        return;
    }

    legend.querySelectorAll('.legend-monto').forEach(node => node.remove());

    const baseSeries = legend.querySelector('.apexcharts-legend-series');
    if (!baseSeries) {
        return;
    }
    baseSeries.classList.remove('legend-monto');
    const marker = baseSeries.querySelector('.apexcharts-legend-marker');
    if (marker) {
        marker.classList.add('legend-dot');
        marker.style.width = '12px';
        marker.style.height = '12px';
        marker.style.borderRadius = '4px';
        marker.style.background = pedidosColor;
        marker.style.borderColor = pedidosColor;
    }
    const text = baseSeries.querySelector('.apexcharts-legend-text');
    if (text) {
        text.textContent = 'Pedidos';
    }

    const montoSerie = baseSeries.cloneNode(true);
    montoSerie.classList.add('legend-monto');
    const montoMarker = montoSerie.querySelector('.apexcharts-legend-marker');
    if (montoMarker) {
        montoMarker.style.background = montoColor;
        montoMarker.style.borderColor = montoColor;
    }
    const montoText = montoSerie.querySelector('.apexcharts-legend-text');
    if (montoText) {
        montoText.textContent = 'Monto';
    }
    legend.appendChild(montoSerie);
}

function setupTopClientesFilter({
    chart,
    container,
    dataset = {},
    fallback = [],
    defaultRange = '6m',
    labelId,
    pedidosColor,
    montoColor,
}) {
    const labelEl = labelId ? document.getElementById(labelId) : null;
    if (labelEl) {
        labelEl.textContent = labelForRange(defaultRange);
    }

    const triggers = document.querySelectorAll('.top-clientes-range');
    triggers.forEach(trigger => {
        if (trigger.getAttribute('data-range') === defaultRange) {
            trigger.classList.add('active');
        }
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const range = trigger.getAttribute('data-range');
            if (!range || !chart) {
                return;
            }

            triggers.forEach(item => item.classList.remove('active'));
            trigger.classList.add('active');

            if (labelEl) {
                labelEl.textContent = labelForRange(range);
            }

            const clientes = extractTopClientesEntries(dataset, range, fallback);
            const { series, axis } = buildTopClientesSeries(clientes, montoColor);

            chart.updateOptions({
                series,
                xaxis: {
                    ...(chart.w?.config?.xaxis ?? {}),
                    ...axis,
                },
            }).then(() => {
                enhanceTopClientesLegend(container, pedidosColor, montoColor);
            });
        });
    });
}

function extractTopClientesEntries(dataset = {}, range = '6m', fallback = []) {
    const entries = dataset?.[range];
    if (Array.isArray(entries)) {
        return entries;
    }
    return Array.isArray(fallback) ? fallback : [];
}

function buildTopClientesSeries(clientesData = [], montoColor = '#405189') {
    const pedidosValues = clientesData.map(item => Number(item.total_pedidos ?? 0));
    const montosValues = clientesData.map(item => Number(item.total_ventas ?? 0));
    const maxPedidos = Math.max(...pedidosValues, 0);
    const maxMontos = Math.max(...montosValues, 1);
    const amountScale = maxPedidos > 0 ? maxMontos / maxPedidos : 1;
    const axisStep = determineAxisStep(maxPedidos);
    const pedidosAxisMax = Math.max(axisStep, Math.ceil(maxPedidos / axisStep) * axisStep);
    const pedidosTickAmount = Math.max(1, Math.round(pedidosAxisMax / axisStep));

    const topClientesSeries = clientesData.map((item, idx) => ({
        x: item.cliente,
        y: pedidosValues[idx],
        goalAmount: montosValues[idx],
        goals: [
            {
                name: 'Monto',
                value: montosValues[idx] / amountScale,
                strokeHeight: 15,
                strokeWidth: 5,
                strokeColor: montoColor,
            },
        ],
    }));

    return {
        series: [{ name: 'Pedidos', data: topClientesSeries }],
        axis: {
            tickAmount: pedidosTickAmount,
            min: 0,
            max: pedidosAxisMax,
            decimalsInFloat: 0,
            tickPlacement: 'between',
        },
    };
}

function determineAxisStep(maxValue) {
    if (maxValue <= 10) {
        return 2;
    }
    if (maxValue <= 30) {
        return 5;
    }
    if (maxValue <= 100) {
        return 10;
    }
    if (maxValue <= 200) {
        return 20;
    }
    if (maxValue <= 500) {
        return 50;
    }
    return 100;
}


function buildResumenChartOptions(categories = [], ventasData = [], comprasData = []) {
    const palette = ['#34c38f', '#f46a6a'];
    return {
        chart: { type: 'area', height: 365, toolbar: { show: false } },
        stroke: {
            curve: 'smooth',
            width: 3,
            colors: palette,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                gradientToColors: palette,
                inverseColors: false,
                opacityFrom: 0.75,
                opacityTo: 0.25,
                stops: [0, 90, 100],
            },
        },
        dataLabels: { enabled: false },
        series: [
            { name: 'Ventas', data: ventasData },
            { name: 'Compras', data: comprasData },
        ],
        colors: palette,
        xaxis: { categories },
        yaxis: { labels: { formatter: value => `S/ ${Number(value).toLocaleString('es-PE')}` } },
        legend: { position: 'bottom', horizontalAlign: 'center' },
        grid: { borderColor: 'transparent' },
    };
}

function labelForRange(range) {
    switch (range) {
        case '1m':
            return 'Ultimo mes';
        case '6m':
            return 'Ultimos 6 meses';
        case '12m':
            return 'Ultimos 12 meses';
        case 'ytd':
            return 'Año en curso';
        default:
            return 'Ultimos 6 meses';
    }
}

function buildResumenFallbackMonths(ventas = [], compras = []) {
    return [...new Set([
        ...ventas.map(v => v.month),
        ...compras.map(c => c.month),
    ].filter(Boolean))].sort();
}

function createMonthlyMap(entries = []) {
    return entries.reduce((map, item) => {
        if (!item?.month) {
            return map;
        }
        map.set(item.month, Number(item.total ?? 0));
        return map;
    }, new Map());
}

function selectResumenMonths(baseMonths = [], range = '6m') {
    if (!baseMonths.length) {
        return [];
    }
    const months = [...baseMonths];
    const currentYearPrefix = `${new Date().getFullYear()}-`;

    switch (range) {
        case '1m':
            return months.slice(-1);
        case '12m':
            return months.slice(-12);
        case 'ytd': {
            const yearMonths = months.filter(key => key.startsWith(currentYearPrefix));
            return yearMonths.length ? yearMonths : months.slice(-12);
        }
        case '6m':
        default:
            return months.slice(-6);
    }
}


function currentMonthIndex() {
    return new Date().getMonth();
}

function monthLabel(monthKey) {
    const [year, month] = monthKey.split('-');
    const date = new Date(Number(year), Number(month) - 1, 1);
    return date.toLocaleString('es-PE', { month: 'short' });
}

function labelForOrdersPerformanceRange(range) {
    switch (range) {
        case '1m':
            return 'Ultimo mes';
        case '6m':
            return 'Ultimos 6 meses';
        case '12m':
            return 'Ultimos 12 meses';
        case 'ytd':
            return 'Año en curso';
        default:
            return 'Ultimos 6 meses';
    }
}

function initOrdersPerformanceChart(summary) {
    const chartElement = document.querySelector('#ordersPerformanceChart');
    if (!chartElement) return;

    const chartInstance = new ApexCharts(chartElement, buildOrdersPerformanceOptions([], [], []));
    chartInstance.render();

    const buttons = document.querySelectorAll('.orders-performance-range');
    let currentRange = '6m';
    const ordersRangeLabel = document.getElementById('ordersPerformanceRangeLabel');

    const refreshOrdersRangeLabel = (range) => {
        if (ordersRangeLabel) {
            ordersRangeLabel.textContent = labelForOrdersPerformanceRange(range);
        }
    };

    buttons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentRange = btn.getAttribute('data-range');
            refreshOrdersRangeLabel(currentRange);
            renderOrdersPerformanceChart(chartInstance, summary, currentRange);
        });
    });

    refreshOrdersRangeLabel(currentRange);
    renderOrdersPerformanceChart(chartInstance, summary, currentRange);
}

function renderOrdersPerformanceChart(chartInstance, summary, range) {
    const aggregated = aggregateOrdersSummary(summary);
    const dataset = selectOrdersRange(aggregated, range);

    chartInstance.updateOptions(buildOrdersPerformanceOptions(dataset.categories, dataset.orders, dataset.earnings, dataset.refunds));

    const totalOrders = dataset.orders.reduce((sum, value) => sum + value, 0);
    const totalEarnings = dataset.earnings.reduce((sum, value) => sum + value, 0);
    const totalRefunds = dataset.refunds.reduce((sum, value) => sum + value, 0);
    const conversion = totalOrders > 0 ? ((totalOrders - totalRefunds) / totalOrders) * 100 : 0;

    updateText('ordersPerformanceOrders', formatVelzonNumber(totalOrders));
    updateText('ordersPerformanceEarnings', formatCompactCurrency(totalEarnings));
    updateText('ordersPerformanceRefunds', formatVelzonNumber(totalRefunds));
    updateText('ordersPerformanceConversion', formatPercentage(conversion));
}

function initEchartsPieChart(containerId, labels = [], values = [], colors = []) {
    const el = document.getElementById(containerId);
    const echartsLib = window.echarts;
    if (!el || !echartsLib) {
        return null;
    }

    const existingInstance = echartsLib.getInstanceByDom(el);
    if (existingInstance) {
        existingInstance.dispose();
    }

    const chart = echartsLib.init(el);
    const dataset = buildPieSeriesData(labels, values, colors);

    chart.setOption({
        tooltip: {
            trigger: 'item',
            backgroundColor: '#fff',
            borderColor: '#edf1f7',
            borderWidth: 1,
            textStyle: { color: '#212529', fontSize: 12, fontWeight: 500 },
            padding: 11,
            formatter: params => {
                const marker = `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${params.color};margin-right:8px;"></span>`;
                return `
                    <div style="font-size:12px; font-weight:500; color:#495057; margin-bottom:4px;">${params.name}</div>
                    <div style="display:flex; align-items:center; font-size:13px; color:#8a96a3;">
                        ${marker}
                        <span style="margin-right:6px;">Cantidad:</span>
                        <span style="color:#111927; font-size:12px;">${formatVelzonNumber(params.value)}</span>
                    </div>
                `.trim();
            },
        },
        legend: {
            orient: 'vertical',
            left: 0,
            top: 'middle',
            textStyle: { color: '#6c757d', fontFamily: 'Poppins, sans-serif' },
            icon: 'circle',
            data: labels,
        },
        series: [
            {
                type: 'pie',
                radius: '70%',
                center: ['65%', '50%'],
                data: dataset.data,
                hoverAnimation: true,
                label: {
                    formatter: '{b}',
                    color: '#6c757d',
                    fontSize: 12,
                },
                labelLine: {
                    show: true,
                    length: 20,
                    length2: 14,
                    smooth: true,
                },
                itemStyle: {
                    borderColor: 'transparent',
                    borderWidth: 0,
                },
                emphasis: {
                    scale: true,
                    scaleSize: 6,
                    label: {
                        show: true,
                        formatter: '{b}',
                        fontWeight: 500,
                        color: '#6c757d',
                    },
                    labelLine: {
                        length: 25,
                        length2: 18,
                    },
                    itemStyle: {
                        shadowBlur: 15,
                        shadowOffsetX: 0,
                        shadowColor: 'rgba(0, 0, 0, 0.3)',
                        borderColor: 'transparent',
                        borderWidth: 0,
                    },
                },
                color: dataset.palette,
            },
        ],
        textStyle: { fontFamily: 'Poppins, sans-serif' },
    });

    const resizeHandler = () => chart.resize();
    window.addEventListener('resize', resizeHandler);

    return { chart, palette: dataset.palette, resizeHandler };
}

function updateEchartsPieChart(instance, labels = [], values = []) {
    if (!instance || !instance.chart) {
        return;
    }
    const dataset = buildPieSeriesData(labels, values, instance.palette);
    instance.chart.setOption({
        legend: { data: labels },
        color: dataset.palette,
        series: [
            {
                data: dataset.data,
            },
        ],
    });
}

function buildPieSeriesData(labels = [], values = [], colors = []) {
    const palette = (colors && colors.length) ? colors : ['#405189', '#0AB39C', '#F7B84B', '#F06548', '#6F42C1'];
    const data = labels.map((label, index) => {
        const color = palette[index % palette.length];
        return {
            name: label,
            value: Number(values[index] ?? 0),
            itemStyle: { color },
            labelLine: {
                lineStyle: {
                    color,
                    width: 1.4,
                },
            },
        };
    });

    return { data, palette };
}

function extractDistributionEntries(dataset = {}, range = '6m', fallback = []) {
    const hasRange = dataset && dataset[range] && dataset[range].length;
    const entries = hasRange ? dataset[range] : (fallback ?? []);
    return {
        labels: entries.map(item => item.producto ?? ''),
        values: entries.map(item => Number(item.total ?? item.total_vendido ?? item.total_comprado ?? 0)),
    };
}

function labelForDistributionRange(range) {
    switch (range) {
        case '1m':
            return 'Ultimo mes';
        case '12m':
            return 'Ultimos 12 meses';
        case 'ytd':
            return 'Año en curso';
        case '6m':
        default:
            return 'Ultimos 6 meses';
    }
}

function setupDistributionFilter({ prefix, chartRef, dataset = {}, fallback = [], defaultRange = '6m' }) {
    const labelEl = document.getElementById(`${prefix}DistribucionOrdenLabel`);
    const triggers = document.querySelectorAll(`.${prefix}-distribucion-order`);

    const renderRange = (range) => {
        const { labels, values } = extractDistributionEntries(dataset, range, fallback);
        updateEchartsPieChart(chartRef, labels, values);
        if (labelEl) {
            labelEl.textContent = labelForDistributionRange(range);
        }
    };

    triggers.forEach(item => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            const range = item.getAttribute('data-range');
            renderRange(range);
        });
    });

    renderRange(defaultRange);
}

function buildOrdersPerformanceOptions(categories, ordersData, earningsData, refundsData) {
    return {
        chart: {
            height: 360,
            type: 'line',
            stacked: false,
            toolbar: { show: false },
            foreColor: '#98a6ad',
        },
        plotOptions: {
            bar: {
                columnWidth: '38%',
                borderRadius: 6,
                endingShape: 'rounded',
            },
        },
        stroke: {
            width: [0, 3, 3],
            curve: 'smooth',
            colors: ['#47ad77', '#c5c9d1', '#c5c9d1'],
            dashArray: [0, 0, 0],
        },
        fill: {
            opacity: [1, 0.12, 1],
            type: ['solid', 'gradient', 'solid'],
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.38,
                opacityTo: 0.02,
                stops: [0, 85, 100],
            },
        },
        markers: {
            size: [3, 3, 3],
            strokeColors: ['#47ad77', '#5A8DEE', '#F1734F'],
            strokeWidth: 2,
            colors: ['#47ad77', '#5A8DEE', '#F1734F'],
            hover: { sizeOffset: 3 },
        },
        dataLabels: { enabled: false },
        series: [
            { name: 'Ganancias', type: 'column', data: earningsData, yAxisIndex: 0 },
            { name: 'Pedidos', type: 'line', data: ordersData, yAxisIndex: 1 },
            { name: 'Anulados', type: 'line', data: refundsData, yAxisIndex: 1 },
        ],
        colors: ['#47ad77', '#5A8DEE', '#F1734F'],
        xaxis: {
            categories,
            axisTicks: { show: false },
            axisBorder: { show: false },
            labels: {
                style: { colors: '#98a6ad' },
            },
        },
        annotations: {
            yaxis: [
                {
                    y: 0,
                    borderColor: '#adb5bd',
                    strokeDashArray: 4,
                },
            ],
        },
        yaxis: [
            {
                axisTicks: { show: false },
                axisBorder: { show: false },
                labels: {
                    style: { colors: '#98a6ad' },
                    formatter: value => `S/ ${Number(value).toLocaleString('es-PE')}`,
                },
                title: {
                    text: 'Ganancias',
                    style: { color: '#98a6ad' },
                },
            },
            {
                show: false,
                axisTicks: { show: false },
                axisBorder: { show: false },
                labels: { show: false },
            },
        ],
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            markers: { width: 10, height: 10, radius: 4 },
            labels: { colors: '#748494' },
        },
        grid: {
            strokeDashArray: 3,
            yaxisLines: { show: true },
            borderColor: 'rgba(152, 166, 173, 0.25)',
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: [
                { formatter: val => formatCurrency(val) },
                { formatter: val => `${val} ordenes` },
                { formatter: val => `${val} anulados` },
            ],
        },
    };
}

function animateCounter(elementId, endValue, duration = 500) {
    const el = document.getElementById(elementId);
    if (!el) {
        return;
    }

    // Mostrar el wrapper del número
    const wrapper = el.closest('h2');
    if (wrapper && wrapper.classList.contains('opacity-0')) {
        wrapper.classList.remove('opacity-0');
    }

    const startValue = 0;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const value = Math.floor(progress * (endValue - startValue) + startValue);
        el.innerText = value.toLocaleString();

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

function updateText(elementId, value) {
    const el = document.getElementById(elementId);
    if (el) {
        el.textContent = value;
    }
}

function formatCurrency(value) {
    const parsed = Number(value || 0);
    return `S/ ${parsed.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatCompactCurrency(value) {
    const parsed = Number(value || 0);
    const absValue = Math.abs(parsed);
    const suffixes = [
        { value: 1e6, symbol: ' M' },
        { value: 1e3, symbol: ' mil' },
    ];

    if (absValue < 1000) {
        return formatCurrency(parsed);
    }

    const match = suffixes.find((s) => absValue >= s.value) ?? suffixes[suffixes.length - 1];
    const compactValue = (parsed / match.value).toFixed(2).replace(/\.?0+$/, '');
    return `S/ ${compactValue}${match.symbol}`;
}

function formatVelzonNumber(value) {
    const parsed = Number(value || 0);
    return parsed.toLocaleString('es-PE');
}

function formatPredictionRange(startDate, endDate) {
    if (!startDate || !endDate) return '';
    const options = { day: '2-digit', month: 'short', year: 'numeric' };
    const start = new Date(`${startDate}T00:00:00`);
    const end = new Date(`${endDate}T00:00:00`);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return '';
    }

    const formatter = new Intl.DateTimeFormat('es-PE', options);
    const startLabel = formatter.format(start);
    const endLabel = formatter.format(end);
    if (startLabel === endLabel) {
        return `para ${startLabel}`;
    }
    return `del ${startLabel} al ${endLabel}`;
}

function updateRangeDropdownLabel(elementId, text) {
    const el = document.getElementById(elementId);
    if (el) {
        el.textContent = text;
    }
}

function setActiveRangeItems(selector, activeRange) {
    document.querySelectorAll(selector).forEach((item) => {
        if (item.getAttribute('data-range') === activeRange) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

function getPredictionRangeLabel(rangeKey) {
    return (REVENUE_PREDICTION_RANGES[rangeKey] ?? REVENUE_PREDICTION_RANGES['30d']).label;
}

function getEvaluationRangeLabel(rangeKey) {
    return (REVENUE_EVALUATION_RANGES[rangeKey] ?? REVENUE_EVALUATION_RANGES['30d']).label;
}

function getPredictionDatasetByRange(rangeKey) {
    const source = revenuePredictionData || {};
    const useFull = rangeKey === 'all';
    const labelsSource = useFull ? (source.full_labels ?? source.labels ?? []) : (source.labels ?? []);
    const valuesSource = useFull ? (source.full_values ?? source.values ?? []) : (source.values ?? []);
    const lowerSource = useFull ? (source.full_lower ?? source.lower ?? []) : (source.lower ?? []);
    const upperSource = useFull ? (source.full_upper ?? source.upper ?? []) : (source.upper ?? []);

    const zipped = labelsSource.map((label, index) => ({
        label,
        value: valuesSource[index],
        lower: lowerSource[index],
        upper: upperSource[index],
    })).sort((a, b) => new Date(a.label) - new Date(b.label));

    const labels = zipped.map(item => item.label);
    const values = zipped.map(item => item.value);
    const lower = zipped.map(item => item.lower);
    const upper = zipped.map(item => item.upper);
    const limitValue = REVENUE_PREDICTION_RANGES[rangeKey]?.limit;
    const limit = typeof limitValue === 'number' && limitValue > 0 ? limitValue : null;

    if (!limit) {
        return { labels, values, lower, upper };
    }

    return {
        labels: labels.slice(0, limit),
        values: values.slice(0, limit),
        lower: lower.slice(0, limit),
        upper: upper.slice(0, limit),
    };
}

function getEvaluationDatasetByRange(rangeKey) {
    const source = revenueEvaluationData || {};
    const labels = [...(source.labels ?? [])];
    const real = [...(source.real ?? [])];
    const predicted = [...(source.predicted ?? [])];
    const limit = REVENUE_EVALUATION_RANGES[rangeKey]?.limit;

    if (!limit || limit >= labels.length || limit <= 0) {
        return { labels, real, predicted };
    }

    const startIndex = Math.max(labels.length - limit, 0);
    return {
        labels: labels.slice(startIndex),
        real: real.slice(startIndex),
        predicted: predicted.slice(startIndex),
    };
}

function computeEvaluationMetrics(real = [], predicted = []) {
    const mae = computeMae(real, predicted);
    const rmse = computeRmse(real, predicted);
    const mape = computeMape(real, predicted);
    return {
        mae,
        rmse,
        mape,
    };
}

function computeMae(real = [], predicted = []) {
    const length = Math.min(real.length, predicted.length);
    if (!length) return 0;
    let sum = 0;
    for (let i = 0; i < length; i += 1) {
        sum += Math.abs((real[i] ?? 0) - (predicted[i] ?? 0));
    }
    return sum / length;
}

function computeRmse(real = [], predicted = []) {
    const length = Math.min(real.length, predicted.length);
    if (!length) return 0;
    let sum = 0;
    for (let i = 0; i < length; i += 1) {
        const diff = (real[i] ?? 0) - (predicted[i] ?? 0);
        sum += diff ** 2;
    }
    return Math.sqrt(sum / length);
}

function computeMape(real = [], predicted = []) {
    const length = Math.min(real.length, predicted.length);
    if (!length) return 0;
    let validCount = 0;
    let sum = 0;
    for (let i = 0; i < length; i += 1) {
        const realValue = real[i] ?? 0;
        if (realValue === 0) continue;
        const diff = Math.abs(realValue - (predicted[i] ?? 0));
        sum += (diff / Math.abs(realValue)) * 100;
        validCount += 1;
    }
    if (!validCount) return 0;
    return sum / validCount;
}

function enforcePredictionColors(chartInstance, options = {}) {
    if (!chartInstance || !options) return;
    const colors = options.colors ?? ['#3b82f6', '#22c55e', '#f97316'];
    const stroke = options.stroke ?? {};
    chartInstance.updateOptions({
        colors,
        stroke: {
            ...stroke,
            colors: stroke.colors ?? colors,
        },
    }, false, true);
}

function formatPercentage(value) {
    const parsed = Number(value || 0);
    return `${parsed.toFixed(1)}%`;
}

function loadRevenuePredictions() {
    const chartEl = document.getElementById('revenuePredictionChart');
    const emptyEl = document.getElementById('revenuePredictionEmpty');
    if (!chartEl) return;

    axios.get('/dashboard/predicciones/ingresos')
        .then(({ data }) => {
            revenuePredictionData = data;
            if (!data || !(data.labels || []).length) {
                revenuePredictionData = null;
                if (revenuePredictionChart) {
                    revenuePredictionChart.destroy();
                    revenuePredictionChart = null;
                }
                if (chartEl) chartEl.classList.add('d-none');
                if (emptyEl) emptyEl.classList.remove('d-none');
                return;
            }

            activeRevenuePredictionRange = activeRevenuePredictionRange || '30d';
            updateRangeDropdownLabel('revenuePredictionRangeLabel', getPredictionRangeLabel(activeRevenuePredictionRange));
            setActiveRangeItems('.revenue-prediction-range', activeRevenuePredictionRange);
            renderRevenuePredictionChart();
            bindRevenuePredictionRangeEvents();
        })
        .catch(() => {
            if (chartEl) chartEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.remove('d-none');
        });
}

function renderRevenuePredictionChart() {
    const chartEl = document.getElementById('revenuePredictionChart');
    const emptyEl = document.getElementById('revenuePredictionEmpty');
    if (!chartEl) return;

    const dataset = getPredictionDatasetByRange(activeRevenuePredictionRange);
    if (!dataset.labels.length) {
        if (revenuePredictionChart) {
            revenuePredictionChart.destroy();
            revenuePredictionChart = null;
        }
        chartEl.classList.add('d-none');
        if (emptyEl) emptyEl.classList.remove('d-none');
        return;
    }

    chartEl.classList.remove('d-none');
    if (emptyEl) emptyEl.classList.add('d-none');

    if (revenuePredictionChart) {
        revenuePredictionChart.destroy();
        revenuePredictionChart = null;
    }

    const options = buildRevenuePredictionOptions(dataset);
    revenuePredictionChart = new ApexCharts(chartEl, options);
    revenuePredictionChart.render().then(() => {
        enforcePredictionColors(revenuePredictionChart, options);
    });
}

function bindRevenuePredictionRangeEvents() {
    if (revenuePredictionRangeBound) return;
    document.querySelectorAll('.revenue-prediction-range').forEach((item) => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            const range = item.getAttribute('data-range');
            if (!range || range === activeRevenuePredictionRange) return;
            activeRevenuePredictionRange = range;
            updateRangeDropdownLabel('revenuePredictionRangeLabel', getPredictionRangeLabel(range));
            setActiveRangeItems('.revenue-prediction-range', range);
            renderRevenuePredictionChart();
        });
    });
    revenuePredictionRangeBound = true;
}

function buildRevenuePredictionOptions(dataset = {}) {
    const labels = dataset.labels ?? [];
    const values = dataset.values ?? [];
    const lower = dataset.lower ?? [];
    const upper = dataset.upper ?? [];

    return {
        chart: { type: 'line', height: 360, toolbar: { show: false } },
        stroke: {
            width: [3, 2, 2],
            dashArray: [0, 7, 7],
            curve: 'smooth',
            colors: ['#3b82f6', '#22c55e', '#f97316'],
        },
        markers: {
            size: 3,
            strokeWidth: 2,
            hover: { sizeOffset: 2 },
        },
        colors: ['#3b82f6', '#22c55e', '#f97316'],
        series: [
            { name: 'Ingreso Predicho', data: values, color: '#3b82f6' },
            { name: 'Límite Superior', data: upper, color: '#22c55e' },
            { name: 'Límite Inferior', data: lower, color: '#f97316' },
        ],
        xaxis: {
            categories: labels,
            type: 'datetime',
            labels: { rotate: -45 },
        },
        yaxis: {
            labels: { formatter: (val) => formatCompactCurrency(val) },
        },
        tooltip: {
            shared: true,
            y: { formatter: (val) => formatCurrency(val) },
        },
        fill: {
            type: ['gradient', 'solid', 'solid'],
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.25,
                opacityTo: 0.05,
                stops: [0, 90, 100],
            },
        },
        dataLabels: { enabled: false },
        legend: { position: 'top' },
    };
}

function loadTopPredictedProducts() {
    const chartEl = document.getElementById('topPredictedProductsChart');
    const emptyEl = document.getElementById('topPredictedProductsEmpty');
    const rangeEl = document.getElementById('topPredictedProductsRange');
    if (!chartEl) return;

    axios.get('/dashboard/predicciones/productos')
        .then(({ data }) => {
            if (!data || !(data.labels || []).length) {
                if (chartEl) chartEl.classList.add('d-none');
                if (emptyEl) emptyEl.classList.remove('d-none');
                if (rangeEl) {
                    rangeEl.textContent = '';
                    rangeEl.classList.add('d-none');
                }
                return;
            }

            updateTopPredictedProductsRange(rangeEl, data.start_date, data.end_date);

            const chart = new ApexCharts(chartEl, buildTopPredictedProductsOptions(data));
            chart.render();
        })
        .catch(() => {
            if (chartEl) chartEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.remove('d-none');
            if (rangeEl) {
                rangeEl.textContent = '';
                rangeEl.classList.add('d-none');
            }
        });
}

function buildTopPredictedProductsOptions(dataset = {}) {
    const labels = dataset.labels ?? [];
    const values = dataset.values ?? [];

    return {
        chart: { type: 'bar', height: 360, toolbar: { show: false } },
        colors: ['#0AB39C'],
        plotOptions: {
            bar: {
                horizontal: true,
                dataLabels: { position: 'top' },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => Number(val || 0).toFixed(0),
            offsetX: 10,
        },
        series: [{ name: 'Cantidad Predicha', data: values }],
        xaxis: {
            categories: labels,
            labels: { formatter: (val) => Number(val || 0).toFixed(0) },
        },
        tooltip: {
            y: { formatter: (val) => `${Number(val || 0).toFixed(0)} unidades` },
        },
        grid: { borderColor: '#f1f1f1' },
    };
}

function updateTopPredictedProductsRange(rangeEl, startDate, endDate) {
    if (!rangeEl) return;
    const label = formatPredictionRange(startDate, endDate);
    if (!label) {
        rangeEl.textContent = '';
        rangeEl.classList.add('d-none');
        return;
    }

    rangeEl.textContent = `Pronóstico ${label}`;
    rangeEl.classList.remove('d-none');
}

function loadRevenueEvaluation() {
    const chartEl = document.getElementById('revenueEvaluationChart');
    const emptyEl = document.getElementById('revenueEvaluationEmpty');
    if (!chartEl) return;

    axios.get('/dashboard/predicciones/evaluacion')
        .then(({ data }) => {
            revenueEvaluationData = data;
            const labels = data.labels ?? [];
            if (!labels.length) {
                revenueEvaluationData = null;
                if (revenueEvaluationChart) {
                    revenueEvaluationChart.destroy();
                    revenueEvaluationChart = null;
                }
                chartEl.classList.add('d-none');
                if (emptyEl) emptyEl.classList.remove('d-none');
                updateText('evalMae', formatCompactCurrency(0));
                updateText('evalRmse', formatCompactCurrency(0));
                updateText('evalMape', '0%');
                return;
            }

            activeRevenueEvaluationRange = activeRevenueEvaluationRange || '30d';
            updateRangeDropdownLabel('revenueEvaluationRangeLabel', getEvaluationRangeLabel(activeRevenueEvaluationRange));
            setActiveRangeItems('.revenue-evaluation-range', activeRevenueEvaluationRange);
            renderRevenueEvaluationChart();
            bindRevenueEvaluationRangeEvents();
        })
        .catch(() => {
            if (chartEl) chartEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.remove('d-none');
        });
}

function renderRevenueEvaluationChart() {
    const chartEl = document.getElementById('revenueEvaluationChart');
    const emptyEl = document.getElementById('revenueEvaluationEmpty');
    if (!chartEl) return;

    const dataset = getEvaluationDatasetByRange(activeRevenueEvaluationRange);
    if (!dataset.labels.length) {
        if (revenueEvaluationChart) {
            revenueEvaluationChart.destroy();
            revenueEvaluationChart = null;
        }
        chartEl.classList.add('d-none');
        if (emptyEl) emptyEl.classList.remove('d-none');
        updateText('evalMae', formatCompactCurrency(0));
        updateText('evalRmse', formatCompactCurrency(0));
        updateText('evalMape', '0%');
        return;
    }

    chartEl.classList.remove('d-none');
    if (emptyEl) emptyEl.classList.add('d-none');

    const metrics = computeEvaluationMetrics(dataset.real, dataset.predicted);
    updateText('evalMae', formatCompactCurrency(metrics.mae));
    updateText('evalRmse', formatCompactCurrency(metrics.rmse));
    updateText('evalMape', `${metrics.mape.toFixed(2)}%`);

    if (revenueEvaluationChart) {
        revenueEvaluationChart.destroy();
        revenueEvaluationChart = null;
    }

    revenueEvaluationChart = new ApexCharts(chartEl, buildRevenueEvaluationOptions(dataset));
    revenueEvaluationChart.render();
}

function bindRevenueEvaluationRangeEvents() {
    if (revenueEvaluationRangeBound) return;
    document.querySelectorAll('.revenue-evaluation-range').forEach((item) => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            const range = item.getAttribute('data-range');
            if (!range || range === activeRevenueEvaluationRange) return;
            activeRevenueEvaluationRange = range;
            updateRangeDropdownLabel('revenueEvaluationRangeLabel', getEvaluationRangeLabel(range));
            setActiveRangeItems('.revenue-evaluation-range', range);
            renderRevenueEvaluationChart();
        });
    });
    revenueEvaluationRangeBound = true;
}

function buildRevenueEvaluationOptions(dataset = {}) {
    const labels = dataset.labels ?? [];
    const real = dataset.real ?? [];
    const predicted = dataset.predicted ?? [];

    return {
        chart: { type: 'line', height: 360, toolbar: { show: false } },
        stroke: { width: 3 },
        colors: ['#0AB39C', '#405189'],
        series: [
            { name: 'Ingreso Real', data: real },
            { name: 'Ingreso Predicho', data: predicted },
        ],
        xaxis: {
            categories: labels,
            type: 'datetime',
            labels: { rotate: -45 },
        },
        yaxis: {
            labels: { formatter: (val) => formatCompactCurrency(val) },
        },
        tooltip: {
            shared: true,
            y: { formatter: (val) => formatCurrency(val) },
        },
        legend: { position: 'top', horizontalAlign: 'right' },
        dataLabels: { enabled: false },
    };
}

function aggregateOrdersSummary(summary = {}) {
    const months = summary.months ?? [];
    const orders = summary.orders ?? [];
    const earnings = summary.earnings ?? [];
    const refunds = summary.refunds ?? [];

    const map = new Map();
    const timeline = [];

    months.forEach((key, index) => {
        if (!key) {
            return;
        }
        const [yearStr, monthStr] = key.split('-');
        const year = Number(yearStr);
        const monthIndex = Number(monthStr) - 1;
        if (Number.isNaN(year) || Number.isNaN(monthIndex)) {
            return;
        }
        const normalizedKey = normalizeMonthKey(year, monthIndex);
        map.set(normalizedKey, {
            key: normalizedKey,
            year,
            monthIndex,
            orders: Number(orders[index] ?? 0),
            earnings: Number(earnings[index] ?? 0),
            refunds: Number(refunds[index] ?? 0),
        });
        timeline.push({ year, monthIndex, key: normalizedKey });
    });

    timeline.sort((a, b) => (a.year === b.year ? a.monthIndex - b.monthIndex : a.year - b.year));

    return { map, timeline };
}

function selectOrdersRange(aggregated = {}, range = '6m') {
    const timeline = aggregated.timeline ?? [];
    if (!timeline.length) {
        return { categories: [], orders: [], earnings: [], refunds: [] };
    }

    const lastPoint = timeline[timeline.length - 1];
    let selected;
    switch (range) {
        case '1m':
            selected = buildRangeFromEnd(lastPoint, 1);
            break;
        case '6m':
            selected = buildRangeFromEnd(lastPoint, 6);
            break;
        case '12m':
            selected = buildRangeFromEnd(lastPoint, 12);
            break;
        case 'ytd':
            selected = buildYearToDateRange(lastPoint);
            break;
        default:
            selected = buildRangeFromEnd(lastPoint, 6);
            break;
    }

    const categories = selected.map(item => monthLabel(normalizeMonthKey(item.year, item.monthIndex)));
    const orders = selected.map(item => aggregated.map.get(normalizeMonthKey(item.year, item.monthIndex))?.orders ?? 0);
    const earnings = selected.map(item => aggregated.map.get(normalizeMonthKey(item.year, item.monthIndex))?.earnings ?? 0);
    const refunds = selected.map(item => aggregated.map.get(normalizeMonthKey(item.year, item.monthIndex))?.refunds ?? 0);

    return { categories, orders, earnings, refunds };
}

function buildRangeFromEnd(endPoint, length) {
    const months = [];
    let year = endPoint.year;
    let monthIndex = endPoint.monthIndex;

    for (let i = 0; i < length; i++) {
        months.unshift({ year, monthIndex, key: normalizeMonthKey(year, monthIndex) });
        monthIndex -= 1;
        if (monthIndex < 0) {
            monthIndex = 11;
            year -= 1;
        }
    }

    return months;
}

function buildYearToDateRange(endPoint) {
    const months = [];
    for (let monthIndex = 0; monthIndex <= endPoint.monthIndex; monthIndex++) {
        months.push({
            year: endPoint.year,
            monthIndex,
            key: normalizeMonthKey(endPoint.year, monthIndex),
        });
    }
    return months;
}

function normalizeMonthKey(year, monthIndex) {
    const normalizedMonth = String(monthIndex + 1).padStart(2, '0');
    return `${year}-${normalizedMonth}`;
}
