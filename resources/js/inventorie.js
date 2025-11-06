import axios from 'axios';

axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.container-fluid[data-fetch-url]');
    if (!container) {
        return;
    }

    const warehouseSelect = document.getElementById('warehouseSelect');
    const dateInput = document.getElementById('closureDate');
    const generateBtn = document.getElementById('generateClosureBtn');
    const exportButtons = [document.getElementById('exportClosureBtn')].filter(Boolean);
    const summaryElements = {
        total: document.getElementById('summary-total-orders'),
        totalDesc: document.getElementById('summary-total-orders-desc'),
        paid: document.getElementById('summary-paid-orders'),
        paidDesc: document.getElementById('summary-paid-orders-desc'),
        pending: document.getElementById('summary-pending-orders'),
        pendingDesc: document.getElementById('summary-pending-orders-desc'),
        income: document.getElementById('summary-income'),
        incomeDesc: document.getElementById('summary-income-desc'),
        incomeExtra: document.getElementById('summary-income-extra'),
    };
    const tableBody = document.getElementById('closureTableBody');
    const emptyAlert = document.getElementById('closureTableEmpty');
    const historyBody = document.getElementById('historyTableBody');

    const fetchUrl = container.dataset.fetchUrl;
    const exportUrl = container.dataset.exportUrl;

    if (container.dataset.defaultWarehouse && warehouseSelect) {
        warehouseSelect.value = container.dataset.defaultWarehouse;
    }
    if (container.dataset.defaultDate && dateInput) {
        dateInput.value = container.dataset.defaultDate;
    }

    const setLoading = (isLoading) => {
        if (!generateBtn) {
            return;
        }

        generateBtn.disabled = isLoading;
        generateBtn.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm me-1"></span> Generando...'
            : '<i class="ri-links-line me-1"></i> Generar Cierre';
    };

    const formatCurrency = (value) => {
        const parsed = Number(value || 0);
        return `S/ ${parsed.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    const updateExportLinks = (params) => {
        exportButtons.forEach((btn) => {
            const url = new URL(exportUrl, window.location.origin);
            url.searchParams.set('warehouse', params.warehouse);
            url.searchParams.set('date', params.date);
            btn.href = url.toString();
        });
    };

    const renderSummary = (summary, meta) => {
        if (!summaryElements.total) {
            return;
        }

        summaryElements.total.textContent = summary.raw?.total_orders ?? 0;
        summaryElements.totalDesc.textContent = summary.cards?.[0]?.description ?? '';

        summaryElements.paid.textContent = summary.raw?.paid_orders ?? 0;
        summaryElements.paidDesc.textContent = summary.cards?.[1]?.description ?? '';

        summaryElements.pending.textContent = summary.raw?.pending_orders ?? 0;
        summaryElements.pendingDesc.textContent = summary.cards?.[2]?.description ?? '';

        const incomeValue = summary.raw?.income_total ?? 0;
        summaryElements.income.textContent = formatCurrency(incomeValue);
        summaryElements.incomeDesc.textContent = summary.cards?.[3]?.description ?? '';

        const cash = summary.raw?.income_cash ?? 0;
        const pendingAmount = summary.raw?.pending_amount ?? 0;
        summaryElements.incomeExtra.textContent = `Efectivo: ${formatCurrency(cash)} • Pendiente: ${formatCurrency(pendingAmount)}`;

    };

    const renderTable = (details) => {
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';

        if (!details.length) {
            emptyAlert?.classList.remove('d-none');
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 11;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'No se registraron ventas en efectivo para los filtros seleccionados.';
            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }

        emptyAlert?.classList.add('d-none');

        details.forEach((detail) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${detail.index ?? ''}</td>
                <td>${detail.customer ?? ''}</td>
                <td>${detail.product ?? ''}</td>
                <td>${detail.quantity ?? 0}</td>
                <td>${detail.unit ?? ''}</td>
                <td><span class="${detail.payment_status?.badge ?? 'badge bg-light text-dark'}">${detail.payment_status?.label ?? ''}</span></td>
                <td>${detail.payment_method?.label ?? ''}</td>
                <td>${formatCurrency(detail.total ?? 0)}</td>
                <td>${formatCurrency(detail.amount_paid ?? 0)}</td>
                <td>${formatCurrency(detail.pending ?? 0)}</td>
            `;
            tableBody.appendChild(row);
        });
    };

    const renderHistory = (items) => {
        if (!historyBody) {
            return;
        }

        historyBody.innerHTML = '';

        if (!items.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 7;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'No hay datos para mostrar todavía.';
            row.appendChild(cell);
            historyBody.appendChild(row);
            return;
        }

        items.forEach((item) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.date_display ?? item.date ?? ''}</td>
                <td>${item.warehouse_label ?? item.warehouse ?? ''}</td>
                <td>${item.total_orders ?? 0}</td>
                <td class="text-success fw-semibold">${item.paid_orders ?? 0}</td>
                <td class="text-warning fw-semibold">${item.pending_orders ?? 0}</td>
                <td>${formatCurrency(item.income_total ?? 0)}</td>
                <td>${formatCurrency(item.pending_amount ?? 0)}</td>
            `;
            historyBody.appendChild(row);
        });
    };

    const showError = (message) => {
        const text = message || 'No se pudo obtener el cierre diario. Intenta nuevamente.';
        if (window.Toastify) {
            Toastify({
                text,
                duration: 4000,
                gravity: 'top',
                position: 'right',
                style: { background: '#dc3545' },
            }).showToast();
        } else {
            alert(text);
        }
    };

    const loadClosure = async () => {
        if (!warehouseSelect || !dateInput) {
            return;
        }

        const params = {
            warehouse: warehouseSelect.value,
            date: dateInput.value,
        };

        updateExportLinks(params);
        setLoading(true);

        try {
            const { data } = await axios.get(fetchUrl, { params });
            renderSummary(data.summary, data.meta);
            renderTable(data.details ?? []);
            renderHistory(data.history ?? []);
        } catch (error) {
            console.error(error);
            showError(error?.response?.data?.message);
        } finally {
            setLoading(false);
        }
    };

    if (generateBtn) {
        generateBtn.addEventListener('click', loadClosure);
    }

    warehouseSelect?.addEventListener('change', () => {
        updateExportLinks({ warehouse: warehouseSelect.value, date: dateInput.value });
    });

    dateInput?.addEventListener('change', () => {
        updateExportLinks({ warehouse: warehouseSelect.value, date: dateInput.value });
    });

    updateExportLinks({ warehouse: warehouseSelect?.value ?? '', date: dateInput?.value ?? '' });
    loadClosure();
});
