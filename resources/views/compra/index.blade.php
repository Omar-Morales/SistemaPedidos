@extends('layouts.app')

@section('title', 'Mantenimiento de Compras')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Listado de Compras</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript:void(0);">Mantenimiento</a></li>
                            <li class="breadcrumb-item active">Compras</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">

                    <div class="card-body">
                        @can('administrar.compras.create')
                        <button type="button" class="btn btn-primary mb-3" id="btnCrearCompra" data-bs-toggle="modal" data-bs-target="#modalCompra">
                            Nueva Compra
                        </button>
                        @endcan
                        <div class="table-responsive">
                            <table id="comprasTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Proveedor</th>
                                        <th>Tipo Compra</th>
                                        <th>Codigo de Compra</th>
                                        <th>Usuario</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DataTable llenará esto -->
                                </tbody>
                            </table>
                        </div>
                        <br/>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Crear/Editar Compra-->
<div class="modal fade" id="modalCompra" tabindex="-1" aria-labelledby="modalCompraLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg"> <!-- Aquí aplicamos modal-lg -->
        <form id="formCompra">
            @csrf
            <input type="hidden" id="compra_id" name="compra_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCompraLabel">Nueva Compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        
                        <div class="col-md-4">
                            <label for="purchase_date" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date" required>
                        </div>

                        <div class="col-md-4">
                            <label for="supplier_id" class="form-label">Proveedor</label>
                            <select class="form-select" id="supplier_id" name="supplier_id" required>
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="tipodocumento_id" class="form-label">Tipo Compra</label>
                            <select class="form-select" id="tipodocumento_id" name="tipodocumento_id" required>
                                <option value="">-- Seleccione --</option>
                                @foreach ($tiposDocumento as $documento)
                                <option value="{{ $documento->id }}">{{ $documento->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="codigo_numero" class="form-label">Código de Compra</label>
                            <input type="number" class="form-control" id="codigo_numero" name="codigo_numero" min="0" step="1">
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">-- Seleccione --</option>
                                <option value="pending">Pendiente</option>
                                <option value="completed">Completada</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                        <label for="total_cost" class="form-label">Total</label>
                        <input type="number" class="form-control" id="total_cost" name="total_cost" required readonly>
                        </div>
                    </div>

                    <div class="mb-1">
                        <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Productos</h5>
                        <button type="button" class="btn btn-sm btn-info mb-2" id="addProductRow">+ Agregar Producto</button>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-bordered table-sm" id="detalleCompraTableEditable">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Costo Unitario</th>
                                        <th>Subtotal</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="detalleCompraBody">
                                    <!-- Las filas de productos se agregarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Modal Ver Detalle -->
<div class="modal fade" id="modalDetalleCompra" tabindex="-1" aria-labelledby="modalDetalleCompraLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
          <h5 class="modal-title" id="modalDetalleCompraLabel">Detalle de Compra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <table id="detalleCompraTable" class="table table-bordered">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Costo Unitario</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody id="detalleCompraBodydos">
                <!-- Se llenará con JS -->
            </tbody>
        </table>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

@endsection
@push('styles')
    <style>
            #modalCompra .select2-container {
        z-index: 9999 !important;
        position: relative !important;
    }

    #modalCompra .select2-dropdown {
        position: absolute !important;
    }

    #modalCompra .modal-content {
        max-height: 90vh;
        overflow-y: auto;
    }
    </style>
@endpush
@push('scripts')
@vite('resources/js/compra.js')
@endpush

