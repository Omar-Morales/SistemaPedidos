import axios from 'axios';

axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

const modalCompra = new bootstrap.Modal(document.getElementById('modalCompra'));
const formCompra = document.getElementById('formCompra');
const tablaDetalle = document.getElementById('detalleCompraBody');
const totalInput = document.getElementById('total_cost');
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

    // ✅ Filtra solo IDs válidos numéricos
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
const $supplier_id = $('#supplier_id');
const $tipodocumento_id = $('#tipodocumento_id');

[$status, $supplier_id, $tipodocumento_id].forEach($el => {
    if (!$el.hasClass('select2-hidden-accessible')) {
        $el.select2({
            dropdownParent: $('#modalCompra'),
            width: '100%',
            placeholder: '',
            allowClear: true,
            theme: 'bootstrap-5'
        });
    }
});

function resetearModalCompra() {
    formCompra.reset();
    $('#compra_id').val('');
    $status.val('').trigger('change');
    $supplier_id.val('').trigger('change');
    $tipodocumento_id.val('').trigger('change');
    $('#codigo_numero').val('');
    totalInput.value = '';

    if ($.fn.DataTable.isDataTable('#detalleCompraTableEditable')) {
        $('#detalleCompraTableEditable').DataTable().clear().destroy();
    }


    // 🔥 Primero elimina todos los select2 y sus eventos
    $('#detalleCompraBody .product-select').each(function () {
        if ($(this).data('select2')) {
            $(this).select2('destroy'); // quita completamente select2
        }
        $(this).off(); // quita eventos si quedaran
    });

    // 🔥 Luego limpia las filas del tbody
    tablaDetalle.innerHTML = '';

}
/*************************para el seelct supllier - proveedor*************************************/
let suppliersCache = null;

function cargarSuppliersEnSelect(idSeleccionado = null, callback = null) {
  const $select = $('#supplier_id');

  $select.empty().append(new Option('Cargando proveedores...', '', true, true)).trigger('change');

  let url = '/suppliers/select';
  if (idSeleccionado) {
    url += '?include_id=' + encodeURIComponent(idSeleccionado);
  }

  axios.get(url)
    .then(response => {
      const suppliers = response.data;

      suppliersCache = suppliers;

      $select.empty().append(new Option('-- Seleccione --', '', true, false));

      suppliers.forEach(s => {
        const isSelected = idSeleccionado == s.id;
        $select.append(new Option(s.text, s.id, false, isSelected));
      });

      $select.trigger('change');

      if (callback) callback();
    })
    .catch(error => {
      console.error('Error al cargar proveedores:', error);

      $select.empty().append(new Option('-- Error al cargar --', '', true, true)).trigger('change');

      Toastify({
        text: "Error al cargar proveedores",
        duration: 3000,
        gravity: "top",
        position: "right",
        backgroundColor: "#dc3545"
      }).showToast();
    });
}
/**************************************************************/
$('#btnCrearCompra').on('click', async (e) => {
    e.currentTarget.blur();
    $('#modalCompraLabel').text('Nueva Compra');
    resetearModalCompra();

    // Overlay de carga
    $('#modalCompra .modal-content').append('<div id="cargandoOverlay" class="modal-loading-overlay"></div>');

    try {
        await new Promise(resolve => cargarProductos(resolve));
        await new Promise(resolve => cargarSuppliersEnSelect(null, resolve));
        inicializarDataTableEditable();
        modalCompra.show();
    } catch (error) {
        console.error('Error al preparar modal de compra:', error);
        Toastify({
            text: 'Error al cargar datos para la compra',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            style: { background: '#dc3545' }
        }).showToast();
    } finally {
        $('#cargandoOverlay').remove();
    }
});


$('#modalCompra').on('hidden.bs.modal', function () {
resetearModalCompra();
document.activeElement.blur();
});


const table = $('#comprasTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: '/compras/data',
    columns: [
        { data: 'id', name: 'compras.id' },
        { data: 'proveedor', name: 'suppliers.name' },
        { data: 'tipo_documento', name: 'tipodocumento.name' },
        { data: 'codigo_numero', name: 'compras.codigo_numero' },
        { data: 'usuario', name: 'users.name' },
        { data: 'fecha', name: 'compras.purchase_date' },
        { data: 'total', name: 'compras.total_cost' },
        { data: 'estado', name: 'compras.status' },
        { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
    ],
    language: { url: '/assets/js/es-ES.json' },

    responsive: true,
    autoWidth: false,
    //lengthMenu: [10, 25, 50, 75, 100],
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
                $(this).prepend('<span class="checkmark">✔</span>');
            }
            } else {
            // Remover check si existe
            $(this).find('.checkmark').remove();
            }
        });
        }

        // Evento cuando se hace alguna acción con los botones (activar/desactivar columna)
    table.on('buttons-action', function () {
    setTimeout(updateColvisStyles, 10);
    });

    // Evento para cuando abren el menú de columnas visibles
    $(document).on('click', '.buttons-colvis', function () {
    setTimeout(updateColvisStyles, 50);
    });

    // Opcional: cuando se carga la página
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
            console.log('Usando menú lateral (.app-menu)');
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

            console.log('Menú ocultado');
        }
    });

    });

function calcularTotal() {
    const table = $('#detalleCompraTableEditable').DataTable();
    let total = 0;

    // Recorre todas las filas (incluso las ocultas en otras páginas)
    table.rows().every(function () {
        const row = this.node();
        const input = row?.querySelector('.subtotal-input');
        if (input) {total += parseFloat(input.value) || 0;}
    });

    if (totalInput) totalInput.value = total.toFixed(2);
}

function validarFila(row) {
    const pid = row.querySelector('.product-select').value;
    const qty = parseFloat(row.querySelector('.quantity-input').value);
    const cost = parseFloat(row.querySelector('.unit-cost-input').value);
    return pid && qty > 0 && cost >= 0;
}

function productoYaExiste(productId, currentRow = null) {
    const table = $('#detalleCompraTableEditable').DataTable();
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
    const cost = parseFloat(data.unit_cost) || '';
    const subtotal = quantity && cost ? (quantity * cost).toFixed(2) : '';

    const options = products.map(p =>
        `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${p.name}</option>`
    ).join('');

    const dt = $('#detalleCompraTableEditable').DataTable();

    dt.row.add([
        `<select class="form-select product-select" required>
            <option value="">Seleccione un producto</option>
            ${options}
         </select>`,
        `<input type="number" class="form-control quantity-input" min="1" value="${quantity}" required>`,
        `<input type="number" class="form-control unit-cost-input" step="0.01" min="0" value="${cost}" required>`,
        `<input type="text" class="form-control subtotal-input" value="${subtotal}" readonly>`,
        `<button type="button" class="btn btn-danger remove-row">Eliminar</button>`
    ]).draw(false);

    const lastRowNode = dt.row(':last').node();
    const $select = $(lastRowNode).find('.product-select');

    if (!$select.hasClass('select2-initialized')) {
        $select
            .addClass('select2-initialized')
            .select2({
                dropdownParent: $('#modalCompra .modal-content'),
                width: '100%',
                allowClear: true,
                theme: 'bootstrap-5',
                placeholder: 'Seleccione un producto',
                containerCssClass: 'select2-in-modal'
            });
    }
}


btnAgregarProducto?.addEventListener('click', agregarFilaProducto);

$(document).on('input', '.quantity-input, .unit-cost-input', function () {
    const $row = $(this).closest('tr');
    const qty = parseFloat($row.find('.quantity-input').val()) || 0;
    const cost = parseFloat($row.find('.unit-cost-input').val()) || 0;
    const subtotal = qty * cost;

    $row.find('.subtotal-input').val(subtotal.toFixed(2));
    calcularTotal();
});

$(document).on('click', '.remove-row', function () {
    const table = $('#detalleCompraTableEditable').DataTable();
    const currentPage = table.page(); // página actual
    const currentRows = table.rows({ page: 'current' }).count(); // filas actuales visibles

    // Eliminar la fila
    table.row($(this).closest('tr')).remove();

    // Si era la única fila visible y no estás en la página 0, retrocede
    if (currentRows === 1 && currentPage > 0) {
        table.page(currentPage - 1).draw('page');
    } else {
        table.draw(false); // Redibuja manteniendo la página
    }

    calcularTotal();
});

/*
$(document).on('click', '.remove-row', function () {
    const table = $('#detalleCompraTableEditable').DataTable();
    table.row($(this).closest('tr')).remove().draw();

    // Si ya no hay filas visibles en esta página, retrocede una
    const info = table.page.info();
    if (info.end === info.start && info.page > 0) {
        table.page('previous').draw('page');
    }

    calcularTotal();
});*/

formCompra.addEventListener('submit', function (e) {
    e.preventDefault();

    const tableDT = $('#detalleCompraTableEditable').DataTable();
    const detalle = [];
    let valido = true;
    let idsDuplicados = new Set();

    tableDT.rows().every(function () {
        const row = this.node();

        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.quantity-input');
        const costInput = row.querySelector('.unit-cost-input');

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
            unit_cost: cost
        });
    });

    if (!valido || detalle.length === 0) {
        Swal.fire('Error', 'Verifique que cada producto esté completo y sin duplicados.', 'error');
        return;
    }

    Swal.fire({
        title: '¿Guardar compra?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData(formCompra);
            const id = formData.get('compra_id');
            const url = id ? `/compras/${id}` : '/compras';
            if (id) formData.append('_method', 'PUT');
            formData.append('details', JSON.stringify(detalle));

            axios.post(url, formData)
                .then(res => {
                    document.activeElement.blur();
                    modalCompra.hide();
                    formCompra.reset();
                    tablaDetalle.innerHTML = '';
                    table.ajax.reload();

                    Toastify({
                        text: res.data.message || 'Compra registrada',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        style: { background: '#28a745' }
                    }).showToast();
                })
                .catch(err => {
                    console.error(err);
                    Toastify({
                        text: err.response?.data?.message || 'Error al guardar la compra.',
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
    $('#modalCompra .modal-content').append('<div id="cargandoOverlay" class="modal-loading-overlay"></div>');

    try {
        // Cargar productos una sola vez antes de la compra
        //await new Promise(resolve => cargarProductos(resolve));

        const { data } = await axios.get(`/compras/${id}`);
        const compra = data.compra;
        const proveedores = data.proveedores;

        const productosEnCompra = (compra.detalle || []).map(item => item.product_id);
        await new Promise(resolve => cargarProductos(resolve, productosEnCompra));
        // Llenar select de proveedor (incluso si está inactivo)
        const $proveedorSelect = $('#supplier_id');
        $proveedorSelect.empty();
        proveedores.forEach(p => {
            const selected = p.id === compra.proveedor ? 'selected' : '';
            $proveedorSelect.append(`<option value="${p.id}" ${selected}>${p.text}</option>`);
        });
        $proveedorSelect.trigger('change');

        // Llenar otros campos
        $('#modalCompraLabel').text('Editar Compra');
        $('#compra_id').val(compra.id);
        $('#tipodocumento_id').val(compra.tipo_documento).trigger('change');
        $('#purchase_date').val(compra.fecha);
        $('#codigo_numero').val(compra.codigo_numero ?? '');
        $('#status').val(compra.estado).trigger('change');

        // Llenar tabla de detalle
        tablaDetalle.innerHTML = '';
        inicializarDataTableEditable();

        if (Array.isArray(compra.detalle)) {
            compra.detalle.forEach(item => {
                agregarFilaProducto(item); // Ya maneja select2 por fila
            });
            setTimeout(() => calcularTotal(), 100);
        } else {
            totalInput.value = parseFloat(compra.total).toFixed(2);
        }

        // Mostrar modal
        document.activeElement.blur();
        modalCompra.show();
    } catch (error) {
        console.error('Error al obtener la compra:', error);
        Toastify({
            text: 'No se pudo cargar la compra',
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
        title: '¿Estás seguro?',
        text: 'No podrás revertir esta acción.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            axios.delete(`/compras/${id}`)
                .then(response => {
                    table.ajax.reload();

                    Toastify({
                        text: response.data.message || 'Compra eliminada',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        style: { background: '#28a745' }
                    }).showToast();
                })
                .catch(error => {
                    console.error(error);
                    Toastify({
                        text: 'Error al eliminar la compra',
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
    axios.get(`/compras/${id}/detalle`)
        .then(response => {
            const productos = response.data.detalle;
            const tbody = document.getElementById('detalleCompraBodydos');
            tbody.innerHTML = '';

            productos.forEach(item => {
                const subtotal = parseFloat(item.quantity) * parseFloat(item.unit_cost);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>S/ ${parseFloat(item.unit_cost).toFixed(2)}</td>
                    <td>S/ ${subtotal.toFixed(2)}</td>
                `;
                tbody.appendChild(row);
            });

            if ($.fn.DataTable.isDataTable('#detalleCompraTable')) {
                $('#detalleCompraTable').DataTable().destroy();
            }

            $('#detalleCompraTable').DataTable({
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
            $('#modalDetalleCompra').modal('show');
        })
        .catch(error => {
            console.error('Error al obtener detalle de compra:', error);
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
    if ($.fn.DataTable.isDataTable('#detalleCompraTableEditable')) {
        $('#detalleCompraTableEditable').DataTable().clear().destroy();
    }

    $('#detalleCompraTableEditable').DataTable({
        paging: true,
        pageLength: 5, // ← Cambiado de 4 a 5
        lengthChange: false,
        searching: false,
        info: false,
        ordering: false,
        responsive: true,
        language: {
            url: '/assets/js/es-ES.json' // Asegúrate que este archivo existe
        }
    });
}

// Recalcular total al cambiar de página del DataTable
$('#detalleCompraTableEditable').on('page.dt', function () {
    calcularTotal();
});

$(document).on('select2:select', '.product-select', function (e) {
    const $select = $(this);
    const selectedProductId = $select.val();
    const currentRow = $select.closest('tr')[0];

    if (productoYaExiste(selectedProductId, currentRow)) {
        Toastify({
            text: 'Este producto ya está seleccionado en otra fila.',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            style: { background: '#dc3545' }
        }).showToast();

        $select.val(null).trigger('change.select2');
    }
});

//agregar opcionalmente
/*$(document).on('change', '.product-select', function () {
    const $select = $(this);
    const selectedProductId = $select.val();
    const currentRow = $select.closest('tr')[0];

    if (selectedProductId && productoYaExiste(selectedProductId, currentRow)) {
        Toastify({
            text: 'Este producto ya está seleccionado en otra fila.',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            style: { background: '#dc3545' }
        }).showToast();

        $select.val(null).trigger('change.select2');
    }
});*/


$(document).on('select2:opening', '.product-select', function () {
    const $select = $(this);
    const currentVal = $select.val(); // mantener el valor actual
    const currentRow = $select.closest('tr')[0];

    const seleccionados = new Set();

    // Usar DataTable API para recorrer todas las filas
    const table = $('#detalleCompraTableEditable').DataTable();

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


$('#modalDetalleCompra').on('hidden.bs.modal', function () {
    if ($.fn.DataTable.isDataTable('#detalleCompraTable')) {
        $('#detalleCompraTable').DataTable().clear().destroy();
    }
    document.getElementById('detalleCompraBodydos').innerHTML = '';
});
