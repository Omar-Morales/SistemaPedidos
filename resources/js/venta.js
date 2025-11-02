
import axios from 'axios';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

const modalVenta = new bootstrap.Modal(document.getElementById('modalVenta'));
const formVenta = document.getElementById('formVenta');
const tablaDetalle = document.getElementById('detalleVentaBody');
const totalInput = document.getElementById('total_price');
const saleDateInput = document.getElementById('sale_date');
const amountPaidInput = document.getElementById('amount_paid');
const paymentStatusHidden = document.getElementById('payment_status');
const paymentStatusLabel = document.getElementById('payment_status_label');
const btnAgregarProducto = document.getElementById('addProductRow');
let products = [];

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

const paymentStatusLabels = {
    pending: 'Pendiente',
    to_collect: 'Saldo pendiente',
    change: 'Vuelto pendiente',
    paid: 'Cancelado'
};

[$status, $customer_id, $tipodocumento_id, $payment_method, $delivery_type, $warehouse].forEach($el => {
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

function actualizarEstadoPago() {
    if (!paymentStatusHidden) return;

    const total = parseFloat(totalInput?.value) || 0;
    const amount = parseFloat(amountPaidInput?.value) || 0;
    let statusCode = 'pending';

    if (amount <= 0) {
        statusCode = 'pending';
    } else if (amount < total) {
        statusCode = 'to_collect';
    } else if (amount > total) {
        statusCode = 'change';
    } else {
        statusCode = 'paid';
    }

    paymentStatusHidden.value = statusCode;
    if (paymentStatusLabel) {
        paymentStatusLabel.value = paymentStatusLabels[statusCode] || statusCode;
    }
}


function resetearModalVenta() {
    formVenta.reset();
    $('#venta_id').val('');
    $status.val('').trigger('change');
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
    if (paymentStatusHidden) paymentStatusHidden.value = 'pending';
    if (paymentStatusLabel) paymentStatusLabel.value = paymentStatusLabels.pending;

    if ($.fn.DataTable.isDataTable('#detalleVentaTableEditable')) {
        $('#detalleVentaTableEditable').DataTable().clear().destroy();
    }

    //  Eliminar select2 y eventos
    $('#detalleVentaBody .product-select').each(function () {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
        $(this).off();
    });

    //  Limpiar el tbody de filas
    tablaDetalle.innerHTML = '';
    actualizarEstadoPago();
}

if (amountPaidInput) {
    amountPaidInput.addEventListener('input', actualizarEstadoPago);
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
        { data: 'id', name: 'id' },
        { data: 'cliente', name: 'cliente' },
        { data: 'tipo_documento', name: 'tipo_documento' },
        { data: 'usuario', name: 'usuario' },
        { data: 'fecha', name: 'fecha' },
        { data: 'total', name: 'total' },
        { data: 'monto_pagado', name: 'amount_paid' },
        { data: 'diferencia', name: 'diferencia' },
        { data: 'tipo_entrega', name: 'delivery_type' },
        { data: 'almacen', name: 'warehouse' },
        { data: 'estado_pedido', name: 'estado_pedido' },
        { data: 'estado_pago', name: 'estado_pago' },
        { data: 'metodo_pago', name: 'metodo_pago' },
        { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
    ],
    language: { url: '/assets/js/es-ES.json' },
    responsive: true,
    autoWidth: false,
    pageLength: 10,
    order: [[0, 'asc']],
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

function calcularTotal() {
    const table = $('#detalleVentaTableEditable').DataTable();
    let total = 0;
    // Recorre todas las filas (incluso las ocultas en otras paginas)
    table.rows().every(function () {
        const row = this.node();
        const input = row?.querySelector('.subtotal-input');
        if (input) {total += parseFloat(input.value) || 0;}
    });

    if (totalInput) totalInput.value = total.toFixed(2);
    actualizarEstadoPago();
}

function validarFila(row) {
    const pid = row.querySelector('.product-select').value;
    const qty = parseFloat(row.querySelector('.quantity-input').value);
    const cost = parseFloat(row.querySelector('.unit-price-input').value);
    return pid && qty > 0 && cost >= 0;
}

function productoYaExiste(productId, currentRow = null) {
    const table = $('#detalleVentaTableEditable').DataTable();
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

    return existe;
}

function agregarFilaProducto(data = {}) {
    const selectedId = data.product_id || '';
    const quantity = parseFloat(data.quantity) || '';
    const cost = parseFloat(data.unit_price) || '';
    const subtotal = quantity && cost ? (quantity * cost).toFixed(2) : '';

    const options = products.map(p =>
        `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${p.name}</option>`
    ).join('');

    const dt = $('#detalleVentaTableEditable').DataTable();
   dt.row.add([
        `<select class="form-select product-select" required>
            <option value="">Seleccione un producto</option>
            ${options}
         </select>`,
        `<input type="number" class="form-control quantity-input" min="1" value="${quantity}" required>`,
        `<input type="number" class="form-control unit-price-input" step="0.01" min="0" value="${cost}" required>`,
        `<input type="text" class="form-control subtotal-input" value="${subtotal}" readonly>`,
        `<button type="button" class="btn btn-danger remove-row">Eliminar</button>`
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
}

btnAgregarProducto?.addEventListener('click', agregarFilaProducto);

$(document).on('input', '.quantity-input, .unit-price-input', function () {
    const $row = $(this).closest('tr');
    const qty = parseFloat($row.find('.quantity-input').val()) || 0;
    const cost = parseFloat($row.find('.unit-price-input').val()) || 0;
    const subtotal = qty * cost;

    $row.find('.subtotal-input').val(subtotal.toFixed(2));
    calcularTotal();
});

$(document).on('click', '.remove-row', function () {
    const table = $('#detalleVentaTableEditable').DataTable();
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

    const tableDT = $('#detalleVentaTableEditable').DataTable();
    const detalle = [];
    let valido = true;
    let idsDuplicados = new Set();

    tableDT.rows().every(function () {
        const row = this.node();

        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.quantity-input');
        const costInput = row.querySelector('.unit-price-input');

        const pid = select?.value;
        const qty = parseFloat(qtyInput?.value);
        const cost = parseFloat(costInput?.value);

        if (!pid || qty <= 0 || cost < 0) {
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
            unit_price: cost
        });
    });

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
        if (result.isConfirmed) {
            const formData = new FormData(formVenta);
            const id = formData.get('venta_id');
            const url = id ? `/ventas/${id}` : '/ventas';
            if (id) formData.append('_method', 'PUT');
            formData.append('details', JSON.stringify(detalle));

            axios.post(url, formData)
                .then(res => {
                    document.activeElement.blur();
                    modalVenta.hide();
                    formVenta.reset();
                    tablaDetalle.innerHTML = '';
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
        }
    });
});

$(document).on('click', '.edit-btn', async function () {
    const id = $(this).data('id');

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
        $('#status').val(venta.estado).trigger('change');
        $('#payment_method').val(venta.payment_method).trigger('change');
        $('#total_price').val(parseFloat(venta.total).toFixed(2));
        if (amountPaidInput) amountPaidInput.value = parseFloat(venta.amount_paid).toFixed(2);
        if (paymentStatusHidden) {
            paymentStatusHidden.value = venta.payment_status;
            if (paymentStatusLabel) {
                paymentStatusLabel.value = paymentStatusLabels[venta.payment_status] || venta.payment_status;
            }
        }
        actualizarEstadoPago();

        // Llenar tabla de detalle
        tablaDetalle.innerHTML = '';
        inicializarDataTableEditable();

        if (Array.isArray(venta.detalle)) {
            venta.detalle.forEach(item => {
                agregarFilaProducto(item);
            });
            setTimeout(() => calcularTotal(), 100);
        } else {
            totalInput.value = parseFloat(venta.total).toFixed(2);
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
    const id = $(this).data('id');

    Swal.fire({
        title: 'estas seguro?',
        text: 'No podrs revertir esta accin.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'S, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            axios.delete(`/ventas/${id}`)
                .then(response => {
                    table.ajax.reload();

                    Toastify({
                        text: response.data.message || 'Venta eliminada',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        style: { background: '#28a745' }
                    }).showToast();
                })
                .catch(error => {
                    console.error(error);
                    Toastify({
                        text: 'Error al eliminar la venta',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        style: { background: '#dc3545' }
                    }).showToast();
                });
        }
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
                const subtotal = parseFloat(item.quantity) * parseFloat(item.unit_price);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>S/ ${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td>S/ ${subtotal.toFixed(2)}</td>
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
    if ($.fn.DataTable.isDataTable('#detalleVentaTableEditable')) {
        $('#detalleVentaTableEditable').DataTable().clear().destroy();
    }

    $('#detalleVentaTableEditable').DataTable({
        paging: true,
        pageLength: 5,
        lengthChange: false,
        searching: false,
        info: false,
        ordering: false,
        responsive: true,
        language: { url: '/assets/js/es-ES.json' }
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
    const table = $('#detalleVentaTableEditable').DataTable();

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
