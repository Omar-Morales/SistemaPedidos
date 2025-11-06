<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Venta;
use App\Models\Compra;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\DetalleVenta;
use App\Models\DetalleCompra;

class DashboardController extends Controller
{
        public function __construct()
    {
        //🔹 Solo el ADMINISTRADOR y MANTENEDOR pueden acceder a este controlador
        $this->middleware(['auth', 'permission:administrar.dashboard.index'])->only('index', 'getDashboardData');
    }

    public function index()
    {
        return view('dashboard');
    }

    public function getDashboardData()
    {
        $now = Carbon::now();
        $sixMonthsAgo = $now->copy()->subMonths(6);

        // Ventas y compras por mes
        $ventas = Venta::select(DB::raw("SUM(total_price) as total"), DB::raw("TO_CHAR(sale_date, 'YYYY-MM') as month"))
            ->where('sale_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $compras = Compra::select(DB::raw("SUM(total_cost) as total"), DB::raw("TO_CHAR(purchase_date, 'YYYY-MM') as month"))
            ->where('purchase_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Totales
        $stats = [
            'totalCategorias' => Category::count(),
            'totalProductos' => Product::count(),
            'totalCompras' => Compra::count(),
            'totalVentas' => Venta::count(),
            'totalUsuarios' => User::count(),
        ];

        $totalVentas = Venta::sum('total_price');
        $totalCompras = Compra::sum('total_cost');

        // Top productos
        $ventasProductos = DetalleVenta::select(
                'products.name as producto',
                DB::raw('SUM(detalle_ventas.quantity) as total_vendido')
            )
            ->join('products', 'detalle_ventas.product_id', '=', 'products.id')
            ->groupBy('products.name')
            ->orderByDesc('total_vendido')
            ->limit(5)
            ->get();

        $comprasProductos = DetalleCompra::select(
                'products.name as producto',
                DB::raw('SUM(detalle_compras.quantity) as total_comprado')
            )
            ->join('products', 'detalle_compras.product_id', '=', 'products.id')
            ->groupBy('products.name')
            ->orderByDesc('total_comprado')
            ->limit(5)
            ->get();

        // Top clientes y proveedores
        $topClientes = Venta::select(
                'customers.name as cliente',
                DB::raw('SUM(ventas.total_price) as total_ventas')
            )
            ->join('customers', 'ventas.customer_id', '=', 'customers.id')
            ->groupBy('customers.name')
            ->orderByDesc('total_ventas')
            ->limit(5)
            ->get();

        $topProveedores = Compra::select(
                'suppliers.name as proveedor',
                DB::raw('SUM(compras.total_cost) as total_compras')
            )
            ->join('suppliers', 'compras.supplier_id', '=', 'suppliers.id')
            ->groupBy('suppliers.name')
            ->orderByDesc('total_compras')
            ->limit(5)
            ->get();

        return response()->json([
            'ventas' => $ventas,
            'compras' => $compras,
            'totalVentas' => $totalVentas,
            'totalCompras' => $totalCompras,
            'stats' => $stats,
            'ventasProductos' => $ventasProductos,
            'comprasProductos' => $comprasProductos,
            'topClientes' => $topClientes,
            'topProveedores' => $topProveedores,
        ]);
    }
}
