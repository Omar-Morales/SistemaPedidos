<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DetalleVenta;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\TipoDocumento;
use App\Models\Venta;
use App\Models\VentaLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

class VentaController extends Controller
{
    public function __construct()
    {
        // Solo usuarios con los permisos adecuados pueden acceder a cada accion
        $this->middleware('permission:administrar.ventas.index')->only(['index', 'getData', 'detalle', 'show']);
        $this->middleware('permission:administrar.ventas.create')->only(['store']);
        $this->middleware('permission:administrar.ventas.edit')->only(['update', 'updateDetail']);
        $this->middleware('permission:administrar.ventas.delete')->only(['destroy', 'destroyDetail']);
    }

    public function index()
    {
        $tiposDocumento = TipoDocumento::where('type', 'venta')->get();

        return view('venta.index', compact('tiposDocumento'));
    }

    public function create()
    {
        //
    }

    protected function actualizarEstadoProducto(Product $producto): void
    {
        $producto->refresh();

        if ($producto->quantity <= 0 && $producto->status !== 'sold') {
            $producto->update(['status' => 'sold']);
        } elseif ($producto->quantity > 0 && $producto->status !== 'available') {
            $producto->update(['status' => 'available']);
        }
    }

    private function calculatePaymentStatus(float $total, float $amountPaid): string
    {
        if ($amountPaid <= 0) {
            return 'pending';
        }

        if ($amountPaid < $total) {
            return 'to_collect';
        }

        if ($amountPaid > $total) {
            return 'change';
        }

        return 'paid';
    }

    private function normalizeDetailStatus(?string $status): string
    {
        return match (strtolower($status ?? '')) {
            'in_progress' => 'in_progress',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function normalizeDetailPaymentStatus(?string $status, float $subtotal, float $amountPaid): string
    {
        $normalized = strtolower($status ?? '');

        if (in_array($normalized, ['pending', 'paid', 'to_collect', 'change', 'cancelled'], true)) {
            return $normalized;
        }

        if ($amountPaid >= $subtotal && $subtotal > 0) {
            return 'paid';
        }

        if ($amountPaid > 0 && $amountPaid < $subtotal) {
            return 'to_collect';
        }

        if ($amountPaid > $subtotal && $subtotal > 0) {
            return 'change';
        }

        return 'pending';
    }

    private function resolveSaleStatusFromDetails($statuses): string
    {
        $collection = collect($statuses)
            ->filter(fn ($status) => !is_null($status))
            ->map(fn ($status) => strtolower((string) $status));

        if ($collection->isEmpty()) {
            return 'pending';
        }

        if ($collection->every(fn ($status) => $status === 'cancelled')) {
            return 'cancelled';
        }

        if ($collection->every(fn ($status) => $status === 'delivered')) {
            return 'delivered';
        }

        if ($collection->contains('delivered') || $collection->contains('in_progress')) {
            return 'in_progress';
        }

        return 'pending';
    }

    private function shouldAffectInventory(string $status): bool
    {
        return $status === 'delivered';
    }

    private const PAYMENT_METHOD_LABELS = [
        'efectivo' => 'Efectivo',
        'trans_bcp' => 'Trans. BCP',
        'trans_bbva' => 'Trans. BBVA',
        'yape' => 'Yape',
        'plin' => 'Plin',
    ];

    private const CURRENT_PAYMENT_METHODS = [
        'efectivo',
        'trans_bcp',
        'trans_bbva',
        'yape',
        'plin',
    ];

    private const LEGACY_PAYMENT_METHOD_MAP = [
        'cash' => 'efectivo',
        'card' => 'trans_bbva',
        'transfer' => 'trans_bcp',
    ];

    private function resolveWarehouseScopeForUser($user): ?string
    {
        if (!$user || !method_exists($user, 'getRoleNames')) {
            return null;
        }

        $roleWarehouseMap = [
            'curva' => 'curva',
            'milla' => 'milla',
            'santa carolina' => 'santa_carolina',
        ];

        foreach ($user->getRoleNames() as $roleName) {
            $normalized = Str::of($roleName)->lower()->value();
            if (isset($roleWarehouseMap[$normalized])) {
                return $roleWarehouseMap[$normalized];
            }
        }

        return null;
    }

    private function filterDetallesByWarehouse(Venta $venta, ?string $restrictedWarehouse): Collection
    {
        if (!$restrictedWarehouse) {
            return $venta->detalles;
        }

        return $venta->detalles
            ->filter(function (DetalleVenta $detalle) use ($venta, $restrictedWarehouse) {
                $detalleWarehouse = strtolower(trim((string) ($detalle->warehouse ?? $venta->warehouse)));
                return $detalleWarehouse === $restrictedWarehouse;
            })
            ->values();
    }

    private function assertCanManageDetalle(DetalleVenta $detalle): void
    {
        $restrictedWarehouse = $this->resolveWarehouseScopeForUser(Auth::user());
        if (!$restrictedWarehouse) {
            return;
        }

        $detalleWarehouse = strtolower(trim((string) ($detalle->warehouse ?? optional($detalle->venta)->warehouse)));
        if ($detalleWarehouse !== $restrictedWarehouse) {
            abort(403, 'No tienes permiso para administrar este producto.');
        }
    }

    private function validPaymentMethods(): array
    {
        return self::CURRENT_PAYMENT_METHODS;
    }

    private function paymentMethodRule(string $baseRule = 'nullable'): string
    {
        return $baseRule . '|in:' . implode(',', $this->validPaymentMethods());
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

        if (in_array($normalized, self::CURRENT_PAYMENT_METHODS, true)) {
            return $normalized;
        }

        $cleaned = str_replace('.', '', $normalized);
        $cleaned = preg_replace('/\s+/', '_', $cleaned);

        if (in_array($cleaned, self::CURRENT_PAYMENT_METHODS, true)) {
            return $cleaned;
        }

        if (isset(self::LEGACY_PAYMENT_METHOD_MAP[$normalized])) {
            $mapped = self::LEGACY_PAYMENT_METHOD_MAP[$normalized];
            return in_array($mapped, self::CURRENT_PAYMENT_METHODS, true) ? $mapped : null;
        }

        if (isset(self::LEGACY_PAYMENT_METHOD_MAP[$cleaned])) {
            $mapped = self::LEGACY_PAYMENT_METHOD_MAP[$cleaned];
            return in_array($mapped, self::CURRENT_PAYMENT_METHODS, true) ? $mapped : null;
        }

        return null;
    }

    private function paymentMethodLabel(?string $method): string
    {
        if (!is_string($method) || trim($method) === '') {
            return '-';
        }

        $normalized = strtolower(trim($method));

        if (isset(self::PAYMENT_METHOD_LABELS[$normalized])) {
            return self::PAYMENT_METHOD_LABELS[$normalized];
        }

        $cleaned = str_replace('.', '', $normalized);
        $cleaned = preg_replace('/\s+/', '_', $cleaned);

        if (isset(self::PAYMENT_METHOD_LABELS[$cleaned])) {
            return self::PAYMENT_METHOD_LABELS[$cleaned];
        }

        if (isset(self::LEGACY_PAYMENT_METHOD_MAP[$normalized])) {
            $mapped = self::LEGACY_PAYMENT_METHOD_MAP[$normalized];
            return self::PAYMENT_METHOD_LABELS[$mapped] ?? '-';
        }

        if (isset(self::LEGACY_PAYMENT_METHOD_MAP[$cleaned])) {
            $mapped = self::LEGACY_PAYMENT_METHOD_MAP[$cleaned];
            return self::PAYMENT_METHOD_LABELS[$mapped] ?? '-';
        }

        return '-';
    }

    private function formatUnitValue($value): string
    {
        $numeric = is_numeric($value) ? (float) $value : 0;
        $formatted = number_format($numeric, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function inventoryReason(string $status, int $ventaId): string
    {
        return match ($status) {
            'delivered' => 'Venta entregada ID: ' . $ventaId,
            'in_progress' => 'Venta en curso ID: ' . $ventaId,
            'cancelled' => 'Venta anulada ID: ' . $ventaId,
            default => 'Venta pendiente ID: ' . $ventaId,
        };
    }

    public function store(Request $request)
    {
        $rawDetails = json_decode($request->input('details'), true);

        if (!is_array($rawDetails) || empty($rawDetails)) {
            return response()->json(['message' => 'Detalles invalidos.'], 422);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tipodocumento_id' => 'required|exists:tipodocumento,id',
            'sale_date' => 'nullable|date',
            'payment_method' => $this->paymentMethodRule(),
            'delivery_type' => 'nullable|in:pickup,delivery',
            'warehouse' => 'nullable|in:curva,milla,santa_carolina',
        ]);

        $cliente = Customer::find($request->customer_id);
        if ($cliente && $cliente->status === 'inactive') {
            return response()->json(['message' => "El cliente '{$cliente->name}' esta inactivo."], 422);
        }

        $paymentMethodVenta = $this->normalizePaymentMethod($request->payment_method) ?? 'efectivo';

        $productIds = collect($rawDetails)->pluck('product_id')->unique();
        $productos = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $detalleSanitizado = [];
        $productosUsados = [];

        foreach ($rawDetails as $index => $item) {
            if (!isset($item['product_id']) || !is_numeric($item['product_id'])) {
                return response()->json([
                    'message' => "Debe seleccionar un producto valido para el item {$index}.",
                ], 422);
            }

            $productId = (int) $item['product_id'];
            $producto = $productos->get($productId);

            if (!$producto) {
                return response()->json(['message' => "El producto con ID {$productId} no existe."], 404);
            }

            if ($producto->status === 'archived') {
                return response()->json(['message' => "El producto '{$producto->name}' esta archivado."], 422);
            }

            if (in_array($productId, $productosUsados, true)) {
                return response()->json(['message' => "El producto '{$producto->name}' esta repetido."], 422);
            }

            $warehouseDetalle = $item['warehouse'] ?? $request->warehouse;
            $deliveryDetalle = $item['delivery_type'] ?? $request->delivery_type;
            $paymentMethodDetalle = $item['payment_method'] ?? $paymentMethodVenta;

            if (!in_array($warehouseDetalle, ['curva', 'milla', 'santa_carolina'], true)) {
                return response()->json([
                    'message' => "Almacen invalido para el item {$index}.",
                ], 422);
            }

            if (!in_array($deliveryDetalle, ['pickup', 'delivery'], true)) {
                return response()->json([
                    'message' => "Tipo de entrega invalido para el item {$index}.",
                ], 422);
            }

            if ($paymentMethodDetalle) {
                $normalizedPaymentMethod = $this->normalizePaymentMethod($paymentMethodDetalle);
                if ($normalizedPaymentMethod === null) {
                    return response()->json([
                        'message' => "Metodo de pago invalido para el item {$index}.",
                    ], 422);
                }
                $paymentMethodDetalle = $normalizedPaymentMethod;
            } else {
                $paymentMethodDetalle = null;
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
            $subtotal = isset($item['subtotal']) ? (float) $item['subtotal'] : 0;
            $unitInput = $item['unit'] ?? null;
            $amountPaid = isset($item['amount_paid']) ? max(0, (float) $item['amount_paid']) : 0;
            $warehouseDetalle = $item['warehouse'] ?? $venta->warehouse;
            $deliveryDetalle = $item['delivery_type'] ?? $venta->delivery_type;
            $paymentMethodDetalle = $item['payment_method'] ?? $paymentMethodVenta;

            if (!in_array($warehouseDetalle, ['curva', 'milla', 'santa_carolina'], true)) {
                return response()->json([
                    'message' => "Almacen invalido para el item {$index}.",
                ], 422);
            }

            if (!in_array($deliveryDetalle, ['pickup', 'delivery'], true)) {
                return response()->json([
                    'message' => "Tipo de entrega invalido para el item {$index}.",
                ], 422);
            }

            if ($paymentMethodDetalle) {
                $normalizedPaymentMethod = $this->normalizePaymentMethod($paymentMethodDetalle);

                if ($normalizedPaymentMethod === null) {
                    return response()->json([
                        'message' => "Metodo de pago invalido para el item {$index}.",
                    ], 422);
                }

                $paymentMethodDetalle = $normalizedPaymentMethod;
            } else {
                $paymentMethodDetalle = null;
            }

            if ($quantity < 1 || !is_numeric($subtotal) || $subtotal < 0) {
                return response()->json([
                    'message' => "Detalle invalido en la posicion {$index}.",
                    'errors' => [
                        "details[{$index}]" => ['Debe indicar producto, cantidad, unidad y subtotal validos.'],
                    ],
                ], 422);
            }

            $warehouseDetalle = $item['warehouse'] ?? $request->warehouse;
            $deliveryDetalle = $item['delivery_type'] ?? $request->delivery_type;
            $paymentMethodDetalle = $item['payment_method'] ?? $paymentMethodVenta;

            if (!in_array($warehouseDetalle, ['curva', 'milla', 'santa_carolina'], true)) {
                return response()->json([
                    'message' => "Almacen invalido para el item {$index}.",
                ], 422);
            }

            if (!in_array($deliveryDetalle, ['pickup', 'delivery'], true)) {
                return response()->json([
                    'message' => "Tipo de entrega invalido para el item {$index}.",
                ], 422);
            }

            if ($paymentMethodDetalle) {
                $normalizedPaymentMethod = $this->normalizePaymentMethod($paymentMethodDetalle);

                if ($normalizedPaymentMethod === null) {
                    return response()->json([
                        'message' => "Metodo de pago invalido para el item {$index}.",
                    ], 422);
                }

                $paymentMethodDetalle = $normalizedPaymentMethod;
            } else {
                $paymentMethodDetalle = null;
            }

            $warehouseDetalle = $item['warehouse'] ?? $request->warehouse;
            $deliveryDetalle = $item['delivery_type'] ?? $request->delivery_type;
            $paymentMethodDetalle = $item['payment_method'] ?? $paymentMethodVenta;

            if (!in_array($warehouseDetalle, ['curva', 'milla', 'santa_carolina'], true)) {
                return response()->json([
                    'message' => "Almacen invalido para el item {$index}.",
                ], 422);
            }

            if (!in_array($deliveryDetalle, ['pickup', 'delivery'], true)) {
                return response()->json([
                    'message' => "Tipo de entrega invalido para el item {$index}.",
                ], 422);
            }

            if ($paymentMethodDetalle) {
                $normalizedPaymentMethod = $this->normalizePaymentMethod($paymentMethodDetalle);

                if ($normalizedPaymentMethod === null) {
                    return response()->json([
                        'message' => "Metodo de pago invalido para el item {$index}.",
                    ], 422);
                }

                $paymentMethodDetalle = $normalizedPaymentMethod;
            } else {
                $paymentMethodDetalle = null;
            }

            $statusDetalle = $this->normalizeDetailStatus($item['status'] ?? null);
            $paymentStatusDetalle = $this->normalizeDetailPaymentStatus($item['payment_status'] ?? null, $subtotal, $amountPaid);
            $differenceDetalle = round($subtotal - $amountPaid, 2);
            $unitLabel = $this->formatUnitValue($unitInput);
            $unitPrice = $quantity > 0 ? round($subtotal / $quantity, 4) : 0;

            if ($this->shouldAffectInventory($statusDetalle) && $producto->quantity < $quantity) {
                return response()->json(['message' => "Stock insuficiente para '{$producto->name}'."], 422);
            }

            $detalleSanitizado[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit' => $unitLabel,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'status' => $statusDetalle,
                'payment_status' => $paymentStatusDetalle,
                'amount_paid' => $amountPaid,
                'difference' => $differenceDetalle,
                'warehouse' => $warehouseDetalle,
                'delivery_type' => $deliveryDetalle,
                'payment_method' => $paymentMethodDetalle,
            ];

            $productosUsados[] = $productId;
        }

        $detalleCollection = collect($detalleSanitizado);
        $activeDetails = $detalleCollection->where('status', '!=', 'cancelled');
        $total = $activeDetails->sum('subtotal');
        $totalPagado = $activeDetails->sum('amount_paid');
        $statusVenta = $this->resolveSaleStatusFromDetails($detalleCollection->pluck('status'));
        $requestedPaymentStatus = strtolower($request->input('payment_status', 'pending'));
        $allowedStatuses = ['pending', 'paid', 'to_collect', 'change', 'cancelled'];
        if (!in_array($requestedPaymentStatus, $allowedStatuses, true)) {
            $requestedPaymentStatus = $this->calculatePaymentStatus($total, $totalPagado);
        }
        $paymentStatusVenta = $requestedPaymentStatus;
        $differenceVenta = round($total - $totalPagado, 2);
        $saleDate = $request->input('sale_date') ?: Carbon::today()->format('Y-m-d');

        DB::beginTransaction();

        try {
            $venta = Venta::create([
                'customer_id' => $request->customer_id,
                'tipodocumento_id' => $request->tipodocumento_id,
                'user_id' => auth()->id(),
                'sale_date' => $saleDate,
                'payment_method' => $paymentMethodVenta,
                'status' => $statusVenta,
                'delivery_type' => $request->delivery_type,
                'warehouse' => $request->warehouse,
                'total_price' => $total,
                'amount_paid' => $totalPagado,
                'payment_status' => $paymentStatusVenta,
                'difference' => $differenceVenta,
                'codigo' => null,
            ]);

            $codigo = 'VNT-' . str_pad((string) $venta->id, 5, '0', STR_PAD_LEFT);
            $venta->update(['codigo' => $codigo]);

            foreach ($detalleCollection as $detalle) {
                DetalleVenta::create([
                    'sale_id' => $venta->id,
                    'product_id' => $detalle['product_id'],
                    'quantity' => $detalle['quantity'],
                    'unit' => $detalle['unit'],
                    'unit_price' => $detalle['unit_price'],
                    'subtotal' => $detalle['subtotal'],
                    'status' => $detalle['status'],
                    'payment_status' => $detalle['payment_status'],
                    'amount_paid' => $detalle['amount_paid'],
                    'difference' => $detalle['difference'],
                    'warehouse' => $detalle['warehouse'],
                    'delivery_type' => $detalle['delivery_type'],
                    'payment_method' => $detalle['payment_method'],
                ]);

                Inventory::create([
                    'product_id' => $detalle['product_id'],
                    'type' => 'sale',
                    'quantity' => $this->shouldAffectInventory($detalle['status']) ? -$detalle['quantity'] : 0,
                    'reason' => $this->inventoryReason($detalle['status'], $venta->id),
                    'user_id' => auth()->id(),
                    'reference_id' => $venta->id,
                ]);

                if ($this->shouldAffectInventory($detalle['status'])) {
                    $producto = $productos->get($detalle['product_id']);
                    $producto->decrement('quantity', $detalle['quantity']);
                    $this->actualizarEstadoProducto($producto);
                }
            }

            $this->logVenta($venta->id, 'created', [], [
                'new_data' => $venta->toArray(),
                'new_details' => $detalleCollection->toArray(),
            ]);

            DB::commit();

            return response()->json(['message' => 'Venta registrada correctamente.']);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => 'Ocurri un problema al registrar la venta.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $venta = Venta::with(['detalles'])->findOrFail($id);

        $restrictedWarehouse = $this->resolveWarehouseScopeForUser(Auth::user());
        if ($restrictedWarehouse) {
            $filteredDetalles = $this->filterDetallesByWarehouse($venta, $restrictedWarehouse);
            if ($filteredDetalles->isEmpty()) {
                abort(403, 'No tienes permiso para ver esta venta.');
            }
            $venta->setRelation('detalles', $filteredDetalles);
        }

        $clientesQuery = Customer::query()
            ->select('id', 'name', 'status')
            ->where('status', 'active');

        if ($venta->customer_id) {
            $clientesQuery->orWhere('id', $venta->customer_id);
        }

        $clientes = $clientesQuery
            ->orderBy('name')
            ->get()
            ->map(function (Customer $cliente) {
                return [
                    'id' => $cliente->id,
                    'text' => $cliente->status === 'active'
                        ? $cliente->name
                        : $cliente->name . ' (inactivo)',
                ];
            });

        return response()->json([
            'venta' => [
                'id' => $venta->id,
                'cliente' => $venta->customer_id,
                'tipo_documento' => $venta->tipodocumento_id,
                'fecha' => optional($venta->sale_date)->format('Y-m-d'),
                'delivery_type' => $venta->delivery_type,
                'estado' => $venta->status,
                'payment_method' => $this->normalizePaymentMethod($venta->payment_method) ?? 'efectivo',
                'warehouse' => $venta->warehouse,
                'total' => (float) $venta->total_price,
                'amount_paid' => (float) $venta->amount_paid,
                'payment_status' => $venta->payment_status,
                'difference' => (float) $venta->difference,
                'codigo' => $venta->codigo,
                'detalle' => $venta->detalles->map(function (DetalleVenta $detalle) {
                    return [
                        'id' => $detalle->id,
                        'product_id' => $detalle->product_id,
                        'quantity' => $detalle->quantity,
                        'unit' => $detalle->unit,
                        'unit_price' => $detalle->unit_price,
                        'subtotal' => $detalle->subtotal,
                        'status' => $detalle->status,
                        'payment_status' => in_array($detalle->payment_status, ['pending', 'paid', 'to_collect', 'change', 'cancelled'], true)
                            ? $detalle->payment_status
                            : 'pending',
                        'amount_paid' => $detalle->amount_paid,
                        'difference' => $detalle->difference,
                        'warehouse' => $detalle->warehouse,
                        'delivery_type' => $detalle->delivery_type,
                        'payment_method' => $this->normalizePaymentMethod($detalle->payment_method) ?? 'efectivo',
                    ];
                }),
            ],
            'clientes' => $clientes,
        ]);
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $venta = Venta::with('detalles')->findOrFail($id);
        $originalData = $venta->toArray();
        $originalDetails = $venta->detalles->toArray();

        $rawDetails = json_decode($request->input('details'), true);
        if (!is_array($rawDetails) || empty($rawDetails)) {
            return response()->json(['message' => 'Detalles invalidos.'], 422);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tipodocumento_id' => 'required|exists:tipodocumento,id',
            'sale_date' => 'nullable|date',
            'payment_method' => $this->paymentMethodRule('required'),
            'delivery_type' => 'required|in:pickup,delivery',
            'warehouse' => 'required|in:curva,milla,santa_carolina',
            'codigo' => 'nullable|string|max:50|unique:ventas,codigo,' . $venta->id,
        ]);

        $paymentMethodVenta = $this->normalizePaymentMethod($request->payment_method)
            ?? $this->normalizePaymentMethod($venta->payment_method)
            ?? 'efectivo';

        $cliente = Customer::find($request->customer_id);
        if ($cliente && $cliente->status === 'inactive') {
            return response()->json(['message' => "El cliente '{$cliente->name}' esta inactivo."], 422);
        }

        $existingDeliveredByProduct = [];
        foreach ($venta->detalles as $detalleAnterior) {
            if ($this->shouldAffectInventory($detalleAnterior->status)) {
                $existingDeliveredByProduct[$detalleAnterior->product_id] = ($existingDeliveredByProduct[$detalleAnterior->product_id] ?? 0) + $detalleAnterior->quantity;
            }
        }

        $newProductIds = collect($rawDetails)->pluck('product_id')->unique();
        $allProductIds = $newProductIds
            ->merge($venta->detalles->pluck('product_id'))
            ->unique();
        $productos = Product::whereIn('id', $allProductIds)->get()->keyBy('id');

        $detalleSanitizado = [];
        $productosUsados = [];

        foreach ($rawDetails as $index => $item) {
            if (!isset($item['product_id']) || !is_numeric($item['product_id'])) {
                return response()->json([
                    'message' => "Debe seleccionar un producto valido para el item {$index}.",
                ], 422);
            }

            $productId = (int) $item['product_id'];
            $producto = $productos->get($productId);

            if (!$producto) {
                return response()->json(['message' => "El producto con ID {$productId} no existe."], 404);
            }

            if ($producto->status === 'archived') {
                return response()->json(['message' => "El producto '{$producto->name}' esta archivado."], 422);
            }

            if (in_array($productId, $productosUsados, true)) {
                return response()->json(['message' => "El producto '{$producto->name}' esta repetido."], 422);
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
            $subtotal = isset($item['subtotal']) ? (float) $item['subtotal'] : 0;
            $unitInput = $item['unit'] ?? null;
            $amountPaid = isset($item['amount_paid']) ? max(0, (float) $item['amount_paid']) : 0;

            if ($quantity < 1 || !is_numeric($subtotal) || $subtotal < 0) {
                return response()->json([
                    'message' => "Detalle invalido en la posicion {$index}.",
                    'errors' => [
                        "details[{$index}]" => ['Debe indicar producto, cantidad, unidad y subtotal validos.'],
                    ],
                ], 422);
            }

            $statusDetalle = $this->normalizeDetailStatus($item['status'] ?? null);
            $paymentStatusDetalle = $this->normalizeDetailPaymentStatus($item['payment_status'] ?? null, $subtotal, $amountPaid);
            $differenceDetalle = round($subtotal - $amountPaid, 2);
            $unitLabel = $this->formatUnitValue($unitInput);
            $unitPrice = $quantity > 0 ? round($subtotal / $quantity, 4) : 0;

            $availableStock = ($producto->quantity ?? 0) + ($existingDeliveredByProduct[$productId] ?? 0);
            if ($this->shouldAffectInventory($statusDetalle) && $availableStock < $quantity) {
                return response()->json(['message' => "Stock insuficiente para '{$producto->name}'."], 422);
            }

            $detalleSanitizado[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit' => $unitLabel,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'status' => $statusDetalle,
                'payment_status' => $paymentStatusDetalle,
                'amount_paid' => $amountPaid,
                'difference' => $differenceDetalle,
                'warehouse' => $warehouseDetalle,
                'delivery_type' => $deliveryDetalle,
                'payment_method' => $paymentMethodDetalle,
            ];

            $productosUsados[] = $productId;
        }

        $detalleCollection = collect($detalleSanitizado);
        $total = $detalleCollection->sum('subtotal');
        $totalPagado = $detalleCollection->sum('amount_paid');
        $statusVenta = $this->resolveSaleStatusFromDetails($detalleCollection->pluck('status'));
        $requestedPaymentStatus = strtolower($request->input('payment_status', $venta->payment_status ?? 'pending'));
        $allowedStatuses = ['pending', 'paid', 'to_collect', 'change', 'cancelled'];
        if (!in_array($requestedPaymentStatus, $allowedStatuses, true)) {
            $requestedPaymentStatus = $this->calculatePaymentStatus($total, $totalPagado);
        }
        $paymentStatusVenta = $requestedPaymentStatus;
        $differenceVenta = round($total - $totalPagado, 2);
        $saleDate = $request->input('sale_date') ?: Carbon::today()->format('Y-m-d');

        DB::transaction(function () use (
            $venta,
            $request,
            $detalleCollection,
            $statusVenta,
            $paymentStatusVenta,
            $total,
            $totalPagado,
            $differenceVenta,
            $productos,
            $originalData,
            $originalDetails,
            $saleDate,
            $paymentMethodVenta
        ) {
            foreach ($venta->detalles as $detalleAnterior) {
                if ($this->shouldAffectInventory($detalleAnterior->status)) {
                    $producto = $productos->get($detalleAnterior->product_id) ?? Product::find($detalleAnterior->product_id);
                    if ($producto) {
                        $producto->increment('quantity', $detalleAnterior->quantity);
                        $this->actualizarEstadoProducto($producto);
                    }
                }
            }

            DetalleVenta::where('sale_id', $venta->id)->delete();
            Inventory::where('reference_id', $venta->id)->delete();

            foreach ($detalleCollection as $detalle) {
                DetalleVenta::create([
                    'sale_id' => $venta->id,
                    'product_id' => $detalle['product_id'],
                    'quantity' => $detalle['quantity'],
                    'unit' => $detalle['unit'],
                    'unit_price' => $detalle['unit_price'],
                    'subtotal' => $detalle['subtotal'],
                    'status' => $detalle['status'],
                    'payment_status' => $detalle['payment_status'],
                    'amount_paid' => $detalle['amount_paid'],
                    'difference' => $detalle['difference'],
                    'warehouse' => $detalle['warehouse'],
                    'delivery_type' => $detalle['delivery_type'],
                    'payment_method' => $detalle['payment_method'],
                ]);

                Inventory::create([
                    'product_id' => $detalle['product_id'],
                    'type' => 'sale',
                    'quantity' => $this->shouldAffectInventory($detalle['status']) ? -$detalle['quantity'] : 0,
                    'reason' => $this->inventoryReason($detalle['status'], $venta->id),
                    'reference_id' => $venta->id,
                    'user_id' => auth()->id(),
                ]);

                if ($this->shouldAffectInventory($detalle['status'])) {
                    $producto = $productos->get($detalle['product_id']);
                    if ($producto) {
                        $producto->decrement('quantity', $detalle['quantity']);
                        $this->actualizarEstadoProducto($producto);
                    }
                }
            }

            $venta->update([
                'customer_id' => $request->customer_id,
                'tipodocumento_id' => $request->tipodocumento_id,
                'sale_date' => $saleDate,
                'status' => $statusVenta,
                'payment_method' => $paymentMethodVenta,
                'delivery_type' => $request->input('delivery_type', $venta->delivery_type),
                'warehouse' => $request->input('warehouse', $venta->warehouse),
                'total_price' => $total,
                'amount_paid' => $totalPagado,
                'payment_status' => $paymentStatusVenta,
                'difference' => $differenceVenta,
                'codigo' => $request->input('codigo') ?? $venta->codigo,
            ]);

            $venta->refresh()->load('detalles');

            $this->logVenta($venta->id, 'updated', [
                'old_data' => $originalData,
                'old_details' => $originalDetails,
            ], [
                'new_data' => $venta->toArray(),
                'new_details' => $detalleCollection->toArray(),
            ]);
        });

        return response()->json(['message' => 'Venta actualizada correctamente.']);
    }

    public function updateDetail(Request $request, $detailId)
    {
        $detalle = DetalleVenta::with(['venta.detalles', 'producto'])->findOrFail($detailId);
        $venta = $detalle->venta;
        $this->assertCanManageDetalle($detalle);
        $restrictedWarehouse = $this->resolveWarehouseScopeForUser(Auth::user());

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tipodocumento_id' => 'required|exists:tipodocumento,id',
            'sale_date' => 'nullable|date',
            'payment_method' => $this->paymentMethodRule(),
            'delivery_type' => 'nullable|in:pickup,delivery',
            'warehouse' => 'nullable|in:curva,milla,santa_carolina',
        ]);

        $detailInput = $request->input('detail');

        if (!is_array($detailInput)) {
            return response()->json([
                'message' => 'La informacion del detalle es invalida.',
            ], 422);
        }

        $paymentMethodVenta = $this->normalizePaymentMethod($request->payment_method)
            ?? $this->normalizePaymentMethod($venta->payment_method)
            ?? 'efectivo';

        Validator::make($detailInput, [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:1',
            'unit' => 'nullable|string|max:255',
            'subtotal' => 'required|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:pending,paid,to_collect,change,cancelled',
            'status' => 'nullable|in:pending,in_progress,delivered,cancelled',
            'difference' => 'nullable|numeric',
            'warehouse' => 'nullable|in:curva,milla,santa_carolina',
            'delivery_type' => 'nullable|in:pickup,delivery',
            'payment_method' => $this->paymentMethodRule(),
        ])->validate();

        $productId = (int) $detailInput['product_id'];
        $quantity = (int) $detailInput['quantity'];
        $unitLabel = $this->formatUnitValue($detailInput['unit'] ?? $detalle->unit);
        $subtotal = round((float) $detailInput['subtotal'], 2);
        $rawAmountPaid = isset($detailInput['amount_paid']) ? max(0, (float) $detailInput['amount_paid']) : 0.0;
        $normalizedStatus = $this->normalizeDetailStatus($detailInput['status'] ?? $detalle->status);
        $normalizedPaymentStatus = $this->normalizeDetailPaymentStatus($detailInput['payment_status'] ?? $detalle->payment_status, $subtotal, $rawAmountPaid);
        if ($normalizedPaymentStatus === 'paid') {
            $amountPaid = $subtotal;
        } elseif ($normalizedPaymentStatus === 'cancelled') {
            $amountPaid = 0;
        } else {
            $amountPaid = round($rawAmountPaid, 2);
        }
        $difference = round($subtotal - $amountPaid, 2);
        $unitPrice = $quantity > 0 ? round($subtotal / $quantity, 4) : 0;
        $warehouseDetalle = strtolower(trim((string) ($detailInput['warehouse'] ?? $detalle->warehouse ?? $venta->warehouse)));
        $deliveryDetalle = $detailInput['delivery_type'] ?? $detalle->delivery_type ?? $venta->delivery_type;
        $paymentMethodDetalle = $detailInput['payment_method']
            ?? $this->normalizePaymentMethod($detalle->payment_method)
            ?? $paymentMethodVenta;

        if (!in_array($warehouseDetalle, ['curva', 'milla', 'santa_carolina'], true)) {
            return response()->json(['message' => 'Almacen invalido para el detalle.'], 422);
        }

        if ($restrictedWarehouse && $warehouseDetalle !== $restrictedWarehouse) {
            return response()->json(['message' => 'No tienes permiso para asignar este detalle a otro almacén.'], 403);
        }

        if (!in_array($deliveryDetalle, ['pickup', 'delivery'], true)) {
            return response()->json(['message' => 'Tipo de entrega invalido para el detalle.'], 422);
        }

        if ($paymentMethodDetalle) {
            $normalizedPaymentMethod = $this->normalizePaymentMethod($paymentMethodDetalle);
            if ($normalizedPaymentMethod === null) {
                return response()->json(['message' => 'Metodo de pago invalido para el detalle.'], 422);
            }
            $paymentMethodDetalle = $normalizedPaymentMethod;
        } else {
            $paymentMethodDetalle = null;
        }

        $productoNuevo = Product::findOrFail($productId);
        if ($productoNuevo->status === 'archived') {
            return response()->json(['message' => "El producto '{$productoNuevo->name}' esta archivado."], 422);
        }

        $oldDetailData = [
            'product_id' => $detalle->product_id,
            'quantity' => $detalle->quantity,
            'unit' => $detalle->unit,
            'subtotal' => $detalle->subtotal,
            'status' => $detalle->status,
            'payment_status' => $detalle->payment_status,
            'amount_paid' => $detalle->amount_paid,
            'difference' => $detalle->difference,
            'warehouse' => $detalle->warehouse,
            'delivery_type' => $detalle->delivery_type,
            'payment_method' => $this->normalizePaymentMethod($detalle->payment_method) ?? $paymentMethodVenta,
        ];

        try {
            DB::transaction(function () use (
                $detalle,
                $venta,
                $request,
                $productoNuevo,
                $productId,
                $quantity,
                $unitLabel,
                $unitPrice,
                $subtotal,
                $amountPaid,
                $difference,
                $normalizedStatus,
                $normalizedPaymentStatus,
                $warehouseDetalle,
                $deliveryDetalle,
                $paymentMethodDetalle,
                $oldDetailData,
                $paymentMethodVenta
            ) {
                $oldStatus = $detalle->status;
                $oldProductId = $detalle->product_id;
                $oldQuantity = $detalle->quantity;

                if ($this->shouldAffectInventory($oldStatus)) {
                    Inventory::create([
                        'product_id' => $oldProductId,
                        'type' => 'adjustment_sale',
                        'quantity' => $oldQuantity,
                        'reason' => 'Ajuste por edicion de detalle de venta ID: ' . $venta->id,
                        'reference_id' => $venta->id,
                        'user_id' => auth()->id(),
                    ]);

                    $productoAnterior = Product::find($oldProductId);
                    if ($productoAnterior) {
                        $productoAnterior->increment('quantity', $oldQuantity);
                        $this->actualizarEstadoProducto($productoAnterior);
                    }
                }

                if ($this->shouldAffectInventory($normalizedStatus)) {
                    $productoNuevo->refresh();
                    if ($productoNuevo->quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'detail.quantity' => "Stock insuficiente para '{$productoNuevo->name}'.",
                        ]);
                    }
                }

                $detalle->update([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit' => $unitLabel,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'status' => $normalizedStatus,
                    'payment_status' => $normalizedPaymentStatus,
                    'amount_paid' => $amountPaid,
                    'difference' => $difference,
                    'warehouse' => $warehouseDetalle,
                    'delivery_type' => $deliveryDetalle,
                    'payment_method' => $paymentMethodDetalle,
                ]);

                if ($this->shouldAffectInventory($normalizedStatus)) {
                    Inventory::create([
                        'product_id' => $productId,
                        'type' => 'adjustment_sale',
                        'quantity' => -$quantity,
                        'reason' => 'Actualizacion de detalle de venta ID: ' . $venta->id,
                        'reference_id' => $venta->id,
                        'user_id' => auth()->id(),
                    ]);

                    $productoNuevo->decrement('quantity', $quantity);
                    $this->actualizarEstadoProducto($productoNuevo);
                }

                $saleDate = $request->input('sale_date') ?: Carbon::today()->format('Y-m-d');

                $venta->load('detalles');
                $activeDetails = $venta->detalles->where('status', '!=', 'cancelled');
                $total = $activeDetails->sum('subtotal');
                $amountTotal = $activeDetails->sum('amount_paid');
                $requestedPaymentStatus = strtolower($request->input('payment_status', $venta->payment_status ?? 'pending'));
                $allowedStatuses = ['pending', 'paid', 'to_collect', 'change', 'cancelled'];
                if (!in_array($requestedPaymentStatus, $allowedStatuses, true)) {
                    $requestedPaymentStatus = $this->calculatePaymentStatus($total, $amountTotal);
                }

                $venta->update([
                    'customer_id' => $request->input('customer_id'),
                    'tipodocumento_id' => $request->input('tipodocumento_id'),
                    'sale_date' => $saleDate,
                    'payment_method' => $paymentMethodVenta,
                    'delivery_type' => $request->input('delivery_type', $venta->delivery_type),
                    'warehouse' => $request->input('warehouse', $venta->warehouse),
                    'total_price' => $total,
                    'amount_paid' => $amountTotal,
                    'difference' => round($total - $amountTotal, 2),
                    'status' => $this->resolveSaleStatusFromDetails($venta->detalles->pluck('status')),
                    'payment_status' => $requestedPaymentStatus,
                ]);

                $transactionAmount = $venta->status === 'cancelled' ? 0 : $venta->total_price;

                $detalle->refresh();

                $this->logVenta($venta->id, 'detail_updated', [
                    'old_detail' => $oldDetailData,
                ], [
                    'new_detail' => [
                        'product_id' => $detalle->product_id,
                        'quantity' => $detalle->quantity,
                        'unit' => $detalle->unit,
                        'subtotal' => $detalle->subtotal,
                        'status' => $detalle->status,
                        'payment_status' => $detalle->payment_status,
                        'amount_paid' => $detalle->amount_paid,
                        'difference' => $detalle->difference,
                        'warehouse' => $detalle->warehouse,
                        'delivery_type' => $detalle->delivery_type,
                        'payment_method' => $detalle->payment_method,
                    ],
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Ocurrio un problema al actualizar el detalle.',
                'error' => $th->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Detalle actualizado correctamente.']);
    }

    public function destroyDetail($detailId)
    {
        $detalle = DetalleVenta::with(['venta.detalles', 'producto'])->findOrFail($detailId);
        $venta = $detalle->venta;
        $this->assertCanManageDetalle($detalle);

        $oldDetailData = $detalle->toArray();

        DB::transaction(function () use ($detalle, $venta, $oldDetailData) {
            $producto = $detalle->producto ?? Product::find($detalle->product_id);

            if ($this->shouldAffectInventory($detalle->status) && $producto) {
                $producto->increment('quantity', $detalle->quantity);
                $this->actualizarEstadoProducto($producto);
            }

            Inventory::create([
                'product_id' => $detalle->product_id,
                'type' => 'adjustment_sale',
                'quantity' => $this->shouldAffectInventory($detalle->status) ? $detalle->quantity : 0,
                'reason' => 'Eliminacion de detalle de venta ID: ' . $venta->id,
                'reference_id' => $venta->id,
                'user_id' => auth()->id(),
            ]);

            $detalle->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'amount_paid' => 0,
                'difference' => $detalle->subtotal,
            ]);

            $venta->refresh()->load('detalles');

            $activeDetails = $venta->detalles->where('status', '!=', 'cancelled');

            if ($activeDetails->isEmpty()) {
                $venta->update([
                    'total_price' => 0,
                    'amount_paid' => 0,
                    'difference' => 0,
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                ]);
            } else {
                $total = $activeDetails->sum('subtotal');
                $amountTotal = $activeDetails->sum('amount_paid');
                $nuevoEstado = $this->resolveSaleStatusFromDetails($venta->detalles->pluck('status'));
                $nuevoEstadoPago = $this->calculatePaymentStatus($total, $amountTotal);

                if ($nuevoEstado === 'cancelled') {
                    $nuevoEstadoPago = 'cancelled';
                }

                $venta->update([
                    'total_price' => $total,
                    'amount_paid' => $amountTotal,
                    'difference' => round($total - $amountTotal, 2),
                    'status' => $nuevoEstado,
                    'payment_status' => $nuevoEstadoPago,
                ]);
            }

            $this->logVenta($venta->id, 'detail_deleted', [
                'deleted_detail' => $oldDetailData,
            ], [
                'updated_detail' => $detalle->toArray(),
                'remaining_details' => $venta->detalles->toArray(),
                'venta' => $venta->toArray(),
            ]);
        });

        return response()->json(['message' => 'Producto eliminado de la venta.']);
    }

    public function destroy($id)
    {
        $venta = Venta::with('detalles.producto')->findOrFail($id);
        $restrictedWarehouse = $this->resolveWarehouseScopeForUser(Auth::user());

        if ($restrictedWarehouse) {
            $totalDetalles = $venta->detalles->count();
            $permitidos = $venta->detalles
                ->filter(function (DetalleVenta $detalle) use ($venta, $restrictedWarehouse) {
                    $detalleWarehouse = strtolower(trim((string) ($detalle->warehouse ?? $venta->warehouse)));
                    return $detalleWarehouse === $restrictedWarehouse;
                })
                ->count();

            if ($permitidos === 0 || $permitidos !== $totalDetalles) {
                abort(403, 'No tienes permiso para anular esta venta.');
            }
        }

        if ($venta->status === 'cancelled') {
            return response()->json(['message' => 'Esta venta ya fue anulada.'], 400);
        }

        $ventaOriginal = $venta->toArray();
        $detallesOriginal = $venta->detalles->map->toArray();

        DB::transaction(function () use ($venta, $ventaOriginal, $detallesOriginal) {
            foreach ($venta->detalles as $detalle) {
                if ($this->shouldAffectInventory($detalle->status)) {
                    $producto = $detalle->producto ?? Product::find($detalle->product_id);
                    if ($producto) {
                        $producto->increment('quantity', $detalle->quantity);
                        $this->actualizarEstadoProducto($producto);
                    }
                }

                Inventory::create([
                    'product_id' => $detalle->product_id,
                    'type' => 'adjustment_sale',
                    'quantity' => $this->shouldAffectInventory($detalle->status) ? $detalle->quantity : 0,
                    'reason' => 'Anulacin de venta ID: ' . $venta->id . ' (estado: ' . $venta->status . ')',
                    'reference_id' => $venta->id,
                    'user_id' => auth()->id(),
                ]);

                $detalle->update([
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                    'amount_paid' => 0,
                    'difference' => $detalle->subtotal,
                ]);
            }

            $venta->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'amount_paid' => 0,
                'difference' => $venta->total_price,
            ]);

            $venta->refresh()->load('detalles');

            $this->logVenta($venta->id, 'cancelled', [
                'old_data' => $ventaOriginal,
                'old_details' => $detallesOriginal,
            ], [
                'new_data' => $venta->toArray(),
                'new_details' => $venta->detalles->map->toArray(),
            ]);
        });

        return response()->json(['message' => 'Venta anulada correctamente.']);
    }

    public function getData()
    {
        $detalles = DetalleVenta::query()
            ->select([
                'detalle_ventas.id as detalle_id',
                'detalle_ventas.sale_id',
                'detalle_ventas.product_id',
                'detalle_ventas.quantity',
                'detalle_ventas.unit',
                'detalle_ventas.subtotal',
                'detalle_ventas.amount_paid',
                'detalle_ventas.difference',
                'detalle_ventas.payment_status as detalle_payment_status',
                'detalle_ventas.status as detalle_status',
                'detalle_ventas.warehouse as detalle_warehouse',
                'detalle_ventas.delivery_type as detalle_delivery_type',
                'detalle_ventas.payment_method as detalle_payment_method',
                'ventas.sale_date',
                'ventas.payment_method',
                'ventas.status as venta_status',
                'ventas.warehouse',
                'customers.name as customer_name',
                'users.name as user_name',
                'products.name as product_name',
                'products.status as product_status',
                DB::raw('(SELECT COUNT(*) FROM detalle_ventas dv2 WHERE dv2.id <= detalle_ventas.id) as row_number'),
            ])
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'ventas.customer_id')
            ->leftJoin('users', 'users.id', '=', 'ventas.user_id')
            ->leftJoin('products', 'products.id', '=', 'detalle_ventas.product_id');

        $currentUser = Auth::user();
        $restrictedWarehouse = $this->resolveWarehouseScopeForUser($currentUser);

        if ($restrictedWarehouse) {
            $detalles->where(function ($query) use ($restrictedWarehouse) {
                $query->whereRaw('LOWER(detalle_ventas.warehouse) = ?', [$restrictedWarehouse])
                    ->orWhere(function ($subQuery) use ($restrictedWarehouse) {
                        $subQuery->whereNull('detalle_ventas.warehouse')
                            ->whereRaw('LOWER(ventas.warehouse) = ?', [$restrictedWarehouse]);
                    });
            });
        }

        return DataTables::of($detalles)
            ->orderColumn('row_number', 'row_number $1')
            ->addColumn('row_number', fn ($detalle) => (int) ($detalle->row_number ?? 0))
            ->addColumn('id', fn ($detalle) => $detalle->sale_id)
            ->addColumn('fecha', fn ($detalle) => Carbon::parse($detalle->sale_date)->format('d/m/Y'))
            ->addColumn('cliente', fn ($detalle) => $detalle->customer_name ?? '-')
            ->addColumn('producto', function ($detalle) {
                $nombre = $detalle->product_name ?? 'Sin nombre';
                if (($detalle->product_status ?? null) === 'archived') {
                    $nombre .= ' (archivado)';
                }
                return $nombre;
            })
            ->addColumn('cantidad', fn ($detalle) => $detalle->quantity)
            ->addColumn('unidad', fn ($detalle) => $detalle->unit)
            ->addColumn('almacen', function ($detalle) {
                return match ($detalle->detalle_warehouse) {
                    'milla' => 'Milla',
                    'santa_carolina' => 'Santa Carolina',
                    default => 'Curva',
                };
            })
            ->addColumn('total', function ($detalle) {
                if ($detalle->detalle_status === 'cancelled') {
                    return 'S/ 0.00';
                }

                return 'S/ ' . number_format($detalle->subtotal, 2);
            })
            ->addColumn('monto_pagado', function ($detalle) {
                if ($detalle->detalle_status === 'cancelled') {
                    return 'S/ 0.00';
                }

                return 'S/ ' . number_format($detalle->amount_paid, 2);
            })
            ->addColumn('diferencia', function ($detalle) {
                if ($detalle->detalle_status === 'cancelled') {
                    return 'S/ 0.00';
                }

                $difference = (float) $detalle->difference;
                if ($difference < 0) {
                    return '<span class="text-danger">-S/ ' . number_format(abs($difference), 2) . '</span>';
                }

                return 'S/ ' . number_format($difference, 2);
            })
            ->addColumn('metodo_pago', fn ($detalle) => $this->paymentMethodLabel($detalle->detalle_payment_method))
            ->addColumn('estado_pago', function ($detalle) {
                return match ($detalle->detalle_payment_status) {
                    'paid' => '<span class="badge bg-success p-2">Pagado</span>',
                    'to_collect' => '<span class="badge bg-info text-dark p-2">Saldo pendiente</span>',
                    'change' => '<span class="badge bg-secondary p-2">Vuelto pendiente</span>',
                    'cancelled' => '<span class="badge bg-danger p-2">Anulado</span>',
                    default => '<span class="badge bg-warning text-dark p-2">Pendiente</span>',
                };
            })
            ->addColumn('estado_pedido', function ($detalle) {
                return match ($detalle->detalle_status) {
                    'delivered' => '<span class="badge bg-success p-2">Entregado</span>',
                    'in_progress' => '<span class="badge bg-primary p-2">En curso</span>',
                    'cancelled' => '<span class="badge bg-danger p-2">Anulado</span>',
                    default => '<span class="badge bg-warning text-dark p-2">Pendiente</span>',
                };
            })
            ->addColumn('acciones', function ($detalle) use ($currentUser) {
                $ventaCancelada = ($detalle->venta_status === 'cancelled');
                $detalleCancelado = ($detalle->detalle_status === 'cancelled');

                if ($ventaCancelada || $detalleCancelado) {
                    return '<span class="text-muted">Sin acciones</span>';
                }

                $acciones = '';

                if ($currentUser && $currentUser->can('administrar.ventas.edit')) {
                    $acciones .= '
                        <button type="button" class="btn btn-sm btn-outline-warning btn-icon waves-effect waves-light edit-btn"
                            data-id="' . $detalle->sale_id . '" data-detail-id="' . $detalle->detalle_id . '" title="Editar">
                            <i class="ri-edit-2-line"></i>
                        </button>';
                }

                if ($currentUser && $currentUser->can('administrar.ventas.delete')) {
                    $acciones .= '
                        <button type="button" class="btn btn-sm btn-outline-danger btn-icon waves-effect waves-light delete-btn"
                            data-id="' . $detalle->sale_id . '" data-detail-id="' . $detalle->detalle_id . '" title="Eliminar">
                            <i class="ri-delete-bin-5-line"></i>
                        </button>';
                }

                $acciones .= '
                    <button type="button" class="btn btn-sm btn-outline-info btn-icon waves-effect waves-light ver-detalle-btn"
                        data-id="' . $detalle->sale_id . '" title="Ver detalle">
                        <i class="ri-eye-line"></i>
                    </button>';

                return $acciones ?: '<span class="text-muted">Sin acciones</span>';
            })
            ->rawColumns(['estado_pedido', 'estado_pago', 'diferencia', 'acciones'])
            ->make(true);
    }

    public function detalle($id)
    {
        $venta = Venta::with('detalles.producto')->findOrFail($id);
        $restrictedWarehouse = $this->resolveWarehouseScopeForUser(Auth::user());

        if ($restrictedWarehouse) {
            $filteredDetalles = $this->filterDetallesByWarehouse($venta, $restrictedWarehouse);
            if ($filteredDetalles->isEmpty()) {
                abort(403, 'No tienes permiso para ver esta venta.');
            }
            $venta->setRelation('detalles', $filteredDetalles);
        }

        $detalle = $venta->detalles->map(function ($item) {
            $producto = $item->producto;
            $nombreBase = $producto->name ?? 'Sin nombre';
            if ($producto && $producto->status === 'archived') {
                $nombreBase .= ' (archivado)';
            }

            return [
                'product_name' => $nombreBase,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'amount_paid' => $item->amount_paid,
                'difference' => $item->difference,
                'status' => $item->status,
                'payment_status' => in_array($item->payment_status, ['paid', 'pending', 'to_collect', 'change', 'cancelled'], true)
                    ? $item->payment_status
                    : 'pending',
                'warehouse' => $item->warehouse,
                'delivery_type' => $item->delivery_type,
                'payment_method' => $this->normalizePaymentMethod($item->payment_method) ?? 'efectivo',
            ];
        });

        return response()->json(['detalle' => $detalle]);
    }

    protected function logVenta($ventaId, $accion, $datosAntes = [], $datosDespues = [])
    {
        VentaLog::create([
            'venta_id' => $ventaId,
            'accion' => $accion,
            'datos_antes' => !empty($datosAntes) ? json_encode($datosAntes) : null,
            'datos_despues' => !empty($datosDespues) ? json_encode($datosDespues) : null,
            'ip' => request()->ip(),
            'user_id' => auth()->id(),
        ]);
    }
}
