@extends('layouts.app')

@section('title', 'Cierre Diario')

@section('content')
<div class="page-content">
    <div class="container-fluid"
        data-fetch-url="{{ route('inventories.daily') }}"
        data-export-url="{{ route('inventories.daily.export') }}"
        data-default-warehouse="{{ $defaultWarehouse }}"
        data-default-date="{{ $defaultDate }}"
    >
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Cierre Diario</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="#">Cierre Diario</a></li>
                            <li class="breadcrumb-item active">Resumen</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card h-100">
                    <div class="card-body pb-2">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-4 col-md-6">
                                <label for="warehouseSelect" class="form-label fw-semibold text-muted text-uppercase small">Almac&eacute;n</label>
                                <select class="form-select" id="warehouseSelect">
                                    @foreach($warehouses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label for="closureDate" class="form-label fw-semibold text-muted text-uppercase small">Fecha</label>
                                <input type="date" id="closureDate" class="form-control" value="{{ $defaultDate }}">
                            </div>
                            <div class="col-lg-5 col-md-12">
                                <div class="row g-2 ms-lg-auto">
                                    <div class="col-12 col-lg-6">
                                        <button id="generateClosureBtn" class="btn btn-primary w-100 py-2">
                                            <i class="ri-links-line me-1"></i> Generar Cierre
                                        </button>
                                    </div>
                                    @can('administrar.inventarios.export')
                                        <div class="col-12 col-lg-6">
                                            <a id="exportClosureBtn" href="#" class="btn btn-outline-success w-100 py-2 d-flex align-items-center justify-content-center gap-1">
                                                <i class="ri-download-2-line"></i> Exportar Excel
                                            </a>
                                        </div>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4" id="closureSummaryRow">
            <div class="col-xxl-3 col-md-6">
                <div class="card mini-stats-wid h-100">
                    <div class="card-body">
                        <p class="text-uppercase fw-semibold text-muted mb-2">Total de Productos</p>
                        <h4 class="fw-semibold mb-1" id="summary-total-orders">0</h4>
                        <p class="text-muted mb-0 small" id="summary-total-orders-desc">Productos registrados en el d&iacute;a</p>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card mini-stats-wid h-100">
                    <div class="card-body">
                        <p class="text-uppercase fw-semibold text-muted mb-2">Productos Pagados</p>
                        <h4 class="fw-semibold mb-1 text-success" id="summary-paid-orders">0</h4>
                        <p class="text-muted mb-0 small" id="summary-paid-orders-desc">Productos completamente pagados</p>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card mini-stats-wid h-100">
                    <div class="card-body">
                        <p class="text-uppercase fw-semibold text-muted mb-2">Productos Pendientes</p>
                        <h4 class="fw-semibold mb-1 text-warning" id="summary-pending-orders">0</h4>
                        <p class="text-muted mb-0 small" id="summary-pending-orders-desc">Productos con saldo por cobrar</p>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card mini-stats-wid h-100">
                    <div class="card-body">
                        <p class="text-uppercase fw-semibold text-muted mb-2">Ingresos del d&iacute;a</p>
                        <h4 class="fw-semibold mb-1 text-primary" id="summary-income">S/ 0.00</h4>
                        <p class="text-muted mb-0 small" id="summary-income-desc">Incluye todos los m&eacute;todos de pago</p>
                        <div class="mt-2 small text-muted" id="summary-income-extra"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body pt-2">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Cliente</th>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Unidad</th>
                                        <th>Estado de Pago</th>
                                        <th>M&eacute;todo</th>
                                        <th>Total (S/)</th>
                                        <th>Pagado (S/)</th>
                                        <th>Pendiente (S/)</th>
                                    </tr>
                                </thead>
                                <tbody id="closureTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            Selecciona filtros y genera el cierre para ver resultados.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="closureTableEmpty" class="alert alert-warning mt-3 d-none">
                            No se registraron ventas en efectivo para los filtros seleccionados.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Cierres Recientes</h5>
                        <p class="text-muted mb-0 small">Historial autom&aacute;tico de cierres por fecha y almac&eacute;n.</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Almac&eacute;n</th>
                                        <th>Total Pedidos</th>
                                        <th>Pagados</th>
                                        <th>Pendientes</th>
                                        <th>Ingresos (S/)</th>
                                        <th>Por Cobrar (S/)</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No hay datos para mostrar todav&iacute;a.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/inventorie.js')
@endpush
