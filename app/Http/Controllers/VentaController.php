<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DetalleVenta;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\TipoDocumento;
use App\Models\Transaction;
use App\Models\Venta;
use App\Models\VentaLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class VentaController extends Controller
{
    public function __construct()
    {
        // Solo usuarios con los permisos adecuados pueden acceder a cada accion
        $this->middleware('permission:administrar.ventas.index')->only(['index', 'getData', 'detalle', 'show']);
        $this->middleware('permission:administrar.ventas.create')->only(['store']);
        $this->middleware('permission:administrar.ventas.edit')->only(['update']);
        $this->middleware('permission:administrar.ventas.delete')->only(['destroy']);
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

    private function mapStatusToLegacy(string $status): string
    {
        return match ($status) {
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
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

    private function inventoryReason(string $status, int $ventaId): string
    {
        return match ($status) {
            'delivered' => 'Venta entregada ID: ' . $ventaId,
            'in_progress' => 'Venta en curso ID: ' . $ventaId,
            'cancelled' => 'Venta anulada ID: ' . $ventaId,
            default => 'Venta pendiente ID: ' . $ventaId,
        };
    }

    private function transactionDescription(string $status, int $ventaId): string
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
        $details = json_decode($request->input('details'), true);

        if (!is_array($details) || empty($details)) {
            return response()->json(['message' => 'Detalles invalidos.'], 422);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tipodocumento_id' => 'required|exists:tipodocumento,id',
            'sale_date' => 'nullable|date',
            'payment_method' => 'required|in:cash,card,transfer',
            'delivery_type' => 'required|in:pickup,delivery',
            'warehouse' => 'required|in:curva,milla,santa_carolina',
            'payment_status' => 'nullable|in:pending,paid',
            'amount_paid' => 'nullable|numeric|min:0',
        ]);

        $cliente = Customer::find($request->customer_id);
        if ($cliente && $cliente->status === 'inactive') {
            return response()->json(['message' => "El cliente '{$cliente->name}' esta inactivo."], 422);
        }

        $idsProductos = collect($details)->pluck('product_id')->unique();
        $productos = Product::whereIn('id', $idsProductos)->get()->keyBy('id');

        foreach ($idsProductos as $idProd) {
            $producto = $productos->get($idProd);
            if (!$producto) {
                return response()->json(['message' => "El producto con ID {$idProd} no existe."], 422);
            }
            if ($producto->status === 'archived') {
                return response()->json(['message' => "El producto '{$producto->name}' esta archivado."], 422);
            }
        }

        foreach ($details as $index => $item) {
            if (
                !isset($item['product_id']) || !is_numeric($item['product_id']) ||
                !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] < 1 ||
                !isset($item['unit']) || trim($item['unit']) === '' ||
                !isset($item['subtotal']) || !is_numeric($item['subtotal']) || $item['subtotal'] < 0
            ) {
                return response()->json([
                    'message' => "Detalle invalido en la posicion {$index}.",
                    'errors' => [
                        "details[{$index}]" => ['Debe indicar producto, cantidad, unidad y subtotal validos.'],
                    ],
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            $total = collect($details)->sum(function ($item) {
                return isset($item['subtotal']) ? (float) $item['subtotal'] : 0;
            });

            $status = 'pending';
            $legacyStatus = $this->mapStatusToLegacy($status);
            $saleDate = $request->input('sale_date') ?: Carbon::today()->format('Y-m-d');
            $deliveryType = $request->input('delivery_type', 'pickup');
            $warehouse = $request->input('warehouse', 'curva');
            $requestedPaymentStatus = $request->input('payment_status', 'pending');
            if (!in_array($requestedPaymentStatus, ['pending', 'paid'], true)) {
                $requestedPaymentStatus = 'pending';
            }
            $amountPaid = $requestedPaymentStatus === 'paid' ? $total : 0;
            $difference = $total - $amountPaid;
            $paymentStatus = $requestedPaymentStatus;

            $venta = Venta::create([
                'customer_id' => $request->customer_id,
                'tipodocumento_id' => $request->tipodocumento_id,
                'user_id' => auth()->id(),
                'sale_date' => $saleDate,
                'payment_method' => $request->payment_method,
                'status' => $status,
                'delivery_type' => $deliveryType,
                'warehouse' => $warehouse,
                'total_price' => $total,
                'amount_paid' => $amountPaid,
                'payment_status' => $paymentStatus,
                'difference' => $difference,
                'codigo' => null,
            ]);

            $codigo = 'VNT-' . str_pad((string) $venta->id, 5, '0', STR_PAD_LEFT);
            $venta->update(['codigo' => $codigo]);

            foreach ($details as $item) {
                $producto = $productos->get($item['product_id']);

                $quantity = (int) $item['quantity'];
                $unitValue = isset($item['unit']) ? (float) $item['unit'] : 0;
                $unitLabel = rtrim(rtrim(number_format($unitValue, 2, '.', ''), '0'), '.');
                $subtotal = isset($item['subtotal']) ? (float) $item['subtotal'] : 0;
                $unitPrice = $quantity > 0 ? $subtotal / $quantity : 0;

                if ($legacyStatus === 'completed' && $producto->quantity < $quantity) {
                    throw new \RuntimeException('Stock insuficiente para: ' . $producto->name);
                }

                $total += $subtotal;

                DetalleVenta::create([
                    'sale_id' => $venta->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit' => $unitLabel,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                Inventory::create([
                    'product_id' => $item['product_id'],
                    'type' => 'sale',
                    'quantity' => $legacyStatus === 'completed' ? -$quantity : 0,
                    'reason' => $this->inventoryReason($status, $venta->id),
                    'user_id' => auth()->id(),
                    'reference_id' => $venta->id,
                ]);

                if ($legacyStatus === 'completed') {
                    $producto->decrement('quantity', $quantity);
                    $this->actualizarEstadoProducto($producto);
                }
            }

            $transactionAmount = $status === 'cancelled' ? 0 : $total;

            Transaction::create([
                'type' => 'sale',
                'amount' => $transactionAmount,
                'reference_id' => $venta->id,
                'description' => $this->transactionDescription($status, $venta->id),
                'user_id' => auth()->id(),
            ]);

            $this->logVenta($venta->id, 'created', [], [
                'new_data' => $venta->toArray(),
                'new_details' => $details,
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
                'payment_method' => $venta->payment_method,
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
        $originalStatus = $venta->status;

        $details = json_decode($request->input('details'), true);
        if (!is_array($details) || empty($details)) {
            return response()->json(['message' => 'Detalles invalidos.'], 422);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tipodocumento_id' => 'required|exists:tipodocumento,id',
            'sale_date' => 'nullable|date',
            'payment_method' => 'required|in:cash,card,transfer',
            'status' => 'nullable|in:pending,in_progress,delivered,cancelled',
            'delivery_type' => 'required|in:pickup,delivery',
            'warehouse' => 'required|in:curva,milla,santa_carolina',
            'amount_paid' => 'nullable|numeric|min:0',
            'codigo' => 'nullable|string|max:50|unique:ventas,codigo,' . $venta->id,
        ]);

        foreach ($details as $index => $item) {
            if (
                !isset($item['product_id']) || !is_numeric($item['product_id']) ||
                !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] < 1 ||
                !isset($item['unit']) || trim($item['unit']) === '' ||
                !isset($item['subtotal']) || !is_numeric($item['subtotal']) || $item['subtotal'] < 0
            ) {
                return response()->json([
                    'message' => "Detalle invalido en la posicion {$index}.",
                    'errors' => [
                        "details[{$index}]" => ['Debe indicar producto, cantidad, unidad y subtotal validos.'],
                    ],
                ], 422);
            }
        }

        $requestedPaymentStatus = $request->input('payment_status', $venta->payment_status ?? 'pending');
        if (!in_array($requestedPaymentStatus, ['pending', 'paid'], true)) {
            $requestedPaymentStatus = 'pending';
        }

        DB::transaction(function () use ($venta, $request, $details, $originalData, $originalStatus, $requestedPaymentStatus) {
            $status = $request->input('status') ?? 'pending';
            $legacyStatus = $this->mapStatusToLegacy($status);
            $saleDate = $request->input('sale_date') ?: Carbon::today()->format('Y-m-d');
            $deliveryType = $request->input('delivery_type', 'pickup');
            $warehouse = $request->input('warehouse', 'curva');

            $productIds = collect($details)->pluck('product_id')->unique();
            $productos = Product::whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($productIds as $productId) {
                $producto = $productos->get($productId);
                if (!$producto) {
                    throw new \RuntimeException("El producto con ID {$productId} no existe.");
                }
                if ($producto->status === 'archived') {
                    throw new \RuntimeException("El producto '{$producto->name}' esta archivado.");
                }
            }

            // Revertir stock si la venta original estaba entregada
            if ($originalStatus === 'delivered') {
                foreach ($venta->detalles as $detalle) {
                    $producto = $productos->get($detalle->product_id) ?? Product::find($detalle->product_id);
                    if ($producto) {
                        $producto->increment('quantity', $detalle->quantity);
                        $this->actualizarEstadoProducto($producto);
                    }
                }
            }

            // Limpiar detalles e inventario anteriores
            DetalleVenta::where('sale_id', $venta->id)->delete();
            Inventory::where('reference_id', $venta->id)->delete();

            $total = 0;

            foreach ($details as $item) {
                $producto = $productos->get($item['product_id']);

                $quantity = (int) $item['quantity'];
                $unitValue = isset($item['unit']) ? (float) $item['unit'] : 0;
                $unitLabel = rtrim(rtrim(number_format($unitValue, 2, '.', ''), '0'), '.');
                $subtotal = isset($item['subtotal']) ? (float) $item['subtotal'] : 0;
                $unitPrice = $quantity > 0 ? $subtotal / $quantity : 0;
                $total += $subtotal;

                if ($legacyStatus === 'completed' && $producto->quantity < $quantity) {
                    throw new \RuntimeException('Stock insuficiente para: ' . $producto->name);
                }

                DetalleVenta::create([
                    'sale_id' => $venta->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit' => $unitLabel,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                Inventory::create([
                    'product_id' => $item['product_id'],
                    'type' => 'sale',
                    'quantity' => $legacyStatus === 'completed' ? -$quantity : 0,
                    'reason' => $this->inventoryReason($status, $venta->id),
                    'reference_id' => $venta->id,
                    'user_id' => auth()->id(),
                ]);

                if ($legacyStatus === 'completed') {
                    $producto->decrement('quantity', $quantity);
                    $this->actualizarEstadoProducto($producto);
                }
            }

            $amountPaid = $requestedPaymentStatus === 'paid' ? $total : 0;
            $difference = $total - $amountPaid;
            $paymentStatus = $requestedPaymentStatus;

            $venta->update([
                'customer_id' => $request->customer_id,
                'tipodocumento_id' => $request->tipodocumento_id,
                'sale_date' => $saleDate,
                'status' => $status,
                'payment_method' => $request->payment_method,
                'delivery_type' => $deliveryType,
                'warehouse' => $warehouse,
                'total_price' => $total,
                'amount_paid' => $amountPaid,
                'payment_status' => $paymentStatus,
                'difference' => $difference,
                'codigo' => $request->input('codigo') ?? $venta->codigo,
            ]);

            Transaction::where('reference_id', $venta->id)
                ->where('type', 'sale')
                ->update([
                    'amount' => $status === 'cancelled' ? 0 : $total,
                    'description' => $this->transactionDescription($status, $venta->id),
                ]);

            $this->logVenta($venta->id, 'updated', [
                'old_data' => $originalData,
            ], [
                'new_data' => $venta->toArray(),
                'new_details' => $details,
            ]);
        });

        return response()->json(['message' => 'Venta actualizada correctamente.']);
    }

    public function destroy($id)
    {
        $venta = Venta::with('detalles.producto')->findOrFail($id);

        if ($venta->status === 'cancelled') {
            return response()->json(['message' => 'Esta venta ya fue anulada.'], 400);
        }

        DB::transaction(function () use ($venta) {
            if ($venta->status === 'delivered') {
                foreach ($venta->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto) {
                        $producto->increment('quantity', $detalle->quantity);
                        $this->actualizarEstadoProducto($producto);
                    }
                }
            }

            foreach ($venta->detalles as $detalle) {
                Inventory::create([
                    'product_id' => $detalle->product_id,
                    'type' => 'adjustment_sale',
                    'quantity' => $venta->status === 'delivered' ? $detalle->quantity : 0,
                    'reason' => 'Anulacin de venta ID: ' . $venta->id . ' (estado: ' . $venta->status . ')',
                    'reference_id' => $venta->id,
                    'user_id' => auth()->id(),
                ]);
            }

            $venta->update([
                'status' => 'cancelled',
                'payment_status' => 'pending',
                'amount_paid' => 0,
                'difference' => $venta->total_price,
            ]);

            Transaction::where('reference_id', $venta->id)
                ->where('type', 'sale')
                ->update([
                    'description' => 'Venta anulada ID: ' . $venta->id,
                    'amount' => 0,
                ]);

            $this->logVenta($venta->id, 'cancelled', [
                'old_data' => $venta->toArray(),
                'old_details' => $venta->detalles->toArray(),
            ]);
        });

        return response()->json(['message' => 'Venta anulada correctamente.']);
    }

    public function getData()
    {
        $ventas = Venta::with(['customer', 'user', 'tipodocumento'])->select('ventas.*');

        $currentUser = Auth::user();

        return DataTables::of($ventas)
            ->addColumn('cliente', fn ($v) => $v->customer->name ?? '-')
            ->addColumn('tipo_documento', fn ($v) => $v->tipodocumento->name ?? '-')
            ->addColumn('usuario', fn ($v) => $v->user->name ?? '-')
            ->addColumn('fecha', fn ($v) => Carbon::parse($v->sale_date)->format('d/m/Y'))
            ->addColumn('total', fn ($v) => 'S/ ' . number_format($v->total_price, 2))
            ->addColumn('monto_pagado', fn ($v) => 'S/ ' . number_format($v->amount_paid, 2))
            ->addColumn('diferencia', function ($v) {
                $difference = (float) ($v->difference ?? ($v->total_price - $v->amount_paid));

                if ($difference < 0) {
                    return '<span class="text-danger">-S/ ' . number_format(abs($difference), 2) . '</span>';
                }

                return 'S/ ' . number_format($difference, 2);
            })
            ->addColumn('tipo_entrega', fn ($v) => $v->delivery_type === 'delivery' ? 'Enviar' : 'Recoge')
            ->addColumn('almacen', function ($v) {
                return match ($v->warehouse) {
                    'milla' => 'Milla',
                    'santa_carolina' => 'Santa Carolina',
                    default => 'Curva',
                };
            })
            ->addColumn('estado_pedido', function ($v) {
                return match ($v->status) {
                    'delivered' => '<span class="badge bg-success p-2">Entregado</span>',
                    'in_progress' => '<span class="badge bg-primary p-2">En curso</span>',
                    'cancelled' => '<span class="badge bg-danger p-2">Anulado</span>',
                    default => '<span class="badge bg-warning text-dark p-2">Pendiente</span>',
                };
            })
            ->addColumn('estado_pago', function ($v) {
                return match ($v->payment_status) {
                    'paid' => '<span class="badge bg-success p-2">Cancelado</span>',
                    'to_collect' => '<span class="badge bg-info text-dark p-2">Saldo pendiente</span>',
                    'change' => '<span class="badge bg-secondary p-2">Vuelto pendiente</span>',
                    default => '<span class="badge bg-warning text-dark p-2">Pendiente</span>',
                };
            })
            ->addColumn('metodo_pago', fn ($v) => $v->payment_method ?? '-')
            ->addColumn('acciones', function ($v) use ($currentUser) {
                if ($v->status === 'cancelled') {
                    return '';
                }

                $acciones = '';

                if ($currentUser && $currentUser->can('administrar.ventas.edit')) {
                    $acciones .= '
                        <button type="button" class="btn btn-sm btn-outline-warning btn-icon waves-effect waves-light edit-btn"
                            data-id="' . $v->id . '" title="Editar">
                            <i class="ri-edit-2-line"></i>
                        </button>';
                }

                if ($currentUser && $currentUser->can('administrar.ventas.delete')) {
                    $acciones .= '
                        <button type="button" class="btn btn-sm btn-outline-danger btn-icon waves-effect waves-light delete-btn"
                            data-id="' . $v->id . '" title="Eliminar">
                            <i class="ri-delete-bin-5-line"></i>
                        </button>';
                }

                $acciones .= '
                    <button type="button" class="btn btn-sm btn-outline-info btn-icon waves-effect waves-light ver-detalle-btn"
                        data-id="' . $v->id . '" title="Ver detalle">
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












