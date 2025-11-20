import axios from 'axios';

axios.defaults.headers.common['X-CSRF-TOKEN'] =
    document.querySelector('meta[name="csrf-token"]').content;

const userRoles = (document.body.dataset.userRoles || '').toLowerCase();
const warehouseRoles = ['curva', 'milla', 'santa carolina'];
const IS_WAREHOUSE_ROLE = warehouseRoles.some(role => userRoles.includes(role));

const LOCATION_OPTIONS = [
    'ATE',
    'AYACUCHO',
    'BARRANCA',
    'CAJAMARCA',
    'CAÑETE',
    'CARABAYLLO',
    'CARAZ',
    'CHAMCHAMAYO',
    'CHANCAY',
    'CHICLAYO',
    'CHIMBOTE',
    'COMAS',
    'CONSTRUCENTER',
    'CUADRA 10',
    'CUADRA 11',
    'CUADRA 6',
    'CUADRA 7',
    'CUADRA 8',
    'CUADRA 9',
    'FRENTE',
    'GALERIA 1019',
    'GALERIA 1069',
    'GALERIA 907',
    'GALERIA 931',
    'GALERIA 955 ZOTANO',
    'HUACHIPA',
    'HUANCAYO',
    'HUANUCO',
    'HUARAL',
    'HUARAZ',
    'HUAROCHIRI',
    'HUAYCAN',
    'LAMBAYEQUE',
    'PIURA',
    'PTE. PIEDRA',
    'SAN JUAN DE LURIGANCHO',
    'SAN JUAN DE MIRAFLORES',
    'STA. CAROLINA',
    'STA. CLORINDA',
    'STA. INES',
    'STA. MERCEDEZ',
    'SURQUILLO',
    'TRUJILLO',
    'TUMBES',
    'VILLA EL SALVADOR',
    'SIN UBICACIÓN',
];

const modalElement = document.getElementById('modalTienda');
const modal = new bootstrap.Modal(modalElement);
const form = document.getElementById('formTienda');
const locationSelect = document.getElementById('location');
const hiddenId = document.getElementById('tienda_id');
const btnCrear = document.getElementById('btnCrearTienda');

function populateLocations(selected = '') {
    if (!locationSelect) return;
    locationSelect.innerHTML = '<option value="">-- Seleccione --</option>';
    LOCATION_OPTIONS.forEach((option) => {
        const opt = document.createElement('option');
        opt.value = option;
        opt.textContent = option;
        if (option === selected) {
            opt.selected = true;
        }
        locationSelect.appendChild(opt);
    });
}

btnCrear?.addEventListener('click', () => {
    form.reset();
    hiddenId.value = '';
    populateLocations();
    document.getElementById('modalTiendaLabel').textContent = 'Nueva Tienda';
    modal.show();
});

const customerColumns = [
    { data: 'row_number', name: 'row_number', searchable: false },
    { data: 'ruc', name: 'ruc' },
    { data: 'name', name: 'name' },
    { data: 'location', name: 'location', orderable: false, searchable: false },
];

if (!IS_WAREHOUSE_ROLE) {
    customerColumns.push(
        { data: 'phone', name: 'phone' },
        { data: 'acciones', name: 'acciones', orderable: false, searchable: false },
    );
}

const table = $('#customersTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: '/customers/data',
        type: 'GET'
    },
    columns: customerColumns,
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

function refreshColVisStyles() {
    $('.dt-button-collection .dt-button').each(function () {
        const $btn = $(this);
        const isActive = $btn.hasClass('active') || $btn.hasClass('dt-button-active');
        const hasCheck = $btn.find('.checkmark').length > 0;

        if (isActive && !hasCheck) {
            $btn.prepend('<span class="checkmark">&#10003;</span>');
        } else if (!isActive && hasCheck) {
            $btn.find('.checkmark').remove();
        }
    });
}

table.on('buttons-action', () => setTimeout(refreshColVisStyles, 10));
$(document).on('click', '.buttons-colvis', () => setTimeout(refreshColVisStyles, 50));
$(document).ready(() => setTimeout(refreshColVisStyles, 100));

$(document).on('click', '.edit-btn', async function () {
    const id = $(this).data('id');
    try {
        const { data } = await axios.get(`/customers/${id}`);

        hiddenId.value = data.id;
        document.getElementById('ruc').value = data.ruc || '';
        document.getElementById('name').value = data.name || '';
        document.getElementById('phone').value = data.phone || '';
        populateLocations(data.address || '');

        document.getElementById('modalTiendaLabel').textContent = 'Editar Tienda';
        modal.show();
    } catch (error) {
        console.error(error);
        Toastify({
            text: 'No se pudo cargar la tienda',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            backgroundColor: '#dc3545'
        }).showToast();
    }
});

form.addEventListener('submit', (event) => {
    event.preventDefault();

    const id = hiddenId.value;
    const payload = {
        ruc: document.getElementById('ruc').value,
        name: document.getElementById('name').value,
        phone: document.getElementById('phone').value,
        address: locationSelect.value
    };

    const request = id
        ? axios.put(`/customers/${id}`, payload)
        : axios.post('/customers', payload);

    request
        .then(({ data }) => {
            modal.hide();
            form.reset();
            populateLocations();
            table.ajax.reload(null, false);

            Toastify({
                text: data.message || (id ? 'Tienda actualizada' : 'Tienda creada'),
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#28a745'
            }).showToast();
        })
        .catch((error) => {
            console.error(error);
            Toastify({
                text: error.response?.data?.message || 'Error al guardar la tienda',
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#dc3545'
            }).showToast();
        });
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
        if (!result.isConfirmed) return;

        axios
            .delete(`/customers/${id}`)
            .then(({ data }) => {
                table.ajax.reload(null, false);
                Toastify({
                    text: data.message || 'Tienda eliminada',
                    duration: 3000,
                    gravity: 'top',
                    position: 'right',
                    backgroundColor: '#28a745'
                }).showToast();
            })
            .catch((error) => {
                console.error(error);
                Toastify({
                    text: 'Error al eliminar la tienda',
                    duration: 3000,
                    gravity: 'top',
                    position: 'right',
                    backgroundColor: '#dc3545'
                }).showToast();
            });
    });
});

document.getElementById('ruc')?.addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
});

document.getElementById('phone')?.addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
});

populateLocations();


