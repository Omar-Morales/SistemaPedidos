<?php

namespace App\Http\Controllers;

use App\Exports\DailyClosureExport;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $cashDetails = $filteredSales
            ->filter(fn (Venta $venta) => $venta->status !== 'cancelled')
            ->filter(fn (Venta $venta) => $venta->normalized_payment_method === 'efectivo')
            ->flatMap(function (Venta $venta) {
                $statusMeta = $this->paymentStatusMeta($venta->payment_status);

                return $venta->detalles->map(function ($detalle) use ($venta, $statusMeta) {
                    return [
                        'sale_id' => $venta->id,
                        'customer' => $venta->customer->name ?? 'Cliente no registrado',
                        'product' => $detalle->producto->name ?? 'Producto sin nombre',
                        'quantity' => (float) $detalle->quantity,
                        'unit' => $detalle->unit ?? '-',
                        'payment_method' => [
                            'value' => $venta->normalized_payment_method,
                            'label' => $this->paymentMethodLabel($venta->normalized_payment_method),
                        ],
                        'payment_status' => [
                            'value' => $venta->payment_status,
                            'label' => $statusMeta['label'],
                            'badge' => $statusMeta['badge'],
                        ],
                        'total' => round((float) $detalle->subtotal, 2),
                        'amount_paid' => round((float) ($detalle->amount_paid ?? 0), 2),
                        'pending' => round(max((float) ($detalle->difference ?? 0), 0), 2),
                    ];
                });
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
            'details' => $cashDetails,
            'history' => $history,
            'meta' => [
                'date' => $dateObj->toDateString(),
                'date_display' => $dateObj->format('d/m/Y'),
                'warehouse' => $warehouse,
                'warehouse_label' => $warehouses[$warehouse] ?? ucfirst(str_replace('_', ' ', $warehouse)),
            ],
        ];
    }

    private function recentClosures(Carbon $referenceDate): Collection
    {
        $warehouses = $this->warehouses();

        return Venta::selectRaw("
                DATE(sale_date) as date,
                warehouse,
                COUNT(*) FILTER (WHERE status != 'cancelled') as total_orders,
                COUNT(*) FILTER (WHERE status != 'cancelled' AND payment_status = 'paid') as paid_orders,
                COUNT(*) FILTER (WHERE status != 'cancelled' AND payment_status != 'paid') as pending_orders,
                SUM(CASE WHEN status != 'cancelled' THEN amount_paid ELSE 0 END) as income_total,
                SUM(CASE WHEN status != 'cancelled' AND difference > 0 THEN difference ELSE 0 END) as pending_amount
            ")
            ->whereDate('sale_date', '<=', $referenceDate->toDateString())
            ->whereDate('sale_date', '>=', $referenceDate->copy()->subDays(14)->toDateString())
            ->groupBy('date', 'warehouse')
            ->orderByDesc('date')
            ->orderBy('warehouse')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($warehouses) {
                $date = Carbon::parse($row->date);

                return [
                    'date' => $date->toDateString(),
                    'date_display' => $date->format('d/m/Y'),
                    'warehouse' => $row->warehouse,
                    'warehouse_label' => $warehouses[$row->warehouse] ?? ucfirst(str_replace('_', ' ', $row->warehouse)),
                    'total_orders' => (int) $row->total_orders,
                    'paid_orders' => (int) $row->paid_orders,
                    'pending_orders' => (int) $row->pending_orders,
                    'income_total' => round((float) $row->income_total, 2),
                    'pending_amount' => round((float) $row->pending_amount, 2),
                ];
            });
    }

    private function summarizeSales(Collection $sales): array
    {
        $validSales = $sales->filter(fn (Venta $venta) => $venta->status !== 'cancelled');
        $cashSales = $validSales->filter(fn (Venta $venta) => $venta->normalized_payment_method === 'efectivo');

        $pendingAmount = $validSales->reduce(
            fn ($carry, Venta $venta) => $carry + max((float) $venta->difference, 0),
            0.0
        );

        $totalOrders = $validSales->count();
        $paidOrders = $validSales->where('payment_status', 'paid')->count();
        $pendingOrders = $totalOrders - $paidOrders;

        return [
            'total_orders' => $totalOrders,
            'paid_orders' => $paidOrders,
            'pending_orders' => $pendingOrders,
            'income_total' => round($validSales->sum('amount_paid'), 2),
            'pending_amount' => round($pendingAmount, 2),
            'income_cash' => round($cashSales->sum('amount_paid'), 2),
        ];
    }

    private function formatSummaryCards(array $summary): array
    {
        return [
            'cards' => [
                [
                    'label' => 'Total de Pedidos',
                    'value' => $summary['total_orders'],
                    'description' => 'Pedidos registrados en el dÃƒÂ­a',
                ],
                [
                    'label' => 'Pedidos Pagados',
                    'value' => $summary['paid_orders'],
                    'description' => 'Pedidos completamente pagados',
                ],
                [
                    'label' => 'Pedidos Pendientes',
                    'value' => $summary['pending_orders'],
                    'description' => 'Pedidos con saldo por cobrar',
                ],
                [
                    'label' => 'Ingresos del DÃ­a',
                    'value' => $summary['income_total'],
                    'description' => 'Incluye todos los mÃ©todos de pago',
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

        return 'MÃƒÂ©todo desconocido';
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
            'curva' => 'AlmacÃƒÂ©n Curva',
            'milla' => 'AlmacÃƒÂ©n Milla',
            'santa_carolina' => 'AlmacÃƒÂ©n Santa Carolina',
        ];
    }
}
