@extends('layouts.app')

@section('title', 'Administración de Roles')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Listado de Roles</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="#">Seguridad</a></li>
                            <li class="breadcrumb-item active">Roles</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>


<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                @can('administrar.roles.create')
                <button type="button" class="btn btn-primary mb-3" id="btnCrearRol" data-bs-toggle="modal" data-bs-target="#modalRol">
                    Nuevo Rol
                </button>
                @endcan

                <div class="table-responsive">
                    <table id="rolesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Rol</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables llenará esta tabla -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
</div>

<!-- Modal Crear/Editar Rol -->
<div class="modal fade" id="modalRol" tabindex="-1" aria-labelledby="modalRolLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formRol">
            @csrf
            <input type="hidden" id="rol_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRolLabel">Nuevo Rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nombreRol" class="form-label">Nombre del Rol</label>
                        <input type="text" class="form-control" id="nombreRol" name="name" required>
                    </div>

                    <div class="mb-3">
                        <div class="hstack gap-3 mb-1">
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWithicon2" aria-expanded="false" aria-controls="collapseWithicon2">
                        <i class="ri-arrow-down-circle-line fs-10"></i>
                    </button>
                    </div>
                    <div class="collapse show collapse-init-hide" id="collapseWithicon2">
                    <div class="card mb-1">
                        <div class="card-body row" id="listaPermisos" >>

                        </div>
                    </div>
                    </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/rol.js') {{-- Este será tu archivo JS para lógica de roles --}}
@endpush

@push('styles')
<style>
.collapse-init-hide {
    display: none !important;
}
</style>
@endpush
