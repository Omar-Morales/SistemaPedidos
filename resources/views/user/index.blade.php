@extends('layouts.app')

@section('title', 'Mantenimiento de Usuarios')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Listado de Usuarios</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript: void(0);">Mantenimiento</a></li>
                            <li class="breadcrumb-item active">Usuarios</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        @can('administrar.usuarios.create')
                        <button type="button" class="btn btn-primary mb-3" id="btnCrearUsuario" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                            Nuevo Usuario
                        </button>
                        @endcan
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Foto</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Teléfono</th>
                                        <th>Rol</th>
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

<!-- Modal Crear/Editar Usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg"> <!-- más ancho -->
        <form id="formUsuario" enctype="multipart/form-data">
            @csrf
            <input type="hidden" id="usuario_id" name="usuario_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUsuarioLabel">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Correo</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" >
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="phone" name="phone" maxlength="9">
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Rol</label>
                            <select class="form-select" id="role" name="role_id" required>
                                <option value="">Cargando roles...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="photo" class="form-label">Foto</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/jpg,image/gif">
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnVerLogo" style="display: none;">
                                Ver foto actual
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarUsuario">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Modal para ver logo -->
<style>
  #modalVerLogo .modal-dialog {
    max-width: 400px;
  }
</style>

<div class="modal fade" id="modalVerLogo" tabindex="-1" aria-labelledby="modalVerLogoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
    <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
    </div>
    <div class="modal-body text-center">
        <img id="imgLogoModal" src="" alt="Foto del usuario" class="img-fluid rounded shadow" style="max-height: 300px;">
    </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/user.js')
@endpush
