import axios from 'axios';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
 const modal = new bootstrap.Modal(document.getElementById('modalCategoria'));


        // Abrir modal para crear
        $('#btnCrearCategoria').on('click', function () {
        $('#modalCategoriaLabel').text('Nueva Categoría');
        $('#formCategoria').trigger('reset');
        $('#categoria_id').val('');
        modal.show();
        });


        const table = $('#categoriasTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/categorias/data',
                type: 'GET',
                xhrFields: {
                    withCredentials: true
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'description', name: 'description' },
                { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
            ],
            language: {
                url: '/assets/js/es-ES.json'
            },
            responsive: true,
            autoWidth: false,
            //lengthMenu: [10, 25, 50, 75, 100],
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


    // Abrir modal para editar
    $(document).on('click', '.edit-btn', function () {
        const id = $(this).data('id');
        axios.get(`/categorias/${id}`)
            .then(response => {
                const data = response.data;
                $('#modalCategoriaLabel').text('Editar Categoría');
                $('#categoria_id').val(data.id);
                $('#name').val(data.name);
                $('#description').val(data.description);
                modal.show();
            })
            .catch(error => {
                console.error('Error al obtener la categoría:', error);
                Toastify({
                    text: "No se pudo cargar la categoría",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#dc3545"
                }).showToast();
            });
    });


            // Guardar categoría
        document.getElementById('formCategoria').addEventListener('submit', function(e) {
            e.preventDefault();

            const id = document.getElementById('categoria_id').value;
            const url = id ? `/categorias/${id}` : '/categorias';

            const data = {
                name: document.getElementById('name').value,
                description: document.getElementById('description').value,
            };

            // Laravel espera PUT como POST + _method
            if (id) {
                data._method = 'PUT';
            }

            axios.post(url, data)
                .then(response => {
                    modal.hide();
                    this.reset(); // resetea el form
                    $('#categoriasTable').DataTable().ajax.reload();

                    Toastify({
                        text: response.data.message || (id ? "Categoría actualizada" : "Categoría creada"),
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#28a745"
                    }).showToast();
                })
                .catch(error => {
                    console.error(error);
                    Toastify({
                        text: "Error al guardar la categoría",
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#dc3545"
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
    }).then((result) => {
        if (result.isConfirmed) {
            axios.delete(`/categorias/${id}`)
            .then(response => {
                $('#categoriasTable').DataTable().ajax.reload();

                Toastify({
                    text: response.data.message || "Categoría eliminada",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#28a745"
                }).showToast();
            })
            .catch(error => {
                console.error(error);
                const message = error.response?.data?.message || "Error al eliminar la categoría";
                Toastify({
                    text: message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#dc3545"
                }).showToast();
            });
        }
    });
});
