@extends('layouts.app')

@section('title', 'Categoria')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Listado de Categorias</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="javascript: void(0);">Mantenimiento</a></li>
                                <li class="breadcrumb-item active">Categoria</li>
                            </ol>
                        </div>

                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">

                        <div class="card-body">
                            @can('administrar.categorias.create')
                            <button type="button" class="btn btn-primary mb-3" id="btnCrearCategoria" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                                Nueva Categoría
                            </button>
                            @endcan
                            <div class="table-responsive">
                                <table id="categoriasTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- DataTables llenará automáticamente esta tabla -->
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

<!-- Modal Crear/Editar Categoría (Velzon-style) -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-labelledby="modalCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formCategoria">
            @csrf
            <input type="hidden" id="categoria_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCategoriaLabel">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Ej: Electrónica" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Descripción opcional..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="hstack gap-2 justify-content-end">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnGuardarCategoria">Guardar</button>
                            </div>
                        </div>
                    </div> <!-- end row -->
                </div>
            </div>
        </form>
    </div>
</div>


@endsection

@push('scripts')
@vite('resources/js/categoria.js')
@endpush
