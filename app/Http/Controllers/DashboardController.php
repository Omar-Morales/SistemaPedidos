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
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Ventas y compras por mes (últimos 6 meses para los gráficos)
        $ventas = Venta::select(DB::raw("SUM(total_price) as total"), DB::raw("TO_CHAR(sale_date, 'YYYY-MM') as month"))
            ->where('sale_date', '>=', $sixMonthsAgo)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $compras = Compra::select(DB::raw("SUM(total_cost) as total"), DB::raw("TO_CHAR(purchase_date, 'YYYY-MM') as month"))
            ->where('purchase_date', '>=', $sixMonthsAgo)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Totales históricos (se mantienen por si se necesitan en otros módulos)
        $stats = [
            'totalCategorias' => Category::count(),
            'totalProductos' => Product::count(),
            'totalCompras' => Compra::count(),
            'totalVentas' => Venta::count(),
            'totalUsuarios' => User::count(),
        ];

        // Métricas mensuales del mes en curso
        $ventasMensualesQuery = Venta::whereBetween('sale_date', [$startOfMonth, $endOfMonth]);
        $comprasMensualesQuery = Compra::whereBetween('purchase_date', [$startOfMonth, $endOfMonth]);

        $totalVentasMes = (clone $ventasMensualesQuery)->sum('total_price');
        $totalComprasMes = (clone $comprasMensualesQuery)->sum('total_cost');
        $detalleVentasBase = DetalleVenta::whereHas('venta', function ($query) use ($startOfMonth, $endOfMonth) {
            $query->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->where(function ($inner) {
                    $inner->whereNull('status')
                        ->orWhere('status', '!=', 'cancelled');
                });
        });

        $totalVentasTransaccionesMes = (clone $detalleVentasBase)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'cancelled');
            })
            ->count();
        $totalComprasTransaccionesMes = (clone $comprasMensualesQuery)->count();

        $totalGananciaMes = $totalVentasMes - $totalComprasMes;

        $completedStatuses = ['delivered', 'completed'];
        $detallePedidosQuery = clone $detalleVentasBase;

        $totalPedidosMes = (clone $detallePedidosQuery)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'cancelled');
            })
            ->count();

        $completedOrders = (clone $detallePedidosQuery)
            ->whereIn('status', $completedStatuses)
            ->count();

        $pendingOrders = max($totalPedidosMes - $completedOrders, 0);

        $completionRate = $totalPedidosMes > 0
            ? round(($completedOrders / $totalPedidosMes) * 100, 2)
            : 0;

        $monthlySalesTarget = (float) config('dashboard.monthly_sales_target', env('MONTHLY_SALES_TARGET', 1500000));
        $salesTargetProgress = $monthlySalesTarget > 0
            ? min(round(($totalVentasMes / $monthlySalesTarget) * 100, 2), 999.99)
            : 0;
        $salesTargetRemaining = max($monthlySalesTarget - $totalVentasMes, 0);

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

        $metrics = [
            'totalComprasMonto' => $totalComprasMes,
            'totalVentasMonto' => $totalVentasMes,
            'totalComprasTransacciones' => $totalComprasTransaccionesMes,
            'totalVentasTransacciones' => $totalVentasTransaccionesMes,
            'pedidosCompletados' => $completedOrders,
            'pedidosPendientes' => $pendingOrders,
            'pedidosTotal' => $totalPedidosMes,
            'pedidosCompletionRate' => $completionRate,
            'totalGanancia' => $totalGananciaMes,
            'salesTargetAmount' => $monthlySalesTarget,
            'salesTargetProgress' => $salesTargetProgress,
            'salesTargetRemaining' => $salesTargetRemaining,
        ];

        return response()->json([
            'ventas' => $ventas,
            'compras' => $compras,
            'metrics' => $metrics,
            'stats' => $stats,
            'ventasProductos' => $ventasProductos,
            'comprasProductos' => $comprasProductos,
            'topClientes' => $topClientes,
            'topProveedores' => $topProveedores,
        ]);
    }
}
