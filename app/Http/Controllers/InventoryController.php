<?php

namespace App\Http\Controllers;

use App\Exports\DailyClosureExport;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:administrar.inventarios.index'])->only(['index', 'dailyClosure']);
        $this->middleware(['auth', 'permission:administrar.inventarios.export'])->only(['exportDailyClosure']);
    }

    public function index(): \Illuminate\View\View
    {
        $warehouses = $this->warehouses();

        return view('inventories.index', [
            'warehouses' => $warehouses,
            'defaultWarehouse' => array_key_first($warehouses),
            'defaultDate' => Carbon::today()->toDateString(),
        ]);
    }

    public function dailyClosure(Request $request)
    {
        $validated = $request->validate([
            'warehouse' => ['required', Rule::in(array_keys($this->warehouses()))],
            'date' => ['required', 'date'],
        ]);

        $data = $this->buildClosureData($validated['date'], $validated['warehouse']);

        return response()->json($data);
    }

    public function exportDailyClosure(Request $request)
    {
        $validated = $request->validate([
            'warehouse' => ['required', Rule::in(array_keys($this->warehouses()))],
            'date' => ['required', 'date'],
        ]);

        $closure = $this->buildClosureData($validated['date'], $validated['warehouse']);
        $warehouseLabel = $closure['meta']['warehouse_label'] ?? $validated['warehouse'];
        $dateLabel = Carbon::parse($closure['meta']['date'] ?? $validated['date'])->format('Ymd');

        return Excel::download(
            new DailyClosureExport(
                $closure['details'],
                $closure['filtered_summary'],
                $closure['meta']
            ),
            "cierre_diario_{$validated['warehouse']}_{$dateLabel}.xlsx"
        );
    }

    private function buildClosureData(string $date, string $warehouse): array
    {
        $warehouses = $this->warehouses();
        $dateObj = Carbon::parse($date)->startOfDay();

        $allSales = Venta::with([
                'customer:id,name',
                'user:id,name',
                'detalles' => function ($query) {
                    $query->select([
                        'id',
                        'sale_id',
                        'product_id',
                        'quantity',
                        'unit',
                        'unit_price',
                        'subtotal',
                        'amount_paid',
                        'difference',
                        'payment_status',
                        'payment_method',
                        'status',
                    ])->where('status', '!=', 'cancelled')
                      ->with(['producto:id,name']);
                },
            ])
            ->whereDate('sale_date', $dateObj)
            ->get()
            ->map(function (Venta $venta) {
                $venta->normalized_payment_method = $this->normalizePaymentMethod($venta->payment_method);

                return $venta;
            });

        $globalSummary = $this->summarizeSales($allSales);

        $filteredSales = $allSales
            ->where('warehouse', $warehouse)
            ->values();
        $filteredSummary = $this->summarizeSales($filteredSales);

        $detailRows = $filteredSales
            ->filter(fn (Venta $venta) => $venta->status !== 'cancelled')
            ->flatMap(function (Venta $venta) {
                return $venta->detalles
                    ->map(function ($detalle) use ($venta) {
                        $rawMethod = $detalle->payment_method ?? $venta->payment_method;
                        $normalizedMethod = $this->normalizePaymentMethod($rawMethod)
                            ?? $venta->normalized_payment_method;

                        $statusMeta = $this->paymentStatusMeta($detalle->payment_status);

                        return [
                            'sale_id' => $venta->id,
                            'customer' => $venta->customer->name ?? 'Cliente no registrado',
                            'product' => $detalle->producto->name ?? 'Producto sin nombre',
                            'quantity' => (float) $detalle->quantity,
                            'unit' => $detalle->unit ?? '-',
                            'payment_method' => [
                                'value' => $normalizedMethod,
                                'label' => $this->paymentMethodLabel($normalizedMethod),
                            ],
                            'payment_status' => [
                                'value' => $detalle->payment_status,
                                'label' => $statusMeta['label'],
                                'badge' => $statusMeta['badge'],
                            ],
                            'total' => round((float) $detalle->subtotal, 2),
                            'amount_paid' => round((float) ($detalle->amount_paid ?? 0), 2),
                            'pending' => round(max((float) ($detalle->difference ?? 0), 0), 2),
                            'difference' => round((float) ($detalle->difference ?? 0), 2),
                        ];
                    })
                    ->values();
            })
            ->values()
            ->map(function (array $detail, int $index) {
                $detail['index'] = $index + 1;

                return $detail;
            });

        $history = $this->recentClosures($dateObj);

        return [
            'summary' => $this->formatSummaryCards($globalSummary),
            'filtered_summary' => $filteredSummary,
            'details' => $detailRows,
            'history' => $history,
            'meta' => [
                'date' => $dateObj->toDateString(),
                'date_display' => $dateObj->format('d/m/Y'),
                'warehouse' => $warehouse,
                'warehouse_label' => $warehouses[$warehouse] ?? ucfirst(str_replace('_', ' ', $warehouse)),
                'payment_method_labels' => $this->paymentMethodLabels(),
            ],
        ];
    }

    private function recentClosures(Carbon $referenceDate): Collection
    {
        $warehouses = $this->warehouses();
        $endDate = $referenceDate->copy()->endOfDay()->toDateString();
        $startDate = $referenceDate->copy()->subDays(14)->startOfDay()->toDateString();

        return DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'dv.sale_id', '=', 'v.id')
            ->selectRaw("
                DATE(v.sale_date) as date,
                v.warehouse,
                COUNT(*) FILTER (
                    WHERE v.status != 'cancelled'
                    AND COALESCE(dv.status, 'pending') != 'cancelled'
                ) as total_orders,
                SUM(
                    CASE
                        WHEN v.status != 'cancelled'
                             AND COALESCE(dv.status, 'pending') != 'cancelled'
                            THEN COALESCE(dv.amount_paid, 0)
                        ELSE 0
                    END
                ) as income_total,
                SUM(
                    CASE
                        WHEN v.status != 'cancelled'
                             AND COALESCE(dv.status, 'pending') != 'cancelled'
                             AND COALESCE(dv.difference, 0) > 0
                            THEN COALESCE(dv.difference, 0)
                        ELSE 0
                    END
                ) as pending_amount
            ")
            ->whereBetween(DB::raw('DATE(v.sale_date)'), [$startDate, $endDate])
            ->groupBy('date', 'v.warehouse')
            ->orderByDesc('date')
            ->orderBy('v.warehouse')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($warehouses) {
                $date = Carbon::parse($row->date);
                $incomeTotal = (float) ($row->income_total ?? 0);
                $pendingAmount = (float) ($row->pending_amount ?? 0);

                return [
                    'date' => $date->toDateString(),
                    'date_display' => $date->format('d/m/Y'),
                    'warehouse' => $row->warehouse,
                    'warehouse_label' => $warehouses[$row->warehouse] ?? ucfirst(str_replace('_', ' ', $row->warehouse)),
                    'total_orders' => (int) $row->total_orders,
                    'income_total' => round($incomeTotal, 2),
                    'pending_amount' => round($pendingAmount, 2),
                    'total_amount' => round($incomeTotal + $pendingAmount, 2),
                ];
            });
    }

    private function summarizeSales(Collection $sales): array
    {
        $validDetails = $sales
            ->filter(fn (Venta $venta) => $venta->status !== 'cancelled')
            ->flatMap(function (Venta $venta) {
                $ventaMethod = $venta->normalized_payment_method
                    ?? $this->normalizePaymentMethod($venta->payment_method);

                return $venta->detalles
                    ->filter(fn ($detalle) => ($detalle->status ?? null) !== 'cancelled')
                    ->map(function ($detalle) use ($ventaMethod) {
                        $rawMethod = $detalle->payment_method ?? null;
                        $normalizedMethod = $this->normalizePaymentMethod($rawMethod) ?? $ventaMethod;

                        return [
                            'status' => $detalle->payment_status,
                            'amount_paid' => (float) ($detalle->amount_paid ?? 0),
                            'difference' => (float) ($detalle->difference ?? 0),
                            'subtotal' => (float) ($detalle->subtotal ?? 0),
                            'method' => $normalizedMethod,
                        ];
                    });
            })
            ->filter(fn ($detalle) => $detalle !== null)
            ->values();

        $totalOrders = $validDetails->count();
        $paidOrders = $validDetails->filter(fn ($detalle) => $detalle['status'] === 'paid')->count();
        $pendingOrders = $totalOrders - $paidOrders;

        $incomeTotal = round($validDetails->sum('amount_paid'), 2);
        $pendingAmount = round(
            $validDetails->reduce(fn ($carry, $detalle) => $carry + max($detalle['difference'], 0), 0.0),
            2
        );

        $methodLabels = collect($this->paymentMethodLabels());
        $methodCollected = $methodLabels->mapWithKeys(fn ($label, $key) => [$key => 0.0])->all();
        $methodSales = $methodLabels->mapWithKeys(fn ($label, $key) => [$key => 0.0])->all();

        foreach ($validDetails as $detalle) {
            $method = $detalle['method'] ?? null;
            if ($method !== null && array_key_exists($method, $methodCollected)) {
                $methodCollected[$method] += (float) $detalle['amount_paid'];
                $methodSales[$method] += (float) $detalle['subtotal'];
            }
        }

        $methodCollected = array_map(fn ($value) => round($value, 2), $methodCollected);
        $methodSales = array_map(fn ($value) => round($value, 2), $methodSales);

        $incomeCash = round($methodCollected['efectivo'] ?? 0, 2);

        $methodBreakdown = collect($methodCollected)
            ->map(function ($amount, $method) use ($methodLabels) {
                return [
                    'method' => $method,
                    'label' => $methodLabels[$method] ?? $this->paymentMethodLabel($method),
                    'amount' => $amount,
                ];
            })
            ->filter(fn ($item) => $item['amount'] > 0)
            ->values()
            ->sortByDesc('amount')
            ->values();

        $changeTotal = round(
            $validDetails
                ->filter(fn ($detalle) => $detalle['status'] === 'change')
                ->reduce(fn ($carry, $detalle) => $carry + min($detalle['difference'] ?? 0, 0), 0.0),
            2
        );

        $toCollectTotal = round(
            $validDetails
                ->filter(fn ($detalle) => $detalle['status'] === 'to_collect')
                ->reduce(fn ($carry, $detalle) => $carry + max($detalle['difference'] ?? 0, 0), 0.0),
            2
        );

        return [
            'total_orders' => $totalOrders,
            'paid_orders' => $paidOrders,
            'pending_orders' => $pendingOrders,
            'income_total' => $incomeTotal,
            'pending_amount' => $pendingAmount,
            'income_cash' => $incomeCash,
            'method_breakdown' => $methodBreakdown,
            'method_collected_totals' => $methodCollected,
            'method_sales_totals' => $methodSales,
            'change_total' => $changeTotal,
            'to_collect_total' => $toCollectTotal,
        ];
    }

    private function formatSummaryCards(array $summary): array
    {
        return [
            'cards' => [
                [
                    'label' => 'Total de Productos',
                    'value' => $summary['total_orders'],
                    'description' => 'Productos registrados en el día',
                ],
                [
                    'label' => 'Productos Pagados',
                    'value' => $summary['paid_orders'],
                    'description' => 'Productos completamente pagados',
                ],
                [
                    'label' => 'Productos Pendientes',
                    'value' => $summary['pending_orders'],
                    'description' => 'Productos con saldo por cobrar',
                ],
                [
                    'label' => 'Ingresos del día',
                    'value' => $summary['income_total'],
                    'description' => 'Incluye todos los métodos de pago',
                    'format' => 'currency',
                    'extra' => [
                        'cash' => $summary['income_cash'],
                        'pending' => $summary['pending_amount'],
                    ],
                ],
            ],
            'raw' => $summary,
        ];
    }

    private function normalizePaymentMethod(?string $method): ?string
    {
        if (!is_string($method)) {
            return null;
        }

        $normalized = strtolower(trim($method));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, array_keys($this->paymentMethodLabels()), true)) {
            return $normalized;
        }

        $cleaned = str_replace('.', '', $normalized);
        $cleaned = preg_replace('/\s+/', '_', $cleaned ?? '');

        if (in_array($cleaned, array_keys($this->paymentMethodLabels()), true)) {
            return $cleaned;
        }

        $legacy = [
            'cash' => 'efectivo',
            'card' => 'trans_bbva',
            'transfer' => 'trans_bcp',
        ];

        return $legacy[$normalized] ?? $legacy[$cleaned] ?? null;
    }

    private function paymentMethodLabel(?string $method): string
    {
        $labels = $this->paymentMethodLabels();

        if ($method && isset($labels[$method])) {
            return $labels[$method];
        }

        return 'Método desconocido';
    }

    private function paymentMethodLabels(): array
    {
        return [
            'efectivo' => 'Efectivo',
            'trans_bcp' => 'Trans. BCP',
            'trans_bbva' => 'Trans. BBVA',
            'yape' => 'Yape',
            'plin' => 'Plin',
        ];
    }

    private function paymentStatusMeta(?string $status): array
    {
        return match ($status) {
            'paid' => ['label' => 'Pagado', 'badge' => 'badge bg-success'],
            'to_collect' => ['label' => 'Saldo pendiente', 'badge' => 'badge bg-info text-dark'],
            'change' => ['label' => 'Vuelto pendiente', 'badge' => 'badge bg-secondary'],
            default => ['label' => 'Pendiente', 'badge' => 'badge bg-warning text-dark'],
        };
    }

    private function warehouses(): array
    {
        return [
            'curva' => 'Almacén Curva',
            'milla' => 'Almacén Milla',
            'santa_carolina' => 'Almacén Santa Carolina',
        ];
    }
}

