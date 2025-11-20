<div class="app-menu navbar-menu">
    @php
        $role = Auth::check() ? Auth::user()->getRoleNames()->first() : null;
    @endphp

    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a href="{{ route('dashboard') }}" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/bdys-dark.png') }}" alt="" height="45">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/bdys-dark.png') }}" alt="" height="70">
            </span>
        </a>
        <!-- Light Logo-->
        <a href="{{ route('dashboard') }}" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/bdys-light.png') }}" alt="" height="45">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/bdys-light.png') }}" alt="" height="70">
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
                        <a class="nav-link menu-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                            <i class="ri-dashboard-2-line"></i> <span>Dashboard</span>
                        </a>
                    </li>
                @endcan
                @canany(['administrar.categorias.index', 'administrar.productos.index', 'administrar.clientes.index',
                    'administrar.proveedores.index', 'administrar.usuarios.index', 'administrar.roles.index'])
                    {{-- <li class="menu-title"><span>Mantenimiento</span></li> --}}
                    @can('administrar.categorias.index')
                        <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('categorias.*') ? 'is-active' : '' }}" href="{{ route('categorias.index') }}"><i
                                    class="ri-folders-line"></i> <span>Categorias</span></a></li>
                    @endcan
                    @can('administrar.productos.index')
                        <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('products.*') ? 'is-active' : '' }}" href="{{ route('products.index') }}"><i
                                    class="ri-price-tag-3-line"></i> <span>Productos</span></a></li>
                    @endcan
                    @can('administrar.clientes.index')
                        <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('customers.*') ? 'is-active' : '' }}" href="{{ route('customers.index') }}"><i
                                    class="ri-store-3-line"></i> <span>Tiendas</span></a></li>
                    @endcan
                    @can('administrar.proveedores.index')
                        <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('suppliers.*') ? 'is-active' : '' }}" href="{{ route('suppliers.index') }}"><i
                                    class="ri-truck-line"></i> <span>Proveedor</span></a></li>
                    @endcan
                    @can('administrar.usuarios.index')
                        <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}"><i
                                    class="ri-team-line"></i> <span>Usuarios</span></a></li>
                    @endcan
                    @can('administrar.roles.index')
                        <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('roles.*') ? 'is-active' : '' }}" href="{{ route('roles.index') }}"><i
                                    class="ri-shield-user-line"></i> <span>Roles</span></a></li>
                    @endcan
                @endcanany

                {{-- <li class="menu-title"><span>Compra</span></li> --}}
                @can('administrar.compras.index')
                    <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('compras.*') ? 'is-active' : '' }}" href="{{ route('compras.index') }}"><i
                                class="ri-shopping-cart-2-line"></i> <span>Compras</span></a></li>
                @endcan

                {{-- <li class="menu-title"><span>Ventas</span></li> --}}
                @can('administrar.ventas.index')
                    <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('ventas.*') ? 'is-active' : '' }}" href="{{ route('ventas.index') }}"><i
                                class="ri-bill-line"></i> <span>Ventas</span></a></li>
                @endcan

                {{-- <li class="menu-title"><span>Inventario</span></li> --}}
                @can('administrar.inventarios.index')
                    <li class="nav-item"><a class="nav-link menu-link {{ request()->routeIs('inventories.*') ? 'is-active' : '' }}" href="{{ route('inventories.index') }}"><i
                                class="ri-calendar-check-line"></i> <span>Cierre Diario</span></a></li>
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
