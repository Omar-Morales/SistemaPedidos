@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')



    <div class="page-content">

        <div class="container-fluid">



            <div class="row">

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

                        <div class="card-body">

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

                                    <span><i class="ri-money-dollar-circle-line align-middle me-1"></i> Ganancia del Mes </span>

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
                <div class="col-xxl-6">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex flex-wrap gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Resumen de equilibrio</h4>
                            <div class="flex-shrink-0">
                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold text-uppercase fs-12">Ordenar por:</span>
                                        <span class="text-muted">
                                            <span id="ventasComprasOrdenLabel">Ultimos 6 meses</span>
                                            <i class="mdi mdi-chevron-down ms-1"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item ventas-compras-order" data-order="6m" href="#">Ultimos 6 meses</a>
                                        <a class="dropdown-item ventas-compras-order" data-order="12m" href="#">Ultimos 12 meses</a>
                                        <a class="dropdown-item ventas-compras-order" data-order="ytd" href="#">Año en curso</a>
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
                            <div id="ventasComprasChart" class="apex-charts" data-colors='["--vz-success", "--vz-danger"]'></div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-6">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex flex-wrap gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Ganancia</h4>
                            <div class="flex-shrink-0">
                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold text-uppercase fs-12 text-muted">Ordenar por:</span>
                                        <span class="text-muted">
                                            <span id="ordersPerformanceRangeLabel">Todo</span>
                                            <i class="mdi mdi-chevron-down ms-1"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item orders-performance-range active" data-range="all" href="#">Todo</a>
                                        <a class="dropdown-item orders-performance-range" data-range="1m" href="#">Ultimo mes</a>
                                        <a class="dropdown-item orders-performance-range" data-range="6m" href="#">Ultimos 6 meses</a>
                                        <a class="dropdown-item orders-performance-range" data-range="1y" href="#">Ultimo año</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0">
                            <ul class="list-inline main-chart text-center mb-0">
                                <li class="list-inline-item chart-border-left me-0 border-0">
                                    <h4 class="text-primary mb-0">
                                        <span id="ordersPerformanceOrders" class="fw-normal">0</span>
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Ordenes</span>
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
                                        <span class="text-muted d-inline-block fs-13 align-middle ms-2">Ratio de conversion</span>
                                    </h4>
                                </li>
                            </ul>
                            <div id="ordersPerformanceChart" class="apex-charts"></div>
                        </div>
                    </div>
                </div>
            </div>



        <div class="row">

                <!-- Grafico de Ventas por Producto -->

                <div class="col-6">

                    <div class="card">

                        <div class="card-body">

                            <h4 class="card-title">Distribucion de Ventas por Producto</h4>

                            <div id="ventasProductosChart"></div>

                        </div>

                    </div>

                </div>

                <!-- Grafico de Compras por Producto -->

                <div class="col-6">

                    <div class="card">

                        <div class="card-body">

                            <h4 class="card-title">Distribucion de Compras por Producto</h4>

                            <div id="comprasProductosChart"></div>

                        </div>

                    </div>

                </div>

            </div>



            <div class="row">

                <!-- Grafico de Top Clientes -->

                <div class="col-6">

                    <div class="card">

                        <div class="card-body">

                            <h4 class="card-title">Top 5 Tiendas con Mayor Monto de Ventas ($)</h4>

                            <div id="topClientesChart"></div>

                        </div>

                    </div>

                </div>

                <!-- Grafico de Top Proveedores -->

                <div class="col-6">

                    <div class="card">

                        <div class="card-body">

                            <h4 class="card-title">Top 5 Proveedores con Mayor Monto de Compras ($)</h4>

                            <div id="topProveedoresChart"></div>

                        </div>

                    </div>

                </div>

            </div>



        </div>

    </div>

@endsection



@push('scripts')
    <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>



    @vite('resources/js/dashboard.js')
@endpush

@push('styles')
    <style>
        h2 {

            transition: opacity 0.3s ease-in-out;



        }



        .summary-card .card-body {

            display: flex;

            flex-direction: column;

            height: 100%;

        }



        .summary-card .summary-meta {

            margin-top: auto;

        }



        .summary-card .card-body {

            justify-content: space-between;

            gap: 0.75rem;

        }


        .summary-card .summary-meta {

            margin-top: 0;

        }

    </style>
@endpush






