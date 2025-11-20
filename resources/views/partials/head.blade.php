<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="Sistema de Gestión de Pedidos" name="description">
<meta content="AnderCode" name="author">
<title>@yield('title', 'AnderCode - Sistema de Gestión de Pedidos')</title>

<!-- Icono del sitio -->
<script>
  (function() {
    const mode = localStorage.getItem("darkMode") === "enabled" ? "dark" : "light";
    document.documentElement.setAttribute("data-layout-mode", mode);
  })();
</script>
<link rel="shortcut icon" href="{{ asset('assets/images/bdys-light.png') }}">
<!-- Scripts de configuración -->
<script src="{{ asset('assets/js/personalizado.js') }}"></script>
<script src="{{ asset('assets/js/layout.js') }}"></script>
<!-- Archivos CSS -->
<link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('assets/css/custom.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('assets/css/personalizado.css') }}" rel="stylesheet" type="text/css">
