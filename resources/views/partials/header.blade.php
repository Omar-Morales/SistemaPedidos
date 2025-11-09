<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box horizontal-logo">
                    <a href="{{ route('dashboard') }}" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/bdys-dark.png') }}" alt="" height="40">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ asset('assets/images/bdys-dark.png') }}" alt="" height="60">
                        </span>
                    </a>

                    <a href="{{ route('dashboard') }}" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/bdys-light.png') }}" alt="" height="40">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ asset('assets/images/bdys-light.png') }}" alt="" height="60">
                        </span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item horizontal-menu-btn topnav-hamburger"
                    id="topnav-hamburger-icon">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <!-- App Search-->
                <form class="app-search d-none d-md-block">
                    <div class="position-relative">
                        <input type="text" class="form-control" placeholder="Search..." autocomplete="off"
                            id="search-options" value="">
                        <span class="mdi mdi-magnify search-widget-icon"></span>
                        <span class="mdi mdi-close-circle search-widget-icon search-widget-icon-close d-none"
                            id="search-close-options"></span>
                    </div>
                    <div class="dropdown-menu dropdown-menu-lg" id="search-dropdown">
                        <div data-simplebar style="max-height: 320px;">
                            <!-- item-->
                            <div class="dropdown-header mt-2">
                                <h6 class="text-overflow text-muted mb-1 text-uppercase">Pages</h6>
                            </div>

                            <div class="notification-list">

                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="d-flex align-items-center">

                <div class="dropdown d-md-none topbar-head-dropdown header-item">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle"
                        id="page-header-search-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <i class="bx bx-search fs-22"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                        aria-labelledby="page-header-search-dropdown">
                        <form class="p-3">
                            <div class="form-group m-0">
                                <div class="input-group">
                                    <input type="text" id="search-mobile" class="form-control"
                                        placeholder="Search ..." aria-label="Recipient's username">
                                    <button class="btn btn-primary" type="submit"><i
                                            class="mdi mdi-magnify"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle"
                        data-toggle="fullscreen" id="fullscreen-toggle">
                        <i class='bx bx-fullscreen fs-22'></i>
                    </button>
                </div>

                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button"
                        class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle light-dark-mode">
                        <i class='bx bx-moon fs-22'></i>
                    </button>
                </div>

                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn" id="page-header-user-dropdown" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user" src="{{ $photoUrl }}"
                                alt="Avatar de {{ $user->name }}">
                            <span class="text-start ms-xl-2">
                                <span
                                    class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ $user->name ?? 'Usuario' }}</span>
                                <span class="d-none d-xl-block ms-1 fs-12 text-muted user-name-sub-text">
                                    {{ $user->role->name ?? 'Usuario' }}
                                </span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Bienvenido, {{ $user->name ?? 'Usuario' }}!</h6>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Perfil</span>
                        </a>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Cerrar Sesión</span>
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    const paginas = [{
            nombre: 'Dashboard',
            url: "{{ route('dashboard') }}"
        },
        {
            nombre: 'Categoría',
            url: "{{ route('categorias.index') }}"
        },
        {
            nombre: 'Producto',
            url: "{{ route('products.index') }}"
        },
        {
            nombre: 'Tienda',
            url: "{{ route('customers.index') }}"
        },
        {
            nombre: 'Proveedor',
            url: "{{ route('suppliers.index') }}"
        },
        {
            nombre: 'Usuario',
            url: "{{ route('users.index') }}"
        },
        {
            nombre: 'Roles',
            url: "{{ route('roles.index') }}"
        },
        {
            nombre: 'Compras',
            url: "{{ route('compras.index') }}"
        },
        {
            nombre: 'Ventas',
            url: "{{ route('ventas.index') }}"
        },
        {
            nombre: 'Inventario',
            url: "{{ route('inventories.index') }}"
        }
    ];

    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('search-options');
        const dropdown = document.getElementById('search-dropdown');
        const dropdownList = dropdown.querySelector('.notification-list');

        //pantallas grandes
        input.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            dropdownList.innerHTML = '';

            if (query.length === 0) {
                dropdown.classList.remove('show');
                return;
            }

            const resultados = paginas.filter(p => p.nombre.toLowerCase().includes(query));

            if (resultados.length > 0) {
                resultados.forEach(p => {
                    const item = document.createElement('a');
                    item.href = p.url;
                    item.className = 'dropdown-item notify-item py-2';
                    item.innerHTML = `
                        <div class="d-flex align-items-center">
                            <div class="flex-1">${p.nombre}</div>
                        </div>`;
                    dropdownList.appendChild(item);
                });
            } else {
                const noResult = document.createElement('div');
                noResult.className = 'dropdown-item text-muted';
                noResult.innerText = 'No se encontraron resultados';
                dropdownList.appendChild(noResult);
            }

            dropdown.classList.add('show');
        });

        const closeBtn = document.getElementById('search-close-options');
        closeBtn.addEventListener('click', function() {
            input.value = '';
            dropdownList.innerHTML = '';
            dropdown.classList.remove('show');
        });

        //pantallas de celulares

        const mobileInput = document.getElementById('search-mobile');
        const mobileForm = mobileInput.closest('form');

        mobileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = mobileInput.value.toLowerCase();

            const resultado = paginas.find(p => p.nombre.toLowerCase().includes(query));

            if (resultado) {
                window.location.href = resultado.url;
            } else {
                alert('No se encontró ninguna coincidencia.');
            }
        });

    });
</script>
