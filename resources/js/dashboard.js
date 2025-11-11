import axios from 'axios';

axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

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

            // Distribución de productos vendidos
            const productosVentas = data.ventasProductos.map(p => p.producto);
            const valoresVentas = data.ventasProductos.map(p => Number(p.total_vendido));

            new ApexCharts(document.querySelector("#ventasProductosChart"), {
                chart: { type: 'donut', height: 350 },
                labels: productosVentas,
                series: valoresVentas,
                legend: { position: 'bottom' },
                colors: ['#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0']
            }).render();

            // Distribución de productos comprados
            const productosCompras = data.comprasProductos.map(p => p.producto);
            const valoresCompras = data.comprasProductos.map(p => Number(p.total_comprado));

            new ApexCharts(document.querySelector("#comprasProductosChart"), {
                chart: { type: 'donut', height: 350 },
                labels: productosCompras,
                series: valoresCompras,
                legend: { position: 'bottom' },
                colors: ['#FF5733', '#C70039', '#900C3F', '#581845', '#1E8449']
            }).render();

            // Top clientes
            const clientes = data.topClientes.map(c => c.cliente);
            const ventasClientes = data.topClientes.map(c => Number(c.total_ventas));

            new ApexCharts(document.querySelector("#topClientesChart"), {
                chart: { type: 'bar', height: 350 },
                series: [{ name: 'Ventas', data: ventasClientes }],
                xaxis: { categories: clientes },
                yaxis: { title: { text: "Monto ($)" } },
                colors: ['#008FFB']
            }).render();

            // Top proveedores
            const proveedores = data.topProveedores.map(p => p.proveedor);
            const comprasProveedores = data.topProveedores.map(p => Number(p.total_compras));

            new ApexCharts(document.querySelector("#topProveedoresChart"), {
                chart: { type: 'bar', height: 350 },
                series: [{ name: 'Compras', data: comprasProveedores }],
                xaxis: { categories: proveedores },
                yaxis: { title: { text: "Monto ($)" } },
                colors: ['#FF5733']
            }).render();
        })
        .catch(error => {
            console.error("Error al cargar dashboard:", error);
        });
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

function buildResumenChartOptions(categories = [], ventasData = [], comprasData = []) {
    return {
        chart: { type: 'area', height: 365, toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0, stops: [0, 80, 100] }
        },
        dataLabels: { enabled: false },
        series: [
            { name: 'Ventas', data: ventasData },
            { name: 'Compras', data: comprasData }
        ],
        colors: ['#34c38f', '#f46a6a'],
        xaxis: { categories },
        yaxis: { labels: { formatter: value => `S/ ${Number(value).toLocaleString('es-PE')}` } },
        legend: { position: 'bottom', horizontalAlign: 'center' },
        grid: { borderColor: 'transparent' }
    };
}

function labelForRange(range) {
    switch (range) {
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
        case '12m':
            return months.slice(-12);
        case 'ytd': {
            const yearMonths = months.filter(key => key.startsWith(currentYearPrefix));
            return yearMonths.length ? yearMonths : months.slice(-12);
        }
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
        case '1y':
            return 'Ultimo año';
        default:
            return 'Todo';
    }
}

function initOrdersPerformanceChart(summary) {
    const chartElement = document.querySelector('#ordersPerformanceChart');
    if (!chartElement) return;

    const chartInstance = new ApexCharts(chartElement, buildOrdersPerformanceOptions([], [], []));
    chartInstance.render();

    const buttons = document.querySelectorAll('.orders-performance-range');
    let currentRange = 'all';
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
            dashArray: [0, 0, 4],
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
            size: [0, 4, 0],
            strokeColors: '#fff',
            strokeWidth: 2,
            hover: { sizeOffset: 3 },
        },
        dataLabels: { enabled: false },
        series: [
            { name: 'Ordenes', type: 'column', data: ordersData, yAxisIndex: 1 },
            { name: 'Ganancias', type: 'area', data: earningsData, yAxisIndex: 0 },
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
                { formatter: val => `${val} ordenes` },
                { formatter: val => formatCurrency(val) },
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
        { value: 1e6, symbol: ' millones' },
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

function formatPercentage(value) {
    const parsed = Number(value || 0);
    return `${parsed.toFixed(1)}%`;
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

function selectOrdersRange(aggregated = {}, range = 'all') {
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
        case '1y':
            selected = buildRangeFromEnd(lastPoint, 12);
            break;
        case 'all':
        default:
            selected = buildCalendarYearRange(lastPoint.year);
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

function buildCalendarYearRange(year) {
    return Array.from({ length: 12 }, (_, index) => ({
        year,
        monthIndex: index,
        key: normalizeMonthKey(year, index),
    }));
}

function normalizeMonthKey(year, monthIndex) {
    const normalizedMonth = String(monthIndex + 1).padStart(2, '0');
    return `${year}-${normalizedMonth}`;
}
