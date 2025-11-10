import axios from 'axios';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

document.addEventListener('DOMContentLoaded', () => {
    const collapseEl = document.getElementById('collapseWithicon2');
    collapseEl.classList.remove('collapse-init-hide'); // ya renderizado, listo para colapsar
});

const modalElement = document.getElementById('modalRol');
const modal = new bootstrap.Modal(modalElement);
const collapseEl = document.getElementById('collapseWithicon2');
const collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapseEl);
const listaPermisos = document.getElementById('listaPermisos');


// Inicializar DataTable
const table = $('#rolesTable').DataTable({
    processing: true,
    serverSide: true,
    lengthChange: false,
    ajax: '/roles/data',
    columns: [
        {
            data: 'row_number',
            name: 'row_number',
            searchable: false,
        },
        { data: 'name', name: 'name' },
        { data: 'created_at', name: 'created_at', orderable: false, searchable: false },
        { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
    ],
    language: {
        url: '/assets/js/es-ES.json'
    },
    responsive: true,
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



// Ocultar lista permisos antes de cargar
function cargarPermisos(seleccionados = []) {
    listaPermisos.style.opacity = '0';
    listaPermisos.style.transition = 'opacity 0.3s ease';

    return axios.get('/roles/permissions')
        .then(response => {
            listaPermisos.innerHTML = '';

            response.data.forEach(p => {
                const isChecked = seleccionados.includes(p.id) ? 'checked' : '';
                const div = document.createElement('div');
                div.classList.add('col-md-4');
                div.innerHTML = `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="${p.id}" id="perm-${p.id}" ${isChecked}>
                        <label class="form-check-label" for="perm-${p.id}">${p.description}</label>
                    </div>`;
                listaPermisos.appendChild(div);
            });

            setTimeout(() => {
                listaPermisos.style.opacity = '1';
            }, 50);
        })
        .catch(error => {
            console.error('Error cargando permisos:', error);
            Toastify({
                text: "Error al cargar permisos",
                backgroundColor: "#dc3545",
                duration: 3000
            }).showToast();
        });
}

// Cuando se cierra el modal, cierra el acordeón
modalElement.addEventListener('hidden.bs.modal', () => {
    collapseInstance.hide();
});

// Crear nuevo rol
document.getElementById('btnCrearRol').addEventListener('click', () => {
    document.getElementById('modalRolLabel').textContent = 'Nuevo Rol';
    document.getElementById('formRol').reset();
    document.getElementById('rol_id').value = '';

    cargarPermisos([]).then(() => {
        modal.show();
        collapseInstance.show(); // 👈 Asegura que se abra siempre el acordeón
    });
});


// Editar rol
// Editar rol
// Editar rol
$(document).on('click', '.edit-btn', function () {
    const id = $(this).data('id');

    axios.get(`/roles/${id}`)
        .then(response => {
            const rol = response.data;
            document.getElementById('modalRolLabel').textContent = 'Editar Rol';
            document.getElementById('rol_id').value = rol.id;
            document.getElementById('nombreRol').value = rol.name;

            cargarPermisos(rol.permissions.map(p => p.id)).then(() => {
                // Mostrar el modal
                modal.show();

                // Esperar a que el modal termine de mostrarse antes de abrir el acordeón
                modalElement.addEventListener('shown.bs.modal', function handler() {
                    collapseInstance.show();
                    modalElement.removeEventListener('shown.bs.modal', handler);
                });
            });
        })
        .catch(error => {
            console.error(error);
            Toastify({
                text: "No se pudo cargar el rol",
                backgroundColor: "#dc3545",
                duration: 3000
            }).showToast();
        });
});


// Guardar rol (crear o editar)
document.getElementById('formRol').addEventListener('submit', function (e) {
    e.preventDefault();
    const id = document.getElementById('rol_id').value;
    const form = new FormData(this);
    if (id) { form.append('_method', 'PUT'); }
    const url = id ? `/roles/${id}` : '/roles';

    axios.post(url, form).then(res => {
        modal.hide();
        table.ajax.reload(); // Recargar tabla usando la variable table
        this.reset();
        Toastify({
            text: res.data.message,
            backgroundColor: "#28a745",
            duration: 3000
        }).showToast();
    }).catch(() => Toastify({
        text: "Error al guardar el rol",
        backgroundColor: "#dc3545",
        duration: 3000
    }).showToast());
});

// Eliminar rol
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
            axios.delete(`/roles/${id}`)
                .then(response => {
                    table.ajax.reload();
                    Toastify({
                        text: response.data.message || "Rol eliminado",
                        backgroundColor: "#28a745",
                        duration: 3000
                    }).showToast();
                })
                .catch(error => {
                    console.error(error);
                    Toastify({
                        text: "Error al eliminar el rol",
                        backgroundColor: "#dc3545",
                        duration: 3000
                    }).showToast();
                });
        }
    });
});
