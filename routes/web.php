<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified','active'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::post('/profile/sessions/destroy', [ProfileController::class, 'destroySessions'])->name('profile.sessions.destroy');
    Route::delete('/profile/session/{id}', [ProfileController::class, 'destroySession'])->name('profile.session.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    //Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'active'])->group(function () {

    /*siempre tener en cuenta el ordenamiento ya que primero te cargaria esta ruta get definida antes del resource */
    Route::get('/categorias/data', [CategoryController::class, 'getData'])->name('categorias.data');
    Route::get('/categorias/select', [CategoryController::class, 'select']);
    Route::resource('categorias', CategoryController::class);

    Route::get('/customers/data', [CustomerController::class, 'getData'])->name('customers.data');
    Route::get('/customers/select', [CustomerController::class, 'select']);
    Route::resource('customers', CustomerController::class);

    Route::get('/suppliers/data', [SupplierController::class, 'getData'])->name('suppliers.data');
    Route::get('/suppliers/select', [SupplierController::class, 'select']);
    Route::resource('suppliers', SupplierController::class);

    Route::get('roles/permissions', [RoleController::class, 'getPermissions']);
    Route::get('/roles/list', [RoleController::class, 'list'])->name('roles.list');
    Route::get('/roles/data', [RoleController::class, 'getData'])->name('roles.data');
    Route::resource('roles', RoleController::class);

    Route::get('/users/data', [UserController::class, 'getData'])->name('users.data');
    Route::resource('users', UserController::class);

    Route::get('/products/list', [ProductController::class, 'list'])->name('products.list');
    Route::get('/products/data', [ProductController::class, 'getData'])->name('products.data');
    Route::post('/products/{product}/images/upload', [ProductController::class, 'uploadImages'])->name('products.images.upload');
    Route::post('/products/{product}/images/delete', [ProductController::class, 'deleteImage'])->name('products.images.delete');
    Route::post('/products/images/temp-upload', [ProductController::class, 'uploadTemp'])->name('products.images.temp-upload');
    Route::resource('products', ProductController::class);

    Route::get('/compras/data', [CompraController::class, 'getData'])->name('compras.data');
    Route::resource('compras', CompraController::class);
    Route::get('/compras/{id}/detalle', [CompraController::class, 'detalle'])->name('compras.detalle');
    //Route::get('compras/pdf/{id}', [CompraController::class, 'downloadPDF'])->name('compras.pdf');

    Route::get('/ventas/data', [VentaController::class, 'getData'])->name('ventas.data');
    Route::put('/ventas/detalles/{detalle}', [VentaController::class, 'updateDetail'])->name('ventas.detalles.update');
    Route::delete('/ventas/detalles/{detalle}', [VentaController::class, 'destroyDetail'])->name('ventas.detalles.destroy');
    Route::resource('ventas', VentaController::class);
    Route::get('/ventas/{id}/detalle', [VentaController::class, 'detalle'])->name('ventas.detalle');
    //Route::get('ventas/pdf/{id}', [VentaController::class, 'downloadPDF'])->name('ventas.pdf');

    Route::get('/inventories/daily', [InventoryController::class, 'dailyClosure'])->name('inventories.daily');
    Route::get('/inventories/daily/export', [InventoryController::class, 'exportDailyClosure'])->name('inventories.daily.export');
    Route::resource('/inventories', InventoryController::class)->only(['index']);

    Route::get('/dashboard/data', [DashboardController::class, 'getDashboardData'])->name('dashboard.data');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

});

require __DIR__.'/auth.php';
