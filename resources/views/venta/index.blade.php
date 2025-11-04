@extends('layouts.app')

@section('title', 'Mantenimiento de Ventas')
@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Listado de Ventas</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript:void(0);">Mantenimiento</a></li>
                            <li class="breadcrumb-item active">Ventas</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        @can('administrar.ventas.create')
                        <button type="button" class="btn btn-primary mb-3" id="btnCrearVenta" data-bs-toggle="modal" data-bs-target="#modalVenta">
                            Nueva Venta
                        </button>
                        @endcan

                        <div class="table-responsive">
                            <table id="ventasTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Tipo Documento</th>
                                        <th>Usuario</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Monto Pagado</th>
                                        <th>Diferencia</th>
                                        <th>Tipo de Entrega</th>
                                        <th>Almacen</th>
                                        <th>Estado de Pedido</th>
                                        <th>Estado de Pago</th>
                                        <th>Metodo de Pago</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Crear/Editar Venta -->
<div class="modal fade" id="modalVenta" tabindex="-1" aria-labelledby="modalVentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formVenta">
            @csrf
            <input type="hidden" id="venta_id" name="venta_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVentaLabel">Nueva Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="customer_id" class="form-label">Cliente</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="tipodocumento_id" class="form-label">Tipo Documento</label>
                            <select class="form-select" id="tipodocumento_id" name="tipodocumento_id" required>
                                <option value="">-- Seleccione --</option>
                                @foreach ($tiposDocumento as $documento)
                                    <option value="{{ $documento->id }}">{{ $documento->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="payment_method" class="form-label">Metodo de Pago</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">-- Seleccione --</option>
                                <option value="cash">Efectivo</option>
                                <option value="card">Tarjeta</option>
                                <option value="transfer">Transferencia</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="warehouse" class="form-label">Almacen</label>
                            <select class="form-select" id="warehouse" name="warehouse" required>
                                <option value="">-- Seleccione --</option>
                                <option value="curva">Curva</option>
                                <option value="milla">Milla</option>
                                <option value="santa_carolina">Santa Carolina</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sale_date" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="sale_date" name="sale_date" value="{{ now()->format('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="delivery_type" class="form-label">Tipo de Entrega</label>
                            <select class="form-select" id="delivery_type" name="delivery_type" required>
                                <option value="">-- Seleccione --</option>
                                <option value="pickup">Recoge</option>
                                <option value="delivery">Enviar</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="status" name="status" value="pending">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="total_price" class="form-label">Total</label>
                            <input type="number" step="0.01" class="form-control" id="total_price" name="total_price" value="0.00" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="payment_status">Estado de Pago</label>
                            <select class="form-select" id="payment_status" name="payment_status" required>
                                <option value="pending" selected>Pendiente</option>
                                <option value="paid">Cancelado</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="amount_paid" name="amount_paid" value="0.00">

                    <div class="mb-1">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Productos</h5>
                            <button type="button" class="btn btn-sm btn-info" id="addProductRow">+ Agregar Producto</button>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-bordered table-sm" id="detalleVentaTableEditable">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Unidad</th>
                                        <th>Subtotal</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="detalleVentaBody"></tbody>
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
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" aria-labelledby="modalDetalleVentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleVentaLabel">Detalle de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <table id="detalleVentaTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="detalleVentaBodydos"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
</div>
</div>
@push('scripts')
    @vite('resources/js/venta.js')
@endpush
@endsection









