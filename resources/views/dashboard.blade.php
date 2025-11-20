@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')



    <div class="page-content">

        <div class="container-fluid">



            <div class="row g-4">

                <div class="col-12">

                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">

                        <h4 class="mb-sm-0">Dashboard</h4>



                        <div class="page-title-right">

                            <ol class="breadcrumb m-0">

                                <li class="breadcrumb-item"><a href="javascript: void(0);">Inicio</a></li>

                                <li class="breadcrumb-item active">Dashboard</li>

                            </ol>

                        </div>

                    </div>

                </div>

            </div>



            <div class="row row-cols-xxl-5 row-cols-lg-3 row-cols-md-2 row-cols-1 g-3 mb-1">



                <div class="col">

                    <div class="card card-animate summary-card">

                        <div class="card-body pt-0">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <p class="text-muted text-uppercase fw-medium mb-3">Pedidos completados</p>

                                    <h2 class="mt-1 ff-secondary fw-semibold mb-3">

                                        <span class="counter-value" id="pedidosCompletados">0</span>
                                        <span class="text-muted mx-1">/</span>
                                        <span class="counter-value" id="pedidosTotal">0</span>

                                    </h2>

                                </div>

                                <div class="avatar-sm flex-shrink-0">

                                    <span class="avatar-title bg-soft-primary text-primary rounded-circle fs-2">

                                        <i class="ri-briefcase-line"></i>

                                    </span>

                                </div>

                            </div>

                            <div class="summary-meta text-muted">

                                <span
                                    class="badge bg-soft-primary text-primary d-inline-flex flex-column flex-md-row gap-1 gap-md-2 mb-3">

                                    <span><i class="ri-check-line align-middle me-1"></i> Porcentaje <span
                                            id="pedidosCompletionRate" class="ms-1">0%</span></span>

                                    <span><i class="ri-time-line align-middle me-1"></i> Pendientes: <span
                                            class="counter-value" id="pedidosPendientes">0</span></span>

                                </span>

                            </div>

                        </div>

                    </div>

                </div>



                <div class="col">



                    <div class="card card-animate summary-card">



                        <div class="card-body">



                            <div class="d-flex justify-content-between align-items-start">



                                <div>



                                    <p class="text-muted text-uppercase fw-medium mb-3">Total Compras</p>


                                    <h2 class="mt-1 ff-secondary fw-semibold mb-3">



                                        S/. <span class="counter-value" id="totalComprasMonto">0</span>



                                    </h2>



                                </div>



                                <div class="avatar-sm flex-shrink-0">



                                    <span class="avatar-title bg-soft-warning text-warning rounded-circle fs-2">



                                        <i class="ri-wallet-line"></i>



                                    </span>



                                </div>



                            </div>



                            <div class="summary-meta text-muted">



                                <span class="badge bg-soft-warning text-warning mb-3">



                                    <i class="ri-shopping-basket-2-line align-middle me-1"></i> Ordenes


                                    <span id="totalComprasTransacciones" class="ms-2">0</span>



                                </span>



                            </div>



                        </div>



                    </div>



                </div>







                <div class="col">



                    <div class="card card-animate summary-card">



                        <div class="card-body">



                            <div class="d-flex justify-content-between align-items-start">



                                <div>



                                    <p class="text-muted text-uppercase fw-medium mb-3">Total Ventas</p>


                                    <h2 class="mt-1 ff-secondary fw-semibold mb-3">



                                        S/. <span class="counter-value" id="totalVentasMonto">0</span>



                                    </h2>



                                </div>



                                <div class="avatar-sm flex-shrink-0">



                                    <span class="avatar-title bg-soft-danger text-danger rounded-circle fs-2">



                                        <i class="ri-money-dollar-circle-line"></i>



                                    </span>



                                </div>



                            </div>



                            <div class="summary-meta text-muted">



                                <span class="badge bg-soft-danger text-danger mb-3">



                                    <i class="ri-arrow-right-up-line align-middle me-1"></i> Pedidos



                                    <span id="totalVentasTransacciones" class="ms-2">0</span>



                                </span>



                            </div>



                        </div>



                    </div>



                </div>






                <div class="col">



                    <div class="card card-animate summary-card">



                        <div class="card-body">



                            <div class="d-flex justify-content-between align-items-start">



                                <div>



                                    <p class="text-muted text-uppercase fw-medium mb-3">Total Ganancia</p>


                                    <h2 class="mt-1 ff-secondary fw-semibold mb-3">



                                        S/. <span class="counter-value" id="totalGanancia">0</span>



                                    </h2>



                                </div>



                                <div class="avatar-sm flex-shrink-0">



                                    <span class="avatar-title bg-soft-success text-success rounded-circle fs-2">



                                        <i class="ri-line-chart-line"></i>



                                    </span>



                                </div>



                            </div>

                            <div class="summary-meta text-muted">

                                <span class="badge bg-soft-success text-success mb-3">

                                    <span><i class="ri-money-dollar-circle-line align-middle me-1"></i> Ganancia del Mes
                                    </span>

                            </div>

                        </div>



                    </div>



                </div>







                <div class="col">

                    <div class="card card-animate summary-card">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <p class="text-muted text-uppercase fw-medium mb-3">Avance vs. meta de ventas</p>

                                    <h2 class="mt-1 ff-secondary fw-semibold mb-3">

                                        <span class="counter-value" id="salesTargetProgress">0</span>%

                                    </h2>

                                </div>

                                <div class="avatar-sm flex-shrink-0">

                                    <span class="avatar-title bg-soft-info text-info rounded-circle fs-2">

                                        <i class="ri-time-line"></i>

                                    </span>

                                </div>

                            </div>

                            <div class="summary-meta text-muted">

                                <span
                                    class="badge bg-soft-info text-info d-inline-flex flex-column flex-lg-row gap-1 gap-lg-2 mb-3">

                                    <span><i class="ri-flag-line align-middle me-1"></i> Meta: <span
                                            id="salesTargetAmount">S/. 0.00</span></span>

                                    <span><i class="ri-hourglass-line align-middle me-1"></i> Restante: <span
                                            id="salesTargetRemaining">S/. 0.00</span></span>

                                </span>

                            </div>

                        </div>

                    </div>

                </div>


            </div>



            <div class="row g-3">

                <div class="col-lg-6">

                    <div class="card card-height-100">

                        <div class="card-header align-items-center d-flex flex-wrap gap-2">

                            <h4 class="card-title mb-0 flex-grow-1">Top 5 Tiendas con más Pedidos</h4>

                            <div class="flex-shrink-0">

                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold text-uppercase fs-12">Ordenar por:</span>
                                        <span class="text-muted">
                                            <span id="topClientesRangeLabel">Últimos 6 meses</span>
                                            <i class="mdi mdi-chevron-down ms-1"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item top-clientes-range" data-range="1m" href="#">Ultimo
                                            mes</a>
                                        <a class="dropdown-item top-clientes-range active" data-range="6m"
                                            href="#">Últimos 6 meses</a>
                                        <a class="dropdown-item top-clientes-range" data-range="12m"
                                            href="#">Últimos 12
                                            meses</a>
                                        <a class="dropdown-item top-clientes-range" data-range="ytd" href="#">Año
                                            en
                                            curso</a>
                                    </div>
                                </div>

                            </div>

                        </div>

                        <div class="card-body">
                            <div id="topClientesChart"></div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-6">

                    <div class="card card-height-100">

                        <div class="card-header align-items-center d-flex flex-wrap gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Distribucion de Ventas por Producto</h4>

                            <div class="flex-shrink-0">
                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold text-uppercase fs-12">Ordenar por:</span>
                                        <span class="text-muted">
                                            <span id="ventasDistribucionOrdenLabel">Últimos 6 meses</span>
                                            <i class="mdi mdi-chevron-down ms-1"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item ventas-distribucion-order" data-range="1m"
                                            href="#">Ultimo mes</a>
                                        <a class="dropdown-item ventas-distribucion-order" data-range="6m"
                                            href="#">Últimos 6 meses</a>
                                        <a class="dropdown-item ventas-distribucion-order" data-range="12m"
                                            href="#">Últimos 12 meses</a>
                                        <a class="dropdown-item ventas-distribucion-order" data-range="ytd"
                                            href="#">Año en curso</a>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="card-body">
                            <div id="ventasProductosChart" style="height:320px;"></div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="row g-3">
                <div class="col-xxl-6">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex flex-wrap gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Resumen de equilibrio</h4>
                            <div class="flex-shrink-0">
                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold text-uppercase fs-12">Ordenar por:</span>
                                        <span class="text-muted">
                                            <span id="ventasComprasOrdenLabel">Últimos 6 meses</span>
                                            <i class="mdi mdi-chevron-down ms-1"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item ventas-compras-order" data-order="1m"
                                            href="#">Ultimo mes</a>
                                        <a class="dropdown-item ventas-compras-order" data-order="6m"
                                            href="#">Últimos 6 meses</a>
                                        <a class="dropdown-item ventas-compras-order" data-order="12m"
                                            href="#">Últimos 12 meses</a>
                                        <a class="dropdown-item ventas-compras-order" data-order="ytd" href="#">Año
                                            en curso</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0">
                            <ul class="list-inline main-chart text-center mb-0">
                                <li class="list-inline-item chart-border-left me-0 border-0">
                                    <h4 class="text-primary mb-0">
                                        <span id="ventasComparacionTotal" class="fw-normal">S/ 0.00</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Ventas</span>
                                    </h4>
                                </li>
                                <li class="list-inline-item chart-border-left me-0">
                                    <h4 class="text-danger mb-0">
                                        <span id="comprasComparacionTotal" class="fw-normal">S/ 0.00</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Compras</span>
                                    </h4>
                                </li>
                                <li class="list-inline-item chart-border-left me-0">
                                    <h4 class="mb-0">
                                        <span id="ventasComparacionRatio" class="fw-normal">0%</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Beneficio</span>
                                    </h4>
                                </li>
                            </ul>
                            <div id="ventasComprasChart" class="apex-charts"
                                data-colors='["--vz-success", "--vz-danger"]'></div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-6">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex flex-wrap gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Ganancia</h4>
                            <div class="flex-shrink-0">
                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold text-uppercase fs-12">Ordenar por:</span>
                                        <span class="text-muted">
                                            <span id="ordersPerformanceRangeLabel">Últimos 6 meses</span>
                                            <i class="mdi mdi-chevron-down ms-1"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item orders-performance-range" data-range="1m"
                                            href="#">Ultimo mes</a>
                                        <a class="dropdown-item orders-performance-range active" data-range="6m"
                                            href="#">Últimos 6 meses</a>
                                        <a class="dropdown-item orders-performance-range" data-range="12m"
                                            href="#">Últimos 12 meses</a>
                                        <a class="dropdown-item orders-performance-range" data-range="ytd"
                                            href="#">Año en curso</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0">
                            <ul class="list-inline main-chart text-center mb-0">
                                <li class="list-inline-item chart-border-left me-0 border-0">
                                    <h4 class="text-primary mb-0">
                                        <span id="ordersPerformanceOrders" class="fw-normal">0</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Pedidos</span>
                                    </h4>
                                </li>
                                <li class="list-inline-item chart-border-left me-0">
                                    <h4 class="text-success mb-0">
                                        <span id="ordersPerformanceEarnings" class="fw-normal">S/ 0.00</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Ganancias</span>
                                    </h4>
                                </li>
                                <li class="list-inline-item chart-border-left me-0">
                                    <h4 class="text-danger mb-0">
                                        <span id="ordersPerformanceRefunds" class="fw-normal">0</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Anulados</span>
                                    </h4>
                                </li>
                                <li class="list-inline-item chart-border-left me-0">
                                    <h4 class="mb-0">
                                        <span id="ordersPerformanceConversion" class="fw-normal">0%</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Ratio de
                                            conversion</span>
                                    </h4>
                                </li>
                            </ul>
                            <div id="ordersPerformanceChart" class="apex-charts"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card card-animate h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0 flex-grow-1">Proyección de Ingresos (30 días)</h4>
                            <span class="badge bg-soft-info text-info">Forecast</span>
                        </div>
                        <div class="card-body">
                            <div id="revenuePredictionChart" class="apex-charts" style="min-height: 350px;"></div>
                            <div id="revenuePredictionEmpty" class="text-center text-muted py-5 d-none">
                                No hay predicciones disponibles.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card card-animate h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0 flex-grow-1">Top Productos Pronosticados</h4>
                            <span class="badge bg-soft-success text-success">Top 10</span>
                        </div>
                        <div class="card-body">
                            <div id="topPredictedProductsChart" class="apex-charts" style="min-height: 350px;"></div>
                            <div id="topPredictedProductsEmpty" class="text-center text-muted py-5 d-none">
                                No hay datos de productos pronosticados.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card card-animate mt-4">
                        <div class="card-header flex-wrap d-flex align-items-center justify-content-between gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Evaluación de Predicciones vs. Reales</h4>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-soft-primary text-primary">
                                    MAE: <span id="evalMae">0</span>
                                </span>
                                <span class="badge bg-soft-warning text-warning">
                                    RMSE: <span id="evalRmse">0</span>
                                </span>
                                <span class="badge bg-soft-success text-success">
                                    MAPE: <span id="evalMape">0%</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="revenueEvaluationChart" class="apex-charts" style="min-height: 360px;"></div>
                            <div id="revenueEvaluationEmpty" class="text-center text-muted py-5 d-none">
                                Aún no hay datos evaluados. Ejecuta las predicciones después de registrar los valores reales.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

@endsection


@push('scripts')
    <script src="{{ asset('assets/libs/echarts/echarts.min.js') }}"></script>
    <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

    @vite('resources/js/dashboard.js')
@endpush

@push('styles')
    <style>
        .page-title-box {
            margin-top: -42px !important;
        }
    </style>
@endpush
