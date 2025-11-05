<div class="app-menu navbar-menu">
    @php
        $role = Auth::check() ? Auth::user()->getRoleNames()->first() : null;
    @endphp

    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a href="{{ route('dashboard') }}" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/bdys-dark.png') }}" alt="" height="40">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/bdys-dark.png') }}" alt="" height="60">
            </span>
        </a>
        <!-- Light Logo-->
        <a href="{{ route('dashboard') }}" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/bdys-light.png') }}" alt="" height="40">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/bdys-light.png') }}" alt="" height="60">
            </span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-horizontal-sm-hover"
            id="horizontal-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <!-- Botón cerrar menú solo en móviles -->
    <button type="button" class="btn btn-sm btn-close-menu d-md-none" id="close-menu-btn">
        <i class="ri-close-line fs-15"></i>
    </button>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">
                @can('administrar.dashboard.index')
                    {{-- <li class="menu-title"><span>Dashboard</span></li> --}}
                    <li class="nav-item">
                        <a class="nav-link menu-link" href="{{ route('dashboard') }}">
                            <i class="ri-window-line"></i> <span>Dashboard</span>
                        </a>
                    </li>
                @endcan
                @canany(['administrar.categorias.index', 'administrar.productos.index', 'administrar.clientes.index',
                    'administrar.proveedores.index', 'administrar.usuarios.index', 'administrar.roles.index'])
                    {{-- <li class="menu-title"><span>Mantenimiento</span></li> --}}
                    @can('administrar.categorias.index')
                        <li class="nav-item"><a class="nav-link menu-link" href="{{ route('categorias.index') }}"><i
                                    class="ri-flag-2-line"></i> <span>Categorias</span></a></li>
                    @endcan
                    @can('administrar.productos.index')
                        <li class="nav-item"><a class="nav-link menu-link" href="{{ route('products.index') }}"><i
                                    class="ri-pushpin-fill"></i> <span>Productos</span></a></li>
                    @endcan
                    @can('administrar.clientes.index')
                        <li class="nav-item"><a class="nav-link menu-link" href="{{ route('customers.index') }}"><i
                                    class="ri-user-5-fill"></i> <span>Tiendas</span></a></li>
                    @endcan
                    @can('administrar.proveedores.index')
                        <li class="nav-item"><a class="nav-link menu-link" href="{{ route('suppliers.index') }}"><i
                                    class="ri-account-box-line"></i> <span>Proveedor</span></a></li>
                    @endcan
                    @can('administrar.usuarios.index')
                        <li class="nav-item"><a class="nav-link menu-link" href="{{ route('users.index') }}"><i
                                    class="ri-honour-line"></i> <span>Usuarios</span></a></li>
                    @endcan
                    @can('administrar.roles.index')
                        <li class="nav-item"><a class="nav-link menu-link" href="{{ route('roles.index') }}"><i
                                    class="ri-checkbox-line"></i> <span>Roles</span></a></li>
                    @endcan
                @endcanany

                {{-- <li class="menu-title"><span>Compra</span></li> --}}
                @can('administrar.compras.index')
                    <li class="nav-item"><a class="nav-link menu-link" href="{{ route('compras.index') }}"><i
                                class="ri-red-packet-fill"></i> <span>Compras</span></a></li>
                @endcan

                {{-- <li class="menu-title"><span>Ventas</span></li> --}}
                @can('administrar.ventas.index')
                    <li class="nav-item"><a class="nav-link menu-link" href="{{ route('ventas.index') }}"><i
                                class="ri-store-line"></i> <span>Ventas</span></a></li>
                @endcan

                {{-- <li class="menu-title"><span>Inventario</span></li> --}}
                @can('administrar.inventarios.index')
                    <li class="nav-item"><a class="nav-link menu-link" href="{{ route('inventories.index') }}"><i
                                class="ri-calendar-check-line"></i> <span>Cierre Diario</span></a></li>
                @endcan

                {{-- <li class="menu-title"><span>Transaccion</span></li> --}}
                @can('administrar.transacciones.index')
                    <li class="nav-item"><a class="nav-link menu-link" href="{{ route('transactions.index') }}"><i
                                class="ri-money-dollar-circle-line"></i> <span>Listado Transaccion</span></a></li>
                @endcan

            </ul>
        </div>
    </div>

    <div class="sidebar-background"></div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const closeBtn = document.getElementById("close-menu-btn");
        const sidebarBg = document.querySelector(".sidebar-background");

        // Botón de cerrar (X)
        closeBtn?.addEventListener("click", function() {
            document.body.classList.remove("vertical-sidebar-enable");
        });

        // Clic fuera del menú (overlay)
        sidebarBg?.addEventListener("click", function() {
            document.body.classList.remove("vertical-sidebar-enable");
        });
    });
</script>
