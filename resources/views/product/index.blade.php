@extends('layouts.app')

@section('title', 'Mantenimiento de Productos')

@section('content')
<div class="page-content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
          <h4 class="mb-sm-0">Listado de Productos</h4>

          <div class="page-title-right">
            <ol class="breadcrumb m-0">
              <li class="breadcrumb-item"><a href="javascript:void(0);">Mantenimiento</a></li>
              <li class="breadcrumb-item active">Productos</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            @can('administrar.productos.create')
            <button type="button" class="btn btn-primary mb-3" id="btnCrearProducto" data-bs-toggle="modal" data-bs-target="#modalProducto">
              Nuevo Producto
            </button>
            @endcan
            <div class="table-responsive">
              <table id="productsTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Imagen</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <br/>

          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal Crear/Editar Producto -->
<!-- Modal Crear/Editar Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formProducto" enctype="multipart/form-data">
      @csrf
      <input type="hidden" id="producto_id" name="producto_id">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProductoLabel">Nuevo Producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label for="name" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="name" name="name" required>
          </div>

          <div class="mb-3">
            <label for="category_id" class="form-label">Categoría</label>
            <select class="form-select" id="category_id" name="category_id" required>
              <option value="">-- Seleccione --</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="price" class="form-label">Precio</label>
            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
          </div>

          <div class="mb-3">
            <label for="quantity" class="form-label">Cantidad</label>
            <input type="number" class="form-control" id="quantity" name="quantity" required>
          </div>

          <div class="mb-3">
            <label for="images" class="form-label">Imagen (modelo)</label>
            <input type="file" class="form-control" id="images" name="images[]" accept="image/*" multiple>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnVerImagenProducto" style="display: none;">
              Ver imagen actual
            </button>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarProducto">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal para ver imagen -->
<style>
  #modalVerImagenProducto .modal-dialog {
    max-width: 400px;
  }
</style>
<div class="modal fade" id="modalVerImagenProducto" tabindex="-1" aria-labelledby="modalVerImagenProductoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body text-center">
        <img id="imgProductoModal" src="" alt="Imagen del producto" class="img-fluid rounded shadow" style="max-height: 300px;">
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
@vite('resources/js/product.js')
@endpush

