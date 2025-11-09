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

            // Ventas vs Compras (mensual)
            const meses = [...new Set([...data.ventas.map(v => v.month), ...data.compras.map(c => c.month)])].sort();
            const ventasData = meses.map(m => {
                const venta = data.ventas.find(v => v.month === m);
                return venta ? parseFloat(venta.total) : 0;
            });
            const comprasData = meses.map(m => {
                const compra = data.compras.find(c => c.month === m);
                return compra ? parseFloat(compra.total) : 0;
            });

            new ApexCharts(document.querySelector("#ventasComprasChart"), {
                chart: { type: 'bar', height: 350 },
                series: [
                    { name: "Ventas", data: ventasData },
                    { name: "Compras", data: comprasData }
                ],
                xaxis: { categories: meses.map(m => m.replace('-', '/')) },
                yaxis: { title: { text: "Monto ($)" } },
                colors: ['#28a745', '#dc3545'],
                legend: { position: 'top' }
            }).render();

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
    return `S/. ${parsed.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatCompactCurrency(value) {
    const parsed = Number(value || 0);
    const absValue = Math.abs(parsed);
    const suffixes = [
        { value: 1e12, symbol: 'T' },
        { value: 1e9, symbol: 'B' },
        { value: 1e6, symbol: 'M' },
        { value: 1e3, symbol: 'K' },
    ];

    if (absValue < 1000) {
        return formatCurrency(parsed);
    }

    const match = suffixes.find((s) => absValue >= s.value) ?? suffixes[suffixes.length - 1];
    const decimals = match.symbol === 'M' ? 3 : 1;
    const compactValue = (parsed / match.value).toFixed(decimals).replace(/\.?0+$/, '');
    return `S/. ${compactValue}${match.symbol}`;
}
