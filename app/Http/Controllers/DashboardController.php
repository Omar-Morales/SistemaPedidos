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
        $comparisonStart = $now->copy()->subMonths(11)->startOfMonth();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Ventas y compras por mes (últimos 6 meses para los gráficos)
        $ventas = Venta::select(DB::raw("SUM(total_price) as total"), DB::raw("TO_CHAR(sale_date, 'YYYY-MM') as month"))
            ->where('sale_date', '>=', $comparisonStart)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $compras = Compra::select(DB::raw("SUM(total_cost) as total"), DB::raw("TO_CHAR(purchase_date, 'YYYY-MM') as month"))
            ->where('purchase_date', '>=', $comparisonStart)
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

        // Top productos distribuci��n por rango
        $distributionRanges = [
            '1m' => $now->copy()->startOfMonth(),
            '6m' => $now->copy()->subMonths(5)->startOfMonth(),
            '1y' => $now->copy()->subMonths(11)->startOfMonth(),
        ];

        $ventasProductosByRange = [];
        $comprasProductosByRange = [];

        foreach ($distributionRanges as $key => $startDate) {
            $ventasProductosByRange[$key] = $this->topVentasProductosDesde($startDate);
            $comprasProductosByRange[$key] = $this->topComprasProductosDesde($startDate);
        }

        $ventasProductos = $ventasProductosByRange['6m'] ?? collect();
        $comprasProductos = $comprasProductosByRange['6m'] ?? collect();

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

        $monthsRange = [];
        $cursor = $comparisonStart->copy();
        while ($cursor <= $endOfMonth) {
            $monthsRange[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $ordersData = DetalleVenta::select(
                DB::raw("TO_CHAR(ventas.sale_date, 'YYYY-MM') as month"),
                DB::raw("SUM(CASE WHEN detalle_ventas.status IS NULL OR detalle_ventas.status != 'cancelled' THEN 1 ELSE 0 END) as orders"),
                DB::raw("SUM(CASE WHEN detalle_ventas.status = 'cancelled' THEN 1 ELSE 0 END) as refunds")
            )
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.sale_id')
            ->where('ventas.sale_date', '>=', $comparisonStart)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $ventasMap = $ventas->pluck('total', 'month');
        $comprasMap = $compras->pluck('total', 'month');
        $ordersSeries = [];
        $earningsSeries = [];
        $refundsSeries = [];
        foreach ($monthsRange as $month) {
            $ventasTotal = (float) ($ventasMap[$month] ?? 0);
            $comprasTotal = (float) ($comprasMap[$month] ?? 0);
            $ordersSeries[] = (int) ($ordersData[$month]->orders ?? 0);
            $refundsSeries[] = (int) ($ordersData[$month]->refunds ?? 0);
            $earningsSeries[] = $ventasTotal - $comprasTotal;
        }

        $ordersTotal = array_sum($ordersSeries);
        $earningsTotal = array_sum($earningsSeries);
        $refundsTotal = array_sum($refundsSeries);
        $conversionRatio = $ordersTotal > 0 ? (($ordersTotal - $refundsTotal) / $ordersTotal) * 100 : 0;

        $ordersSummary = [
            'months' => $monthsRange,
            'orders' => $ordersSeries,
            'earnings' => $earningsSeries,
            'refunds' => $refundsSeries,
            'totals' => [
                'orders' => $ordersTotal,
                'earnings' => $earningsTotal,
                'refunds' => $refundsTotal,
                'conversion' => round($conversionRatio, 2),
            ],
        ];

        return response()->json([
            'ventas' => $ventas,
            'compras' => $compras,
            'monthsRange' => $monthsRange,
            'metrics' => $metrics,
            'stats' => $stats,
            'ventasProductos' => $ventasProductos,
            'comprasProductos' => $comprasProductos,
            'ventasProductosByRange' => $ventasProductosByRange,
            'comprasProductosByRange' => $comprasProductosByRange,
            'topClientes' => $topClientes,
            'topProveedores' => $topProveedores,
            'ordersSummary' => $ordersSummary,
        ]);
    }

    protected function topVentasProductosDesde(Carbon $startDate, int $limit = 5)
    {
        return DetalleVenta::select(
                'products.name as producto',
                DB::raw('SUM(detalle_ventas.quantity) as total')
            )
            ->join('products', 'detalle_ventas.product_id', '=', 'products.id')
            ->join('ventas', 'detalle_ventas.sale_id', '=', 'ventas.id')
            ->where('ventas.sale_date', '>=', $startDate)
            ->groupBy('products.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'producto' => $row->producto,
                    'total' => (int) $row->total,
                ];
            })
            ->values();
    }

    protected function topComprasProductosDesde(Carbon $startDate, int $limit = 5)
    {
        return DetalleCompra::select(
                'products.name as producto',
                DB::raw('SUM(detalle_compras.quantity) as total')
            )
            ->join('products', 'detalle_compras.product_id', '=', 'products.id')
            ->join('compras', 'detalle_compras.purchase_id', '=', 'compras.id')
            ->where('compras.purchase_date', '>=', $startDate)
            ->groupBy('products.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'producto' => $row->producto,
                    'total' => (int) $row->total,
                ];
            })
            ->values();
    }
}
