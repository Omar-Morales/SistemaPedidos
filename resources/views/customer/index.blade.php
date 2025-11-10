@extends('layouts.app')

@section('title', 'Mantenimiento de Tiendas')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Listado de Tiendas</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript:void(0);">Mantenimiento</a></li>
                            <li class="breadcrumb-item active">Tiendas</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">

                    <div class="card-body">
                        @can('administrar.clientes.create')
                            <button type="button" class="btn btn-primary mb-3" id="btnCrearTienda" data-bs-toggle="modal" data-bs-target="#modalTienda">
                            Nueva Tienda
                        </button>
                        @endcan
                        <div class="table-responsive">
                            <table id="customersTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>RUC</th>
                                        <th>Nombre</th>
                                        <th>Ubicacion</th>
                                        <th>Telefono</th>
                                        <!--<th>Estado</th>-->
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DataTables llenará esta tabla -->
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

<!-- Modal Crear/Editar Tienda -->
<div class="modal fade" id="modalTienda" tabindex="-1" aria-labelledby="modalTiendaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formTienda">
            @csrf
            <input type="hidden" id="tienda_id" name="tienda_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTiendaLabel">Nueva Tienda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="ruc" class="form-label">RUC</label>
                        <input type="text" class="form-control" id="ruc" name="ruc" maxlength="11" required>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Ubicacion</label>
                        <select class="form-select" id="location" name="location" required>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telefono</label>
                        <input type="text" class="form-control" id="phone" name="phone" maxlength="9">
                    </div>
                    <!--<div class="mb-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">-- Seleccione --</option>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>-->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarTienda">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
@vite('resources/js/customer.js')
@endpush
