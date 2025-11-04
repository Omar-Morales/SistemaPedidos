
import axios from 'axios';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

const modalVenta = new bootstrap.Modal(document.getElementById('modalVenta'));
const formVenta = document.getElementById('formVenta');
const tablaDetalle = document.getElementById('detalleVentaBody');
const totalInput = document.getElementById('total_price');
const saleDateInput = document.getElementById('sale_date');
const amountPaidInput = document.getElementById('amount_paid');
const paymentStatusSelect = document.getElementById('payment_status');
const btnAgregarProducto = document.getElementById('addProductRow');
let products = [];
let detalleEditableDT = null;
let isEditingVenta = false;
let editingDetailId = null;
let hiddenDetails = [];

/*
function cargarProductos(callback) {
    axios.get('/products/list')
        .then(response => {
            products = response.data;
            if (typeof callback === 'function') callback();
        })
        .catch(error => {
            console.error('Error al cargar productos:', error);
            Toastify({
                text: 'Error al cargar productos',
                duration: 3000,
                backgroundColor: '#dc3545'
            }).showToast();
        });
}*/

function cargarProductos(callback, includeIds = []) {
    let url = '/products/list';

    //  Filtra solo IDs vlidos numricos
    const validIds = includeIds.filter(id => id !== undefined && id !== null && !isNaN(Number(id)));

    if (validIds.length > 0) {
        const params = new URLSearchParams();
        validIds.forEach(id => params.append('include_id[]', id));
        url += `?${params.toString()}`;
    }

    axios.get(url)
        .then(response => {
            products = response.data;
            if (typeof callback === 'function') callback();
        })
        .catch(error => {
            console.error('Error al cargar productos:', error);
            Toastify({
                text: 'Error al cargar productos',
                duration: 3000,
                backgroundColor: '#dc3545'
            }).showToast();
        });
}

const $status = $('#status');
const $customer_id = $('#customer_id');
const $tipodocumento_id = $('#tipodocumento_id');
const $payment_method = $('#payment_method');
const $delivery_type = $('#delivery_type');
const $warehouse = $('#warehouse');

const paymentStatusText = {
    pending: 'Pendiente',
    to_collect: 'Saldo pendiente',
    change: 'Vuelto pendiente',
    paid: 'Cancelado'
};

const orderStatusText = {
    pending: 'Pendiente',
    in_progress: 'En curso',
    delivered: 'Entregado',
    cancelled: 'Anulado'
};

[$customer_id, $tipodocumento_id, $payment_method, $delivery_type, $warehouse].forEach($el => {
    if (!$el.hasClass('select2-hidden-accessible')) {
        $el.select2({
            dropdownParent: $('#modalVenta'),
            width: '100%',
            placeholder: '',
            allowClear: true,
            theme: 'bootstrap-5'
        });
    }
});

function resetearModalVenta() {
    formVenta.reset();
    $('#venta_id').val('');
    $('#status').val('pending');
    isEditingVenta = false;
    editingDetailId = null;
    hiddenDetails = [];
    $customer_id.val('').trigger('change');
    $tipodocumento_id.val('').trigger('change');
    $payment_method.val('').trigger('change');
    $delivery_type.val('').trigger('change');
    $warehouse.val('').trigger('change');

    if (saleDateInput) {
        saleDateInput.value = new Date().toISOString().slice(0, 10);
    }

    if (totalInput) totalInput.value = '0.00';
    if (amountPaidInput) amountPaidInput.value = '0.00';
    if (paymentStatusSelect) {
        paymentStatusSelect.disabled = false;
        paymentStatusSelect.value = 'pending';
        Array.from(paymentStatusSelect.options).forEach(option => {
            if (!['pending', 'paid'].includes(option.value)) {
                option.remove();
            }
        });
    }

    if (detalleEditableDT) {
        detalleEditableDT.clear().destroy();
        detalleEditableDT = null;
    }

    $('#detalleVentaBody .product-select').each(function () {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
        $(this).off();
    });

    tablaDetalle.innerHTML = '';
    if (btnAgregarProducto) {
        btnAgregarProducto.disabled = false;
    }
}

function getHiddenFields(row) {
    return {
        amount: row?.querySelector('.hidden-amount-paid') || null,
        paymentStatus: row?.querySelector('.hidden-payment-status') || null,
        orderStatus: row?.querySelector('.hidden-order-status') || null,
        difference: row?.querySelector('.hidden-difference') || null,
        warehouse: row?.querySelector('.hidden-warehouse') || null,
        deliveryType: row?.querySelector('.hidden-delivery-type') || null,
        paymentMethod: row?.querySelector('.hidden-payment-method') || null,
        detailId: row?.querySelector('.hidden-detail-id') || null,
    };
}

function syncGlobalSelectorsWithRow(row) {
    if (!isEditingVenta || !row) return;
    const hidden = getHiddenFields(row);
    if (!hidden) return;

    if (hidden.warehouse && document.getElementById('warehouse')) {
        const value = hidden.warehouse.value || 'curva';
        if ($('#warehouse').val() !== value) {
            $('#warehouse').val(value).trigger('change');
        }
    }

    if (hidden.deliveryType && document.getElementById('delivery_type')) {
        const value = hidden.deliveryType.value || 'pickup';
        if ($('#delivery_type').val() !== value) {
            $('#delivery_type').val(value).trigger('change');
        }
    }

    if (hidden.paymentMethod && document.getElementById('payment_method')) {
        const value = hidden.paymentMethod.value || '';
        if ($('#payment_method').val() !== value) {
            $('#payment_method').val(value).trigger('change');
        }
    }
}

/*************************para el seelct customer - cliente*************************************/
let customersCache = null;

function cargarCustomersEnSelect(idSeleccionado = null, callback = null) {
  const $select = $('#customer_id');

  $select.empty().append(new Option('Cargando clientes...', '', true, true)).trigger('change');

  let url = '/customers/select';
  if (idSeleccionado) {
    url += '?include_id=' + encodeURIComponent(idSeleccionado);
  }

  axios.get(url)
    .then(response => {
      const customers = response.data;
      customersCache = customers;
      $select.empty().append(new Option('-- Seleccione --', '', true, false));
      customers.forEach(c => {
        const isSelected = idSeleccionado == c.id;
        $select.append(new Option(c.text, c.id, false, isSelected));
      });

      $select.trigger('change');

      if (callback) callback();
    })
    .catch(error => {
      console.error('Error al cargar clientes:', error);

      $select.empty().append(new Option('-- Error al cargar --', '', true, true)).trigger('change');

      Toastify({
        text: "Error al cargar clientes",
        duration: 3000,
        gravity: "top",
        position: "right",
        backgroundColor: "#dc3545"
      }).showToast();
    });
}
/**************************************************************/
$('#btnCrearVenta').on('click', async (e) => {
    e.currentTarget.blur();
    $('#modalVentaLabel').text('Nueva Venta');
    resetearModalVenta();

    // Mostrar overlay de carga
    $('#modalVenta .modal-content').append('<div id="cargandoOverlay" class="modal-loading-overlay"></div>');

    try {
        await new Promise(resolve => cargarProductos(resolve));
        await new Promise(resolve => cargarCustomersEnSelect(null, resolve));
        inicializarDataTableEditable();
        modalVenta.show();
    } catch (error) {
        console.error('Error al preparar modal de venta:', error);
        Toastify({
            text: 'Error al cargar datos para la venta',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            style: { background: '#dc3545' }
        }).showToast();
    } finally {
        $('#cargandoOverlay').remove();
    }
});

$('#modalVenta').on('hidden.bs.modal', function () {
    resetearModalVenta();
    document.activeElement.blur();
});

const table = $('#ventasTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: '/ventas/data',
    columns: [
        { data: 'id', name: 'detalle_ventas.sale_id' },
        { data: 'fecha', name: 'ventas.sale_date' },
        { data: 'usuario', name: 'users.name' },
        { data: 'cliente', name: 'customers.name' },
        { data: 'producto', name: 'products.name' },
        { data: 'cantidad', name: 'detalle_ventas.quantity' },
        { data: 'unidad', name: 'detalle_ventas.unit' },
        { data: 'almacen', name: 'ventas.warehouse' },
        { data: 'total', name: 'detalle_ventas.subtotal' },
        { data: 'monto_pagado', name: 'detalle_ventas.amount_paid' },
        { data: 'diferencia', name: 'detalle_ventas.difference' },
        { data: 'metodo_pago', name: 'ventas.payment_method' },
        { data: 'estado_pago', name: 'detalle_ventas.payment_status' },
        { data: 'estado_pedido', name: 'detalle_ventas.status' },
        { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
    ],
    language: { url: '/assets/js/es-ES.json' },
    responsive: true,
    autoWidth: false,
    pageLength: 10,
    order: [[0, 'desc']],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'colvis',
            text: 'Seleccionar Columnas',
            className: 'btn btn-info',
            postfixButtons: ['colvisRestore']
        }
    ]
});

// Personalizar los estilos al cambiar visibilidad de columnas

        function updateColvisStyles() {
        $('.dt-button-collection .dt-button').each(function () {
            const isActive = $(this).hasClass('active') || $(this).hasClass('dt-button-active');

            if (isActive) {
            // Agregar check si no existe
            if ($(this).find('.checkmark').length === 0) {
                $(this).prepend('<span class="checkmark"></span>');
            }
            } else {
            // Remover check si existe
            $(this).find('.checkmark').remove();
            }
        });
        }

        // Evento cuando se hace alguna accin con los botones (activar/desactivar columna)
    table.on('buttons-action', function () {
    setTimeout(updateColvisStyles, 10);
    });

    // Evento para cuando abren el men de columnas visibles
    $(document).on('click', '.buttons-colvis', function () {
    setTimeout(updateColvisStyles, 50);
    });

    // Opcional: cuando se carga la pagina
    $(document).ready(function () {
    setTimeout(updateColvisStyles, 100);

    $(window).on('scroll', function () {
        const $menu = $('.dt-button-collection:visible');
        if (!$menu.length) return;

        const windowWidth = document.documentElement.clientWidth;
        console.log('window.innerWidth:', window.innerWidth, 'clientWidth:', windowWidth);

        let $nav;
        if (windowWidth >= 1024 && $('.app-menu').is(':visible')) {
            $nav = $('.app-menu');
            console.log('Usando men lateral (.app-menu)');
        } else {
            $nav = $('#page-topbar');
            console.log('Usando header (#page-topbar)');
        }

        if (!$nav.length) return;

        const menuTop = $menu.offset().top;
        const navBottom = $nav.offset().top + $nav.outerHeight();
        const tolerance = 2;

        console.log('menuTop:', menuTop, 'navBottom + tolerance:', navBottom + tolerance);

        if (menuTop < navBottom + tolerance) {
            const $toggleBtn = $('.buttons-colvis');

            $menu.css('z-index', 50);

            $menu.fadeOut(200, function () {
                $(this).css('z-index', 1050);
            });

            $('body').trigger('click');

            $toggleBtn.removeClass('active dt-btn-split-drop-active');
            $toggleBtn.attr('aria-expanded', 'false');
            $toggleBtn.blur();

            console.log('Men ocultado');
        }
    });

    });

function resolveVentaStatusFromDetails(statuses = []) {
    const filtered = statuses.filter(Boolean);
    if (filtered.length === 0) {
        return 'pending';
    }

    if (filtered.every(status => status === 'cancelled')) {
        return 'cancelled';
    }

    if (filtered.every(status => status === 'delivered')) {
        return 'delivered';
    }

    if (filtered.includes('delivered') || filtered.includes('in_progress')) {
        return 'in_progress';
    }

    return 'pending';
}

function computePaymentStatus(total, amountPaid) {
    if (amountPaid <= 0) {
        return 'pending';
    }

    if (amountPaid < total) {
        return 'to_collect';
    }

    if (amountPaid > total) {
        return 'change';
    }

    return 'paid';
}

function calcularTotal() {
    if (!detalleEditableDT) {
        if (totalInput) totalInput.value = '0.00';
        if (amountPaidInput) amountPaidInput.value = '0.00';
        return;
    }

    const table = detalleEditableDT;
    let total = 0;
    let amountPaid = 0;
    const statuses = [];
    const statusHiddenInput = document.getElementById('status');
    const globalOrderStatus = statusHiddenInput ? statusHiddenInput.value || 'pending' : 'pending';
    const globalPaymentStatus = paymentStatusSelect ? paymentStatusSelect.value || 'pending' : 'pending';

    // Recorre todas las filas (incluso las ocultas en otras paginas)
    table.rows().every(function () {
        const row = this.node();
        const subtotalInput = row?.querySelector('.subtotal-input');
        const subtotal = subtotalInput ? parseFloat(subtotalInput.value) || 0 : 0;
        const hidden = getHiddenFields(row);
        let amount = hidden.amount ? parseFloat(hidden.amount.value) || 0 : 0;
        let status = hidden.orderStatus ? hidden.orderStatus.value || 'pending' : 'pending';

        if (!isEditingVenta) {
            const enforcedAmount = globalPaymentStatus === 'paid' ? subtotal : 0;
            amount = enforcedAmount;
            if (hidden.amount) {
                hidden.amount.value = enforcedAmount.toFixed(2);
            }
            if (hidden.paymentStatus) {
                hidden.paymentStatus.value = globalPaymentStatus === 'paid' ? 'paid' : 'pending';
            }
            if (hidden.orderStatus) {
                hidden.orderStatus.value = globalOrderStatus;
                status = globalOrderStatus;
            } else {
                status = globalOrderStatus;
            }
        }

        const difference = subtotal - amount;
        if (hidden.difference) {
            hidden.difference.value = difference.toFixed(2);
        }

        if (status !== 'cancelled') {
            total += subtotal;
            amountPaid += amount;
        }
        statuses.push(status);
    });

    hiddenDetails.forEach(detail => {
        const subtotal = parseFloat(detail.subtotal) || 0;
        let amount = parseFloat(detail.amount_paid) || 0;
        let status = detail.status || 'pending';

        if (!isEditingVenta) {
            const enforcedAmount = globalPaymentStatus === 'paid' ? subtotal : 0;
            amount = enforcedAmount;
            detail.amount_paid = enforcedAmount.toFixed(2);
            detail.payment_status = globalPaymentStatus === 'paid' ? 'paid' : 'pending';
            detail.status = globalOrderStatus;
            status = detail.status;
            detail.difference = subtotal - enforcedAmount;
        }

        if (typeof detail.difference === 'undefined') {
            detail.difference = subtotal - amount;
        }

        if ((detail.status || 'pending') !== 'cancelled') {
            total += subtotal;
            amountPaid += amount;
        }
        statuses.push(status);
    });

    if (totalInput) totalInput.value = total.toFixed(2);
    if (amountPaidInput) amountPaidInput.value = amountPaid.toFixed(2);

    const ventaStatus = resolveVentaStatusFromDetails(statuses);
    $('#status').val(ventaStatus);

    const paymentStatus = computePaymentStatus(total, amountPaid);
    if (paymentStatusSelect) {
        if (isEditingVenta) {
            if (!['pending', 'paid'].includes(paymentStatusSelect.value)) {
                paymentStatusSelect.value = 'pending';
            }
        } else {
            if (!['pending', 'paid'].includes(paymentStatus)) {
                let option = paymentStatusSelect.querySelector(`option[value="${paymentStatus}"]`);
                if (!option) {
                    option = document.createElement('option');
                    option.value = paymentStatus;
                    option.textContent = paymentStatusText[paymentStatus] || paymentStatus;
                    paymentStatusSelect.appendChild(option);
                }
            }
            paymentStatusSelect.value = paymentStatus;
        }
    }
}

const detailFieldSyncMap = {
    warehouse: { hiddenKey: 'warehouse', detailKey: 'warehouse', defaultValue: 'curva' },
    delivery_type: { hiddenKey: 'deliveryType', detailKey: 'delivery_type', defaultValue: 'pickup' },
    payment_method: { hiddenKey: 'paymentMethod', detailKey: 'payment_method', defaultValue: '' },
};

function applyGlobalSelection(field, rawValue) {
    const mapping = detailFieldSyncMap[field];
    if (!mapping) return;
    const value = (rawValue ?? mapping.defaultValue).toString();

    if (detalleEditableDT) {
        detalleEditableDT.rows().every(function () {
            const hidden = getHiddenFields(this.node());
            if (!hidden) return;
            const input = hidden[mapping.hiddenKey];
            if (input) {
                input.value = value;
            }
        });
    }

    if (!isEditingVenta) {
        hiddenDetails = hiddenDetails.map(detail => ({
            ...detail,
            [mapping.detailKey]: value,
        }));
    }
}

if (paymentStatusSelect) {
    paymentStatusSelect.addEventListener('change', () => {
        if (!detalleEditableDT) {
            calcularTotal();
            return;
        }

        const selectedStatus = paymentStatusSelect.value || 'pending';
        const enforcedStatus = selectedStatus === 'paid' ? 'paid' : 'pending';

        if (isEditingVenta) {
            detalleEditableDT.rows().every(function () {
                const row = this.node();
                const hidden = getHiddenFields(row);
                if (!hidden) return;

                if (hidden.paymentStatus) {
                    hidden.paymentStatus.value = enforcedStatus;
                }

                const subtotalInput = row?.querySelector('.subtotal-input');
                const subtotal = subtotalInput ? parseFloat(subtotalInput.value) || 0 : 0;
                const amount = enforcedStatus === 'paid' ? subtotal : 0;

                if (hidden.amount) {
                    hidden.amount.value = amount.toFixed(2);
                }
                if (hidden.difference) {
                    hidden.difference.value = (subtotal - amount).toFixed(2);
                }
            });
        } else {
            detalleEditableDT.rows().every(function () {
                const row = this.node();
                const subtotalInput = row?.querySelector('.subtotal-input');
                const subtotal = subtotalInput ? parseFloat(subtotalInput.value) || 0 : 0;
                const hidden = getHiddenFields(row);

                if (hidden.paymentStatus) {
                    hidden.paymentStatus.value = enforcedStatus;
                }

                if (hidden.amount) {
                    const amount = enforcedStatus === 'paid' ? subtotal : 0;
                    hidden.amount.value = amount.toFixed(2);
                    if (hidden.difference) {
                        hidden.difference.value = (subtotal - amount).toFixed(2);
                    }
                }
            });

            hiddenDetails = hiddenDetails.map(detail => {
                const subtotal = parseFloat(detail.subtotal) || 0;
                const amount = enforcedStatus === 'paid' ? subtotal : 0;
                return {
                    ...detail,
                    payment_status: enforcedStatus,
                    amount_paid: amount.toFixed(2),
                    difference: (subtotal - amount).toFixed(2),
                };
            });
        }

        calcularTotal();
    });
}

$('#warehouse').on('change', function () {
    applyGlobalSelection('warehouse', this.value || 'curva');
    calcularTotal();
});

$('#delivery_type').on('change', function () {
    applyGlobalSelection('delivery_type', this.value || 'pickup');
    calcularTotal();
});

$('#payment_method').on('change', function () {
    applyGlobalSelection('payment_method', this.value || '');
});

function validarFila(row) {
    const pid = row.querySelector('.product-select').value;
    const qty = parseFloat(row.querySelector('.quantity-input').value);
    const unitValue = parseFloat(row.querySelector('.unit-input').value);
    const subtotalValue = parseFloat(row.querySelector('.subtotal-input').value);
    return pid && qty > 0 && Number.isFinite(unitValue) && unitValue >= 0 && subtotalValue >= 0;
}

function productoYaExiste(productId, currentRow = null) {
    if (!detalleEditableDT) return false;
    const table = detalleEditableDT;
    let existe = false;

    table.rows().every(function () {
        const row = this.node();
        const select = row.querySelector('.product-select');
        if (!select) return;

        if (select.value === productId && (!currentRow || row !== currentRow)) {
            existe = true;
            return false; // rompe el loop de rows
        }
    });

    if (existe) {
        return true;
    }

    return hiddenDetails.some(detail => String(detail.product_id) === String(productId));
}

function agregarFilaProducto(data = {}) {
    if (!detalleEditableDT) {
        inicializarDataTableEditable();
    }

    const selectedId = data.product_id || '';
    const quantity = data.quantity !== undefined ? data.quantity : '';
    const unitValue = data.unit !== undefined && data.unit !== null && data.unit !== ''
        ? Number(data.unit)
        : '';
    const subtotalValue = data.subtotal !== undefined && data.subtotal !== null && data.subtotal !== ''
        ? parseFloat(data.subtotal)
        : '';
    const subtotalNumber = subtotalValue === '' ? 0 : subtotalValue;
    const subtotal = subtotalValue === '' ? '' : subtotalNumber.toFixed(2);

    const defaultPaymentStatus = paymentStatusSelect?.value === 'paid' ? 'paid' : 'pending';
    const paymentStatus = data.payment_status ?? defaultPaymentStatus;
    const defaultAmount = paymentStatus === 'paid' ? subtotalNumber : 0;
    const amountValue = data.amount_paid !== undefined && data.amount_paid !== null
        ? parseFloat(data.amount_paid)
        : defaultAmount;

    const defaultOrderStatus = $('#status').val() || 'pending';
    const orderStatus = data.status ?? defaultOrderStatus;

    const warehouseValue = String(data.warehouse ?? ($('#warehouse').val() || 'curva'));
    const deliveryValue = String(data.delivery_type ?? ($('#delivery_type').val() || 'pickup'));
    const paymentMethodValue = String(data.payment_method ?? ($('#payment_method').val() || ''));

    const differenceValue = data.difference !== undefined && data.difference !== null
        ? parseFloat(data.difference)
        : subtotalNumber - amountValue;
    const differenceFixed = Number.isFinite(differenceValue) ? differenceValue.toFixed(2) : '0.00';

    const options = products
        .map(p => `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${p.name}</option>`)
        .join('');

    const dt = detalleEditableDT;
    dt.row.add([
        `<select class="form-select product-select" required>
            <option value="">Seleccione un producto</option>
            ${options}
         </select>`,
        `<input type="number" class="form-control quantity-input" min="1" value="${quantity}" required>`,
        `<input type="number" class="form-control unit-input" min="0" step="0.01" value="${unitValue === '' ? '' : unitValue}" required>`,
        `<input type="number" class="form-control subtotal-input" step="0.01" min="0" value="${subtotal}" required>`,
        `<div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-danger remove-row">Eliminar</button>
            <input type="hidden" class="hidden-amount-paid" value="${amountValue.toFixed(2)}">
            <input type="hidden" class="hidden-payment-status" value="${paymentStatus}">
            <input type="hidden" class="hidden-order-status" value="${orderStatus}">
            <input type="hidden" class="hidden-difference" value="${differenceFixed}">
            <input type="hidden" class="hidden-warehouse" value="${warehouseValue}">
            <input type="hidden" class="hidden-delivery-type" value="${deliveryValue}">
            <input type="hidden" class="hidden-payment-method" value="${paymentMethodValue}">
            <input type="hidden" class="hidden-detail-id" value="${data.id ?? ''}">
        </div>`
    ]).draw(false);

    const lastRowNode = dt.row(':last').node();
    const $select = $(lastRowNode).find('.product-select');

    if (!$select.hasClass('select2-initialized')) {
        $select
            .addClass('select2-initialized')
            .select2({
                dropdownParent: $('#modalVenta .modal-content'),
                width: '100%',
                allowClear: true,
                theme: 'bootstrap-5',
                placeholder: 'Seleccione un producto',
                containerCssClass: 'select2-in-modal'
            });
    }

    if (isEditingVenta) {
        syncGlobalSelectorsWithRow(lastRowNode);
    }

    calcularTotal();
}

btnAgregarProducto?.addEventListener('click', agregarFilaProducto);

$(document).on('input', '.subtotal-input', function () {
    calcularTotal();
});

$(document).on('input', '.quantity-input', function () {
    calcularTotal();
});

$(document).on('click', '.remove-row', function () {
    if (!detalleEditableDT) return;
    const table = detalleEditableDT;
    const currentPage = table.page(); // pagina actual
    const currentRows = table.rows({ page: 'current' }).count(); // filas actuales visibles

    // Eliminar la fila
    table.row($(this).closest('tr')).remove();

    // Si era la unica fila visible y no estas en la pagina 0, retrocede
    if (currentRows === 1 && currentPage > 0) {
        table.page(currentPage - 1).draw('page');
    } else {
        table.draw(false); // Redibuja manteniendo la pagina
    }

    calcularTotal();
});

formVenta.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!detalleEditableDT) {
        Swal.fire('Error', 'No hay productos en la venta.', 'error');
        return;
    }

    const tableDT = detalleEditableDT;
    const detalle = [];
    let valido = true;
    const idsDuplicados = new Set();
    const isDetailEdit = Boolean(editingDetailId);

    tableDT.rows().every(function () {
        const row = this.node();

        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.quantity-input');
        const unitInput = row.querySelector('.unit-input');
        const subtotalInput = row.querySelector('.subtotal-input');
        const hiddenFields = getHiddenFields(row);

        const pid = select?.value;
        const qty = parseFloat(qtyInput?.value);
        const unit = unitInput?.value.trim() || '';
        const subtotal = parseFloat(subtotalInput?.value);
        const amountPaid = hiddenFields.amount ? parseFloat(hiddenFields.amount.value) : 0;
        const paymentStatus = hiddenFields.paymentStatus?.value || 'pending';
        const orderStatus = hiddenFields.orderStatus?.value || 'pending';
        const difference = hiddenFields.difference ? parseFloat(hiddenFields.difference.value) : subtotal - amountPaid;
        const warehouseValue = hiddenFields.warehouse ? (hiddenFields.warehouse.value || 'curva') : ($('#warehouse').val() || 'curva');
        const deliveryValue = hiddenFields.deliveryType ? (hiddenFields.deliveryType.value || 'pickup') : ($('#delivery_type').val() || 'pickup');
        const paymentMethodValue = hiddenFields.paymentMethod ? (hiddenFields.paymentMethod.value || '') : ($('#payment_method').val() || '');

        if (!pid || qty <= 0 || unit === '' || subtotal < 0 || Number.isNaN(subtotal) || Number.isNaN(amountPaid) || amountPaid < 0) {
            valido = false;
            return;
        }

        if (idsDuplicados.has(pid)) {
            valido = false;
            return;
        }

        idsDuplicados.add(pid);

        detalle.push({
            product_id: pid,
            quantity: qty,
            unit,
            subtotal,
            amount_paid: amountPaid,
            payment_status: paymentStatus,
            status: orderStatus,
            difference: Number.isFinite(difference) ? difference : subtotal - amountPaid,
            warehouse: warehouseValue,
            delivery_type: deliveryValue,
            payment_method: paymentMethodValue,
        });
    });

    if (!isDetailEdit) {
        hiddenDetails.forEach(detail => {
            const subtotalHidden = parseFloat(detail.subtotal) || 0;
            const amountHidden = parseFloat(detail.amount_paid) || 0;
            const diffHidden = detail.difference !== undefined ? parseFloat(detail.difference) : subtotalHidden - amountHidden;

            detalle.push({
                product_id: detail.product_id,
                quantity: detail.quantity,
                unit: detail.unit,
                subtotal: subtotalHidden,
                amount_paid: amountHidden,
                payment_status: detail.payment_status,
                status: detail.status,
                difference: Number.isFinite(diffHidden) ? diffHidden : subtotalHidden - amountHidden,
                warehouse: detail.warehouse ?? ($('#warehouse').val() || 'curva'),
                delivery_type: detail.delivery_type ?? ($('#delivery_type').val() || 'pickup'),
                payment_method: detail.payment_method ?? ($('#payment_method').val() || ''),
            });
        });
    }

    if (!valido || detalle.length === 0) {
        Swal.fire('Error', 'Verifique que cada producto este completo y sin duplicados.', 'error');
        return;
    }

    Swal.fire({
        title: 'Guardar venta?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Si, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const formData = new FormData(formVenta);
        const ventaId = formData.get('venta_id');

        if (isDetailEdit) {
            if (detalle.length !== 1) {
                Swal.fire('Error', 'No se pudo identificar el detalle a editar.', 'error');
                return;
            }

            const detailPayload = detalle[0];
            const payload = {
                customer_id: formData.get('customer_id'),
                tipodocumento_id: formData.get('tipodocumento_id'),
                sale_date: formData.get('sale_date'),
                payment_method: formData.get('payment_method'),
                delivery_type: formData.get('delivery_type'),
                warehouse: formData.get('warehouse'),
                detail: detailPayload,
            };

            axios.put(`/ventas/detalles/${editingDetailId}`, payload)
                .then(res => {
                    document.activeElement.blur();
                    modalVenta.hide();
                    resetearModalVenta();
                    hiddenDetails = [];
                    editingDetailId = null;
                    table.ajax.reload();
                    Toastify({
                        text: res.data.message || 'Detalle actualizado',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        style: { background: '#28a745' }
                    }).showToast();
                })
                .catch(err => {
                    console.error(err);
                    Toastify({
                        text: err.response?.data?.message || 'Error al actualizar el detalle.',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        style: { background: '#dc3545' }
                    }).showToast();
                });

            return;
        }

        const url = ventaId ? `/ventas/${ventaId}` : '/ventas';
        if (ventaId) formData.append('_method', 'PUT');
        formData.append('details', JSON.stringify(detalle));

        axios.post(url, formData)
            .then(res => {
                document.activeElement.blur();
                modalVenta.hide();
                resetearModalVenta();
                table.ajax.reload();
                Toastify({
                    text: res.data.message || 'Venta registrada',
                    duration: 3000,
                    gravity: 'top',
                    position: 'right',
                    style: { background: '#28a745' }
                }).showToast();
            })
            .catch(err => {
                console.error(err);
                Toastify({
                    text: err.response?.data?.message || 'Error al guardar la venta.',
                    duration: 3000,
                    gravity: 'top',
                    position: 'right',
                    style: { background: '#dc3545' }
                }).showToast();
            });
    });
});

$(document).on('click', '.edit-btn', async function () {
    const id = $(this).data('id');
    const targetDetail = $(this).data('detail-id');

    resetearModalVenta();
    isEditingVenta = true;
    editingDetailId = typeof targetDetail !== 'undefined' ? String(targetDetail) : null;
    hiddenDetails = [];
    if (btnAgregarProducto) {
        btnAgregarProducto.disabled = true;
    }

    // Overlay de carga
    $('#modalVenta .modal-content').append('<div id="cargandoOverlay" class="modal-loading-overlay"></div>');

    try {
        // Obtener la venta y los clientes (incluso si estn inactivos)
        const { data } = await axios.get(`/ventas/${id}`);
        const venta = data.venta;
        const clientes = data.clientes;

        // Cargar productos relevantes de la venta
        const productosEnVenta = (venta.detalle || []).map(item => item.product_id);
        await new Promise(resolve => cargarProductos(resolve, productosEnVenta));

        // Llenar select de cliente (aunque est inactivo)
        const $clienteSelect = $('#customer_id');
        $clienteSelect.empty();
        clientes.forEach(c => {
            const selected = c.id === venta.cliente ? 'selected' : '';
            $clienteSelect.append(`<option value="${c.id}" ${selected}>${c.text}</option>`);
        });
        $clienteSelect.trigger('change');

        // Llenar otros campos
        $('#modalVentaLabel').text('Editar Venta');
        $('#venta_id').val(venta.id);
        $('#tipodocumento_id').val(venta.tipo_documento).trigger('change');
        $('#sale_date').val(venta.fecha);
        $('#delivery_type').val(venta.delivery_type).trigger('change');
        $('#warehouse').val(venta.warehouse).trigger('change');
        $('#status').val(venta.estado ?? 'pending');
        $('#payment_method').val(venta.payment_method).trigger('change');
        $('#total_price').val(parseFloat(venta.total).toFixed(2));
        if (amountPaidInput) amountPaidInput.value = parseFloat(venta.amount_paid).toFixed(2);
        if (paymentStatusSelect) {
            paymentStatusSelect.disabled = false;
            const currentStatus = venta.payment_status || 'pending';

            if (!['pending', 'paid'].includes(currentStatus)) {
                let option = paymentStatusSelect.querySelector(`option[value="${currentStatus}"]`);
                if (!option) {
                    option = document.createElement('option');
                    option.value = currentStatus;
                    option.textContent = paymentStatusText[currentStatus] || currentStatus;
                    paymentStatusSelect.appendChild(option);
                }
            }

            paymentStatusSelect.value = currentStatus;
        }

        // Llenar tabla de detalle
        tablaDetalle.innerHTML = '';
        inicializarDataTableEditable();

        let selectedDetailStatus = 'pending';

        if (Array.isArray(venta.detalle)) {
            let displayedCount = 0;

            venta.detalle.forEach(item => {
                const paymentStatusNormalized = item.payment_status === 'paid' ? 'paid' : 'pending';

                const detallePayload = {
                    product_id: item.product_id,
                    quantity: item.quantity,
                    unit: item.unit,
                    subtotal: item.subtotal,
                    amount_paid: item.amount_paid,
                    payment_status: paymentStatusNormalized,
                    status: item.status,
                    difference: item.difference ?? (item.subtotal - item.amount_paid),
                    warehouse: item.warehouse,
                    delivery_type: item.delivery_type,
                    payment_method: item.payment_method,
                    id: item.id,
                };

                if (editingDetailId && String(item.id) !== editingDetailId) {
                    hiddenDetails.push(detallePayload);
                    return;
                }

                agregarFilaProducto(detallePayload);
                displayedCount += 1;

                selectedDetailStatus = paymentStatusNormalized;
            });

            if (editingDetailId && displayedCount === 0 && hiddenDetails.length > 0) {
                const fallbackDetail = hiddenDetails.shift();
                agregarFilaProducto(fallbackDetail);
                selectedDetailStatus = fallbackDetail.payment_status === 'paid' ? 'paid' : 'pending';
            }

            calcularTotal();
        } else {
            totalInput.value = parseFloat(venta.total).toFixed(2);
        }

        if (paymentStatusSelect) {
            paymentStatusSelect.disabled = false;
            paymentStatusSelect.value = selectedDetailStatus;
            paymentStatusSelect.dispatchEvent(new Event('change'));
        }

        // Mostrar modal
        document.activeElement.blur();
        modalVenta.show();
    } catch (error) {
        console.error('Error al obtener la venta:', error);
        Toastify({
            text: 'No se pudo cargar la venta',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            backgroundColor: '#dc3545'
        }).showToast();
    } finally {
        $('#cargandoOverlay').remove();
    }
});


$(document).on('click', '.delete-btn', function () {
    const saleId = $(this).data('id');
    const detailId = $(this).data('detail-id');
    const isDetail = Boolean(detailId);

    Swal.fire({
        title: isDetail ? 'Eliminar producto?' : 'estas seguro?',
        text: isDetail
            ? 'Se remover este producto de la venta. No podrs revertir esta accin.'
            : 'No podrs revertir esta accin.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'S, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const url = isDetail ? `/ventas/detalles/${detailId}` : `/ventas/${saleId}`;
        const successMessage = isDetail ? 'Producto eliminado' : 'Venta eliminada';
        const errorMessage = isDetail ? 'No se pudo eliminar el producto' : 'No se pudo eliminar la venta';

        axios.delete(url)
            .then(response => {
                table.ajax.reload();

                Toastify({
                    text: response.data.message || successMessage,
                    duration: 3000,
                    gravity: 'top',
                    position: 'right',
                    style: { background: '#28a745' }
                }).showToast();
            })
            .catch(error => {
                console.error(error);
                Toastify({
                    text: error.response?.data?.message || errorMessage,
                    duration: 3000,
                    gravity: 'top',
                    position: 'right',
                    style: { background: '#dc3545' }
                }).showToast();
            });
    });
});

$(document).on('click', '.ver-detalle-btn', function () {
    const id = $(this).data('id');
    axios.get(`/ventas/${id}/detalle`)
        .then(response => {
            const productos = response.data.detalle;
            const tbody = document.getElementById('detalleVentaBodydos');
            tbody.innerHTML = '';

            productos.forEach(item => {
                const unitLabel = String(item.unit ?? '');
                const subtotal = parseFloat(item.subtotal ?? 0);
                const amountPaid = parseFloat(item.amount_paid ?? 0);
                const difference = parseFloat(item.difference ?? (subtotal - amountPaid));
                const paymentLabel = paymentStatusText[item.payment_status] ?? item.payment_status ?? '-';
                const orderLabel = orderStatusText[item.status] ?? item.status ?? '-';
                const diffFormatted = Math.abs(difference).toFixed(2);
                const diffHtml = difference < 0
                    ? `<span class="text-danger">- S/ ${diffFormatted}</span>`
                    : `S/ ${diffFormatted}`;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>${unitLabel}</td>
                    <td>S/ ${subtotal.toFixed(2)}</td>
                    <td>S/ ${amountPaid.toFixed(2)}</td>
                    <td>${diffHtml}</td>
                    <td>${paymentLabel}</td>
                    <td>${orderLabel}</td>
                `;
                tbody.appendChild(row);
            });

            if ($.fn.DataTable.isDataTable('#detalleVentaTable')) {
                $('#detalleVentaTable').DataTable().destroy();
            }

            $('#detalleVentaTable').DataTable({
                paging: true,
                pageLength: 5,
                lengthChange: false,
                searching: false,
                info: false,
                ordering: false,
                responsive: true,
                language: { url: '/assets/js/es-ES.json' }
            });

            document.activeElement.blur();
            $('#modalDetalleVenta').modal('show');
        })
        .catch(error => {
            console.error('Error al obtener detalle de venta:', error);
            Toastify({
                text: 'No se pudo cargar el detalle',
                duration: 3000,
                gravity: 'top',
                position: 'right',
                style: { background: '#dc3545' }
            }).showToast();
        });
});


function inicializarDataTableEditable() {
    if (detalleEditableDT) {
        detalleEditableDT.clear().destroy();
    }

    detalleEditableDT = $('#detalleVentaTableEditable').DataTable({
        paging: true,
        pageLength: 5,
        lengthChange: false,
        searching: false,
        info: false,
        ordering: false,
        responsive: true,
        language: { url: '/assets/js/es-ES.json' },
        columnDefs: [
            { targets: -1, orderable: false }
        ]
    });
}

$('#detalleVentaTableEditable').on('page.dt', function () {
    calcularTotal();
});

$(document).on('select2:select', '.product-select', function (e) {
    const $select = $(this);
    const selectedProductId = $select.val();
    const currentRow = $select.closest('tr')[0];

    if (productoYaExiste(selectedProductId, currentRow)) {
        Toastify({
            text: 'Este producto ya est seleccionado en otra fila.',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            style: { background: '#dc3545' }
        }).showToast();

        $select.val(null).trigger('change.select2');
    }
});


$(document).on('select2:opening', '.product-select', function () {
    const $select = $(this);
    const currentVal = $select.val(); // mantener el valor actual
    const currentRow = $select.closest('tr')[0];

    const seleccionados = new Set();

    // Usar DataTable API para recorrer todas las filas
    const table = detalleEditableDT;
    if (!table) {
        $select.html('<option value="">Seleccione un producto</option>');
        return;
    }

    table.rows().every(function () {
        const row = this.node();
        const select = $(row).find('.product-select');
        const val = select.val();

        if (val && row !== currentRow) {
            seleccionados.add(String(val));
        }
    });

    // Filtrar productos disponibles
    const opciones = products
        .filter(p => !seleccionados.has(String(p.id)) || String(p.id) === currentVal)
        .map(p => `<option value="${p.id}" ${String(p.id) === currentVal ? 'selected' : ''}>${p.name}</option>`);

    $select.html(`<option value="">Seleccione un producto</option>${opciones.join('')}`);
});


$('#modalDetalleVenta').on('hidden.bs.modal', function () {
    if ($.fn.DataTable.isDataTable('#detalleVentaTable')) {
        $('#detalleVentaTable').DataTable().clear().destroy();
    }
    document.getElementById('detalleVentaBodydos').innerHTML = '';
});










