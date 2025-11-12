import axios from 'axios';

const userRoles = (document.body.dataset.userRoles || '').toLowerCase();
const warehouseRoles = ['curva', 'milla', 'santa carolina'];
const IS_WAREHOUSE_ROLE = warehouseRoles.some(role => userRoles.includes(role));

﻿

axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;



const modal = new bootstrap.Modal(document.getElementById('modalProducto'));



let table = $('#productsTable').DataTable({

  processing: true,

  serverSide: true,

  ajax: { url: '/products/data', type: 'GET' },

  columns: [

    { data:'row_number', name: 'row_number', searchable: false },

    { data:'image', orderable:false, searchable:false },

    { data:'name' },
    { data:'product_code', name:'products.product_code', defaultContent: '-' },
    { data:'category_name', name:'category.name' },

    ...(!IS_WAREHOUSE_ROLE ? [
        { data:'price' },
        { data:'quantity' },
    ] : []),

    { data:'estado', name:'estado' },

    { data:'acciones', orderable:false, searchable:false }

  ],

  language: { url: '/assets/js/es-ES.json' },

  responsive: true,

  dom: 'Bfrtip',

  buttons: [

    { extend:'colvis', text:'Seleccionar Columnas', className:'btn btn-info', postfixButtons:['colvisRestore'] }

  ],
  order: [[0, 'desc']]

});



// Funci├│n para actualizar estilos de los botones colVis

function updateColvisStyles() {

  $('.dt-button-collection .dt-button').each(function () {

    const isActive = $(this).hasClass('active') || $(this).hasClass('dt-button-active');



    if (isActive) {

      // Agregar check si no existe

      if ($(this).find('.checkmark').length === 0) {

        $(this).prepend('<span class="checkmark">Ô£ö</span>');

      }

    } else {

      // Remover check si existe

      $(this).find('.checkmark').remove();

    }

  });

}



// Evento cuando se hace alguna acci├│n con los botones (activar/desactivar columna)

table.on('buttons-action', function () {

  setTimeout(updateColvisStyles, 10);

});



// Evento para cuando abren el men├║ de columnas visibles

$(document).on('click', '.buttons-colvis', function () {

  setTimeout(updateColvisStyles, 50);

});

$(document).on('click', '.view-image-btn', function () {
  const imageUrl = $(this).data('image-url') || defaultProductImage;
  const modalImg = document.getElementById('imgProductoModal');
  if (modalImg) {
    modalImg.src = imageUrl;
  }
  if (modalVerImagenProducto) {
    modalVerImagenProducto.show();
  }
});



// Opcional: cuando se carga la p├ígina

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

        console.log('Usando men├║ lateral (.app-menu)');

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



        console.log('Men├║ ocultado');

    }

});

});



$(document).ready(() => {

  $('#category_id').select2({

    dropdownParent: $('#modalProducto'),

    width: '100%',

    theme: 'bootstrap-5',

    placeholder: '',

    allowClear: true

  });

});



/*

$(document).ready(() => {

    $('#category_id, #status').select2({

    dropdownParent: $('#modalProducto'),

    width: '100%',

    placeholder: 'Seleccione una opci├│n',

    allowClear: true,

    theme: 'bootstrap-5'

  });

});*/



// Manejo de imagenes

const defaultProductImage = '/assets/images/product.png';

const $imagesInput = $('#images');

const $viewProductImageBtn = $('#btnVerImagenProducto');

const modalVerImagenProductoElement = document.getElementById('modalVerImagenProducto');

const modalVerImagenProducto = modalVerImagenProductoElement ? new bootstrap.Modal(modalVerImagenProductoElement) : null;



function setProductImageButton(imageUrl = null) {

  if (!$viewProductImageBtn.length) return;



  if (imageUrl) {

    $viewProductImageBtn.data('image-url', imageUrl).show();

  } else {

    $viewProductImageBtn.removeData('image-url').hide();

  }

}



function resetProductImageSelection() {

  if ($imagesInput.length) {

    $imagesInput.val('');

  }



  setProductImageButton(null);

}



function resolveProductImageUrl(images = []) {

  if (!Array.isArray(images) || !images.length) {

    return defaultProductImage;

  }



  const realImage = images.find(img => !img.is_placeholder && img && img.url);

  const selectedImage = realImage || images[0];



  return (selectedImage && selectedImage.url) || defaultProductImage;

}



if ($viewProductImageBtn.length && modalVerImagenProducto) {

  $viewProductImageBtn.on('click', () => {

    const url = $viewProductImageBtn.data('image-url');

    if (!url) return;



    $('#imgProductoModal').attr('src', url);

    modalVerImagenProducto.show();

  });

}

//Select de categoria

let categoriasCache = null;



function cargarCategoriasEnSelect(idSeleccionado = null, callback = null) {

  const $select = $('#category_id');



  // Coloca un estado temporal claro para evitar datos fantasma

  $select.empty().append(new Option('Cargando categorías...', '', true, true)).trigger('change');



  // Armar URL con include_id si aplica

  let url = '/categorias/select';

  if (idSeleccionado) {

    url += '?include_id=' + encodeURIComponent(idSeleccionado);

  }



  axios.get(url)

    .then(response => {

      const categorias = response.data;



      // Guardamos cach├® si quieres evitar futuras peticiones

      categoriasCache = categorias;



      // Limpiar y poblar correctamente

      $select.empty().append(new Option('-- Seleccione --', '', true, false));



      categorias.forEach(c => {

        const isSelected = idSeleccionado == c.id;

        $select.append(new Option(c.text, c.id, false, isSelected));

      });



      $select.trigger('change');



       //Esta l├¡nea es la clave para que se ejecute el callback y se muestre el modal

      if (typeof callback === 'function') {

        callback();

      }

    })

    .catch(error => {

      console.error('Error al cargar categorías:', error);



      $select.empty().append(new Option('-- Error al cargar --', '', true, true)).trigger('change');



      Toastify({

        text: "Error al cargar categorías",

        duration: 3000,

        gravity: "top",

        position: "right",

        backgroundColor: "#dc3545"

      }).showToast();

    });

}



$('#modalProducto').on('hidden.bs.modal', () => {

  resetProductImageSelection();

});





// Crear producto

$('#btnCrearProducto').on('click', async () => {

  $('#formProducto')[0].reset();

  resetProductImageSelection();

  $('#producto_id').val('');

  $('#modalProductoLabel').text('Nuevo Producto');

  //$('#category_id').val(null).trigger('change');

  //$('#category_id, #status').val(null).trigger('change');



  modal.show();

  $('#modalProducto .modal-content').append('<div id="cargandoOverlay" class="modal-loading-overlay"></div>');



  try {

    await new Promise(resolve => cargarCategoriasEnSelect(null, resolve));

  } catch (error) {

    console.error('Error al cargar categorías:', error);

    Toastify({

      text: 'No se pudo cargar las categorías',

      duration: 3000,

      gravity: 'top',

      position: 'right',

      style: { background: '#dc3545' }

    }).showToast();

  } finally {

    $('#cargandoOverlay').remove();

  }

});

// Editar producto

$(document).on('click', '.edit-btn', async function() {

  const id = $(this).data('id');

  resetProductImageSelection();



  $('#modalProducto .modal-content').append('<div id="cargandoOverlay" class="modal-loading-overlay"></div>');



  try {

    const { data } = await axios.get(`/products/${id}`);

    const product = data.product;

    const categories = data.categories;



    $('#producto_id').val(product.id);

    $('#name').val(product.name);
    $('#product_code').val(product.product_code ?? '');

    $('#price').val(product.price);

    $('#quantity').val(product.quantity);



    const $select = $('#category_id');

    $select.empty();

    categories.forEach(cat => {

      const selected = cat.id === product.category_id ? 'selected' : '';

      $select.append(`<option value="${cat.id}" ${selected}>${cat.text}</option>`);

    });

    $select.trigger('change');



    $('#images').val(null);

    const imageUrl = resolveProductImageUrl(product.images || []);

    setProductImageButton(imageUrl);

    $('#modalProductoLabel').text('Editar Producto');

    modal.show();

  } catch (error) {

    Toastify({

      text: "Error al cargar producto",

      duration: 3000,

      gravity: "top",

      position: "right",

      backgroundColor: "#dc3545"

    }).showToast();

  } finally {

    $('#cargandoOverlay').remove();

  }

});

// Guardar producto

$('#formProducto').on('submit', function(e){

  e.preventDefault();



  const id = $('#producto_id').val();

  const url = id ? `/products/${id}` : '/products';

  const method = 'post';

  const fd = new FormData(this);



  if (id) fd.append('_method', 'PUT');



  axios({

    method,

    url,

    data: fd,

    headers: { 'Content-Type': 'multipart/form-data' }

  }).then(({data}) => {

    Toastify({

      text: data.message,

      duration: 3000,

      gravity: "top",

      position: "right",

      backgroundColor: "#28a745"

    }).showToast();



    modal.hide();

    table.ajax.reload(null, false);

  }).catch(err => {



    Toastify({

      text: err.response?.data?.message || 'Error al guardar producto.',

      duration: 3000,

      gravity: "top",

      position: "right",

      backgroundColor: "#dc3545"

    }).showToast();

  });

});







// Eliminar producto con confirmaci├│n SweetAlert2

$(document).on('click', '.delete-btn', function(e){

  e.preventDefault();

  let id = $(this).data('id');



  Swal.fire({

    title: '\u00BFEst\u00e1s seguro?',

    text: '\u00A1No podr\u00e1s revertir esto!',

    icon: 'warning',

    showCancelButton: true,

    confirmButtonColor: '#d33',

    cancelButtonColor: '#3085d6',

    confirmButtonText: 'S\u00ed, eliminar',

    cancelButtonText: 'Cancelar'

  }).then(res => {

    if(res.isConfirmed){

      axios.delete(`/products/${id}`)

        .then(() => {

          table.ajax.reload(null, false);

          Toastify({

            text: "Producto eliminado",

            duration: 3000,

            gravity: "top",

            position: "right",

            backgroundColor: "#28a745"

          }).showToast();

        }).catch(() => {

          Toastify({

            text: "Error al eliminar producto",

            duration: 3000,

            gravity: "top",

            position: "right",

            backgroundColor: "#dc3545"

          }).showToast();

        });

    }

  });

});





