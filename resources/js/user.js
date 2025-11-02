import axios from 'axios';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
const modalVerLogoEl = document.getElementById('modalVerLogo');
const modalVerLogo = new bootstrap.Modal(modalVerLogoEl, { keyboard: true });

$('#btnVerLogo').on('click', function () {
    const url = $(this).data('photo-url');
    $('#imgLogoModal').attr('src', url);
    modalVerLogo.show();
});

async function cargarRoles(preselectId = null) {
    try {
        const response = await axios.get('/roles/list');
        const select = document.getElementById('role');
        select.innerHTML = '<option value="">-- Seleccione --</option>';

        response.data.forEach(role => {
            const selected = preselectId && preselectId == role.id ? 'selected' : '';
            select.innerHTML += `<option value="${role.id}" ${selected}>${role.name}</option>`;
        });
    } catch (error) {
        console.error('Error al cargar roles:', error);
        Toastify({
            text: 'Error al cargar roles',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            backgroundColor: '#dc3545'
        }).showToast();
    }
}

$(document).ready(() => {
  $('#role').select2({
    dropdownParent: $('#modalUsuario'),
    width: '100%',
    placeholder: 'Seleccione una opción',
    allowClear: true,
    theme: 'bootstrap-5'
  });
});

$('#btnCrearUsuario').on('click', function () {
    $('#modalUsuarioLabel').text('Nuevo Usuario');
    $('#formUsuario').trigger('reset');
    $('#usuario_id').val('');
    $('#role').val(null).trigger('change');
    $('label[for="password"]').text('Contraseña');
    $('label[for="password_confirmation"]').text('Confirmar Contraseña');
    $('#btnVerLogo').hide();
    cargarRoles();
    modal.show();
});

const table = $('#usersTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: '/users/data',
        type: 'GET',
        xhrFields: { withCredentials: true }
    },
    columns: [
        { data: 'id', name: 'id' },
        {
            data: 'photo',
            name: 'photo',
            orderable: false,
            searchable: false,
        },
        { data: 'name', name: 'name' },
        { data: 'email', name: 'email' },
        { data: 'phone', name: 'phone' },
        {
            data: 'role',
            name: 'role',
        },
        {
            data: 'acciones',
            name: 'acciones',
            orderable: false,
            searchable: false
        }
    ],
    language: {
        url: '/assets/js/es-ES.json'
    },
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

function updateColvisStyles() {
    $('.dt-button-collection .dt-button').each(function () {
        const isActive = $(this).hasClass('active') || $(this).hasClass('dt-button-active');
        if (isActive && $(this).find('.checkmark').length === 0) {
            $(this).prepend('<span class="checkmark">✔</span>');
        } else if (!isActive) {
            $(this).find('.checkmark').remove();
        }
    });
}

table.on('buttons-action', () => setTimeout(updateColvisStyles, 10));
$(document).on('click', '.buttons-colvis', () => setTimeout(updateColvisStyles, 50));
$(document).ready(() => setTimeout(updateColvisStyles, 100));

$(window).on('scroll', function () {
    const $menu = $('.dt-button-collection:visible');
    if (!$menu.length) return;

    const windowWidth = document.documentElement.clientWidth;
    let $nav = windowWidth >= 1024 && $('.app-menu').is(':visible') ? $('.app-menu') : $('#page-topbar');
    if (!$nav.length) return;

    const menuTop = $menu.offset().top;
    const navBottom = $nav.offset().top + $nav.outerHeight();
    if (menuTop < navBottom + 2) {
        const $toggleBtn = $('.buttons-colvis');
        $menu.css('z-index', 50).fadeOut(200, function () {
            $(this).css('z-index', 1050);
        });
        $('body').trigger('click');
        $toggleBtn.removeClass('active dt-btn-split-drop-active').attr('aria-expanded', 'false').blur();
    }
});

$(document).on('click', '.edit-btn', async function () {
    const id = $(this).data('id');
    try {
        const { data } = await axios.get(`/users/${id}`);

        $('#modalUsuarioLabel').text('Editar Usuario');
        $('#usuario_id').val(data.id);
        $('#name').val(data.name);
        $('#email').val(data.email);
        $('#phone').val(data.phone);
        $('#password').val('');
        $('#password_confirmation').val('');
        $('label[for="password"]').text('Nueva Contraseña');
        $('label[for="password_confirmation"]').text('Confirmar Nueva Contraseña');

        await cargarRoles(data.role_id);

        if (data.photo_url) {
            $('#btnVerLogo').data('photo-url', data.photo_url).show();
        } else {
            $('#btnVerLogo').hide();
        }

        modal.show();
    } catch (error) {
        console.error('Error al obtener el usuario:', error);
        Toastify({
            text: 'No se pudo cargar el usuario',
            duration: 3000,
            gravity: 'top',
            position: 'right',
            backgroundColor: '#dc3545'
        }).showToast();
    }
});

document.getElementById('formUsuario').addEventListener('submit', function (e) {
    e.preventDefault();

    const id = document.getElementById('usuario_id').value;
    const url = id ? `/users/${id}` : '/users';

    const formData = new FormData(this);
    if (id) formData.append('_method', 'PUT');

    axios.post(url, formData)
        .then(response => {
            modal.hide();
            this.reset();
            table.ajax.reload();
            Toastify({
                text: response.data.message || (id ? 'Usuario actualizado' : 'Usuario creado'),
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#28a745'
            }).showToast();
        })
        .catch(error => {
            const response = error.response;
            let message = 'Error al guardar el usuario';

            if (response?.data?.errors) {
                const messages = [];
                Object.entries(response.data.errors).forEach(([field, errors]) => {
                    errors.forEach(original => {
                        messages.push(`${field.replace(/_/g, ' ')}: ${original}`);
                    });
                });
                message = messages.join('\n');
            } else if (response?.data?.message) {
                message = response.data.message;
            }

            console.error(response?.data || error);

            Toastify({
                text: message,
                duration: 4000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#dc3545'
            }).showToast();
        });
});

$(document).on('click', '.delete-btn', function (e) {
    e.preventDefault();
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
    }).then(result => {
        if (result.isConfirmed) {
            axios.delete(`/users/${id}`)
                .then(response => {
                    table.ajax.reload();
                    Toastify({
                        text: response.data.message || 'Usuario eliminado',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        backgroundColor: '#28a745'
                    }).showToast();
                })
                .catch(error => {
                    console.error(error);
                    Toastify({
                        text: 'Error al eliminar el usuario',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        backgroundColor: '#dc3545'
                    }).showToast();
                });
        }
    });
});
