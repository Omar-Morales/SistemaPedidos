<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
    $request->authenticate();
    $request->session()->regenerate();

    $user = $request->user();

    $permissionsRoutes = [
        'administrar.dashboard.index' => 'dashboard',
        'administrar.categorias.index' => 'categorias.index',
        'administrar.productos.index' => 'products.index',
        'administrar.clientes.index' => 'customers.index',
        'administrar.proveedores.index' => 'suppliers.index',
        'administrar.usuarios.index' => 'users.index',
        'administrar.roles.index' => 'roles.index',
        'administrar.compras.index' => 'compras.index',
        'administrar.ventas.index' => 'ventas.index',
        'administrar.inventarios.index' => 'inventories.index',
    ];

    foreach ($permissionsRoutes as $permission => $route) {
        if ($user->can($permission)) {
            return redirect()->route($route);
        }
    }

    // No tiene permisos
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    abort(403, 'No tienes permiso para acceder al sistema.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
