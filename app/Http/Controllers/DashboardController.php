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
use Throwable;

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
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereIn('status', ['completed', 'pending']);
            })
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
        $comprasMensualesQuery = Compra::whereBetween('purchase_date', [$startOfMonth, $endOfMonth])
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereIn('status', ['completed', 'pending']);
            });

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
            '12m' => $now->copy()->subMonths(11)->startOfMonth(),
            'ytd' => $now->copy()->startOfYear(),
        ];

        $ventasProductosByRange = [];
        foreach ($distributionRanges as $key => $startDate) {
            $ventasProductosByRange[$key] = $this->topVentasProductosDesde($startDate);
        }

        $ventasProductos = $ventasProductosByRange['6m'] ?? collect();

        // Top clientes y proveedores
        $topClientesByRange = [];
        foreach ($distributionRanges as $key => $startDate) {
            $topClientesByRange[$key] = $this->topClientesDesde($startDate);
        }
        $topClientes = $topClientesByRange['6m'] ?? collect();

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
            'ventasProductosByRange' => $ventasProductosByRange,
            'topClientes' => $topClientes,
            'topClientesByRange' => $topClientesByRange,
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

    protected function topClientesDesde(Carbon $startDate, int $limit = 5)
    {
        return Venta::select(
                'customers.name as cliente',
                DB::raw('SUM(ventas.total_price) as total_ventas'),
                DB::raw('COUNT(detalle_ventas.id) as total_pedidos')
            )
            ->join('customers', 'ventas.customer_id', '=', 'customers.id')
            ->join('detalle_ventas', 'detalle_ventas.sale_id', '=', 'ventas.id')
            ->where('ventas.sale_date', '>=', $startDate)
            ->groupBy('customers.name')
            ->orderByDesc('total_ventas')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'cliente' => $row->cliente,
                    'total_ventas' => (float) $row->total_ventas,
                    'total_pedidos' => (int) $row->total_pedidos,
                ];
            })
            ->values();
    }

    public function getRevenuePredictions()
    {
        $lastSaleDate = DB::table('ventas')->max('sale_date');
        $allPredictions = DB::table('predicciones_ingresos')
            ->orderBy('fecha')
            ->get();

        $filtered = $allPredictions;
        if ($lastSaleDate) {
            $filtered = $allPredictions->filter(function ($row) use ($lastSaleDate) {
                return Carbon::parse($row->fecha)->gt(Carbon::parse($lastSaleDate));
            })->values();
        }

        if ($filtered->isEmpty() && $allPredictions->isEmpty()) {
            return response()->json([
                'labels' => [],
                'values' => [],
                'lower' => [],
                'upper' => [],
                'full_labels' => [],
                'full_values' => [],
                'full_lower' => [],
                'full_upper' => [],
            ]);
        }

        if ($filtered->isEmpty()) {
            $filtered = $allPredictions;
        }

        return response()->json([
            'labels' => $filtered->pluck('fecha')->map(fn ($f) => Carbon::parse($f)->format('Y-m-d')),
            'values' => $filtered->pluck('ingreso_predicho')->map(fn ($v) => (float) $v),
            'lower' => $filtered->pluck('ingreso_predicho_min')->map(fn ($v) => (float) $v),
            'upper' => $filtered->pluck('ingreso_predicho_max')->map(fn ($v) => (float) $v),
            'full_labels' => $allPredictions->pluck('fecha')->map(fn ($f) => Carbon::parse($f)->format('Y-m-d')),
            'full_values' => $allPredictions->pluck('ingreso_predicho')->map(fn ($v) => (float) $v),
            'full_lower' => $allPredictions->pluck('ingreso_predicho_min')->map(fn ($v) => (float) $v),
            'full_upper' => $allPredictions->pluck('ingreso_predicho_max')->map(fn ($v) => (float) $v),
        ]);
    }

    public function getProductPredictions()
    {
        $range = DB::table('predicciones_productos')
            ->selectRaw('MIN(fecha) as min_fecha, MAX(fecha) as max_fecha')
            ->first();

        $topProducts = DB::table('predicciones_productos')
            ->select('producto', DB::raw('SUM(cantidad_predicha) as total'))
            ->groupBy('producto')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'labels' => $topProducts->pluck('producto'),
            'values' => $topProducts->pluck('total')->map(fn ($v) => (float) $v),
            'start_date' => $range?->min_fecha ? Carbon::parse($range->min_fecha)->format('Y-m-d') : null,
            'end_date' => $range?->max_fecha ? Carbon::parse($range->max_fecha)->format('Y-m-d') : null,
        ]);
    }

    public function getRevenueEvaluation()
    {
        try {
            $rows = DB::table('evaluacion_predicciones_ingresos')
                ->orderBy('fecha')
                ->get();
        } catch (Throwable $e) {
            return response()->json([
                'labels' => [],
                'real' => [],
                'predicted' => [],
                'mae' => 0,
                'rmse' => 0,
                'mape' => 0,
            ]);
        }

        if ($rows->isEmpty()) {
            return response()->json([
                'labels' => [],
                'real' => [],
                'predicted' => [],
                'mae' => 0,
                'rmse' => 0,
                'mape' => 0,
            ]);
        }

        $labels = $rows->pluck('fecha')->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'));
        $real = $rows->pluck('ingreso_real')->map(fn ($v) => (float) $v);
        $pred = $rows->pluck('ingreso_predicho')->map(fn ($v) => (float) $v);
        $mae = $rows->avg(function ($row) {
            return abs(($row->ingreso_real ?? 0) - ($row->ingreso_predicho ?? 0));
        });
        $rmse = sqrt($rows->avg(function ($row) {
            $error = ($row->ingreso_real ?? 0) - ($row->ingreso_predicho ?? 0);
            return $error ** 2;
        }));
        $validMape = $rows->filter(fn ($row) => ($row->ingreso_real ?? 0) != 0);
        $mape = $validMape->isEmpty()
            ? 0
            : $validMape->avg(function ($row) {
                $error = abs(($row->ingreso_real ?? 0) - ($row->ingreso_predicho ?? 0));
                return ($error / max($row->ingreso_real, 1)) * 100;
            });

        return response()->json([
            'labels' => $labels,
            'real' => $real,
            'predicted' => $pred,
            'mae' => round($mae ?? 0, 2),
            'rmse' => round($rmse ?? 0, 2),
            'mape' => round($mape ?? 0, 2),
        ]);
    }
}
