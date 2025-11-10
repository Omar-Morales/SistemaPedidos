<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Compra;
use App\Models\CompraLog;
use App\Models\DetalleCompra;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CompraController extends Controller
{
    public function __construct()
    {
        // 🔹 Solo el ADMINISTRADOR y MANTENEDOR pueden acceder a este controlador
        $this->middleware('permission:administrar.compras.index')->only(['index', 'getData', 'detalle', 'show']);
        $this->middleware('permission:administrar.compras.create')->only(['store']);
        $this->middleware('permission:administrar.compras.edit')->only(['update']);
        $this->middleware('permission:administrar.compras.delete')->only(['destroy']);
    }


    public function index()
    {
        $tiposDocumento = TipoDocumento::where('type', 'compra')->get();
        return view('compra.index', compact('tiposDocumento'));
    }

    public function create()
    {
    }

    protected function actualizarEstadoProducto(Product $producto)
    {
        $producto->refresh();

        if ($producto->quantity <= 0 && $producto->status !== 'sold') {
            $producto->update(['status' => 'sold']);
        } elseif ($producto->quantity > 0 && $producto->status !== 'available') {
            $producto->update(['status' => 'available']);
        }
    }


    public function store(Request $request)
{
    $details = json_decode($request->details, true);

    if (!is_array($details) || empty($details)) {
        return response()->json(['message' => 'Detalles inválidos.'], 422);
    }

    if ($request->has('codigo_numero') && $request->codigo_numero === '') {
        $request->merge(['codigo_numero' => null]);
    }

    $request->validate([
        'supplier_id' => 'required|exists:suppliers,id',
        'tipodocumento_id' => 'required|exists:tipodocumento,id',
        'purchase_date' => 'required|date',
        'status' => 'nullable|in:completed,pending',
        'codigo_numero' => 'nullable|integer|min:0',
        'details' => 'required',
        'details.*.product_id' => 'required|exists:products,id',
        'details.*.quantity' => 'required|integer|min:1',
        'details.*.unit_cost' => 'required|numeric|min:0',
    ]);

    // Validar proveedor activo
    $supplier = Supplier::find($request->supplier_id);
    if ($supplier && $supplier->status === 'inactive') {
        return response()->json(['message' => "El proveedor '{$supplier->name}' está inactivo."], 422);
    }

    $idsProductos = collect($details)->pluck('product_id')->unique();
    $productos = Product::whereIn('id', $idsProductos)->get()->keyBy('id');

    // Validar productos existentes y no archivados
    foreach ($idsProductos as $idProd) {
        $producto = $productos[$idProd] ?? null;
        if (!$producto) {
            return response()->json(['message' => "Producto con ID $idProd no existe."], 422);
        }
        if ($producto->status === 'archived') {
            return response()->json(['message' => "Producto '{$producto->name}' está archivado."], 422);
        }
    }

    DB::beginTransaction();

    try {
        $total = collect($details)->sum(fn($item) => $item['quantity'] * $item['unit_cost']);

        $compra = Compra::create([
            'supplier_id' => $request->supplier_id,
            'tipodocumento_id' => $request->tipodocumento_id,
            'user_id' => auth()->id(),
            'purchase_date' => $request->purchase_date,
            'total_cost' => $total,
            'status' => $request->status ?? 'completed',
            'codigo' => null,
            'codigo_numero' => $request->codigo_numero,
        ]);

        $codigo = 'CMP-' . str_pad($compra->id, 5, '0', STR_PAD_LEFT);
        $compra->update(['codigo' => $codigo]);

        foreach ($details as $item) {
            DetalleCompra::create([
                'purchase_id' => $compra->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'subtotal' => $item['quantity'] * $item['unit_cost'],
            ]);

            // Registrar en INVENTORY
            Inventory::create([
                'product_id' => $item['product_id'],
                'type' => 'purchase',
                'quantity' => $item['quantity'],
                'reason' => $compra->status === 'completed'
                    ? 'Compra ID: ' . $compra->id
                    : 'Compra pendiente ID: ' . $compra->id,
                'reference_id' => $compra->id,
                'user_id' => auth()->id(),
            ]);

            // Aumentar stock solo si es completed
            if ($compra->status === 'completed') {
                $producto = $productos[$item['product_id']];
                $producto->quantity += $item['quantity'];
                $producto->save();
                $this->actualizarEstadoProducto($producto);
            }
        }

        // Registrar TRANSACTION
        // Log de auditoría
        $this->logCompra($compra->id, 'created', [], [
            'new_data' => $compra->toArray(),
            'new_details' => $details,
        ]);

        DB::commit();

        return response()->json(['message' => 'Compra registrada correctamente.']);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error al guardar: ' . $e->getMessage()], 500);
    }
}


    public function show($id)
    {
    /*$compra = Compra::with('detalles')->findOrFail($id);
    return response()->json([
        'id' => $compra->id,
        'proveedor' => $compra->supplier_id,
        'tipo_documento' => $compra->tipodocumento_id,
        'fecha' => Carbon::parse($compra->purchase_date)->format('Y-m-d'),
        'total' => $compra->total_cost,
        'estado' => $compra->status,
        'detalle' => $compra->detalles
    ]);*/
    $compra = Compra::with('detalles')->findOrFail($id);

    // Traer proveedores activos o el proveedor actual si está inactivo
    $proveedoresQuery = Supplier::query();
    $proveedoresQuery->where(function ($q) use ($compra) {
        $q->where('status', 'active')
          ->orWhere('id', $compra->supplier_id);
    });

    $proveedores = $proveedoresQuery->orderBy('id')->get()->map(function ($proveedor) {
        return [
            'id' => $proveedor->id,
            'text' => $proveedor->status === 'active'
                ? $proveedor->name
                : $proveedor->name . ' (inactivo)',
        ];
    });

    return response()->json([
        'compra' => [
        'id' => $compra->id,
        'proveedor' => $compra->supplier_id,
        'tipo_documento' => $compra->tipodocumento_id,
        'fecha' => Carbon::parse($compra->purchase_date)->format('Y-m-d'),
        'total' => $compra->total_cost,
        'estado' => $compra->status,
        'codigo_numero' => $compra->codigo_numero,
        'detalle' => $compra->detalles
        ],
        'proveedores' => $proveedores,
    ]);
    }

    public function edit(string $id)
    {

    }
/*
public function update(Request $request, $id)
{
    $compra = Compra::findOrFail($id);
    $originalData = $compra->toArray(); // 🔹 Guardamos datos previos

    $details = json_decode($request->details, true);

    if (!is_array($details) || empty($details)) {
        return response()->json(['message' => 'Detalles inválidos.'], 422);
    }

    $request->validate([
        'supplier_id' => 'required|exists:suppliers,id',
        'tipodocumento_id' => 'required|exists:tipodocumento,id',
        'purchase_date' => 'required|date',
        'codigo' => 'nullable|string|max:50|unique:compras,codigo,' . $compra->id,
        'details' => 'required',
        'details.*.product_id' => 'required|exists:products,id',
        'details.*.quantity' => 'required|integer|min:1',
        'details.*.unit_cost' => 'required|numeric|min:0',
    ]);

    DB::transaction(function () use ($compra, $request, $details, $originalData) {
        $detallesAnteriores = collect();
        // 🔁 Revertir stock anterior solo si estaba en 'completed'
        if ($compra->status === 'completed') {
            $detallesAnteriores = DetalleCompra::where('purchase_id', $compra->id)->get();
            foreach ($detallesAnteriores as $detalle) {
                $producto = Product::find($detalle->product_id);
                if ($producto) {
                    $producto->quantity -= $detalle->quantity;
                    $producto->save();
                }
            }
        }

        // 🧹 Eliminar registros anteriores
        DetalleCompra::where('purchase_id', $compra->id)->delete();
        Inventory::where('reference_id',$compra->id)->delete();

        // 📦 Calcular nuevo total
        $total = collect($details)->sum(fn($d) => $d['quantity'] * $d['unit_cost']);

        // ✏️ Actualizar datos de la compra
        $compra->update([
            'supplier_id' => $request->supplier_id,
            'tipodocumento_id' => $request->tipodocumento_id,
            'purchase_date' => $request->purchase_date,
            'status' => $request->status ?? 'completed',
            'total_cost' => $total,
            'codigo' => $request->codigo ?? $compra->codigo,
        ]);

            // 🔎 Validación de integridad: todos los productos deben existir
        $idsProductos = collect($details)->pluck('product_id');
        $productos = Product::whereIn('id', $idsProductos)->get()->keyBy('id');

        if ($productos->count() !== $idsProductos->unique()->count()) {
            throw new \Exception('Uno o más productos ya no existen.');
        }

        // 📝 Insertar nuevos detalles e inventario
        foreach ($details as $d) {
            DetalleCompra::create([
                'purchase_id' => $compra->id,
                'product_id' => $d['product_id'],
                'quantity' => $d['quantity'],
                'unit_cost' => $d['unit_cost'],
                'subtotal' => $d['quantity'] * $d['unit_cost'],
            ]);

            Inventory::create([
                'product_id' => $d['product_id'],
                'type' => 'purchase',
                'quantity' => $d['quantity'],
                'reason' => 'Compra ID: ' . $compra->id,
                'reference_id' => $compra->id,
                'user_id' => auth()->id(),
            ]);

            // ✅ Aumentar stock si estado es 'completed'
            if ($compra->status === 'completed') {
                $producto = $productos[$d['product_id']] ?? null;
                if ($producto) {
                    $producto->quantity += $d['quantity'];
                    $producto->save();
                }
            }
        }

        // 💰 Actualizar transacción contable
        // 📝 Registrar log
            $this->logCompra($compra->id, 'updated', [
                'old_data' => $originalData,
                'old_details' => $detallesAnteriores->toArray(),
            ], [
                'new_data' => $compra->getChanges(),
                'new_details' => $details,
            ]);
    });

    return response()->json(['message' => 'Compra actualizada correctamente']);
}*/

    public function update(Request $request, $id)
    {
        $compra = Compra::findOrFail($id);
        $originalData = $compra->toArray();
        $originalStatus = $compra->status;

        if ($compra->status !== 'pending') {
            return response()->json([
                'message' => 'Solo las compras con estado pendiente pueden ser editadas.'
            ], 422);
        }

        $details = json_decode($request->details, true);
        if (!is_array($details) || empty($details)) {
            return response()->json(['message' => 'Detalles inválidos.'], 422);
        }

        if ($request->has('codigo_numero') && $request->codigo_numero === '') {
            $request->merge(['codigo_numero' => null]);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'tipodocumento_id' => 'required|exists:tipodocumento,id',
            'purchase_date' => 'required|date',
            'codigo' => 'nullable|string|max:50|unique:compras,codigo,' . $compra->id,
            //'payment_method' => 'required|in:cash,card,transfer',
            'codigo_numero' => 'nullable|integer|min:0',
            'status' => 'nullable|in:completed,pending',
        ]);

        foreach ($details as $index => $item) {
            if (
                !isset($item['product_id']) || !is_numeric($item['product_id']) ||
                !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] < 1 ||
                !isset($item['unit_cost']) || !is_numeric($item['unit_cost']) || $item['unit_cost'] < 0
            ) {
                return response()->json([
                    'message' => "Detalle inválido en la posición $index.",
                    'errors' => [
                        "details[$index]" => ['Debe tener un product_id, quantity y unit_cost válidos.']
                    ],
                ], 422);
            }
        }

        DB::transaction(function () use ($compra, $request, $details, $originalData, $originalStatus) {
            $nuevoStatus = $request->status ?? 'completed';

            $detallesAnteriores = DetalleCompra::where('purchase_id', $compra->id)->get()->keyBy('product_id');
            $inventarioAnterior = Inventory::where('reference_id', $compra->id)->get()->keyBy('product_id');
            $productos = Product::whereIn('id', collect($details)->pluck('product_id'))->get()->keyBy('id');

            $total = 0;
            $procesados = [];

            // ⬅️ REVERTIR STOCK si estaba completada y pasa a pending
            if ($originalStatus === 'completed' && $nuevoStatus === 'pending') {
                foreach ($detallesAnteriores as $detalle) {
                    $producto = $productos[$detalle->product_id] ?? Product::find($detalle->product_id);
                    if ($producto) {
                        $producto->quantity -= $detalle->quantity;
                        $producto->save();
                        $this->actualizarEstadoProducto($producto);
                    }
                }
            }

            $compra->update([
                'supplier_id' => $request->supplier_id,
                'tipodocumento_id' => $request->tipodocumento_id,
                'purchase_date' => $request->purchase_date,
                'status' => $nuevoStatus,
                ///'payment_method' => $request->payment_method,
                'codigo' => $request->codigo ?? $compra->codigo,
                'codigo_numero' => $request->codigo_numero,
            ]);

            foreach ($details as $item) {
                $productId = $item['product_id'];
                $cantidadNueva = $item['quantity'];
                $precioUnitario = $item['unit_cost'];
                $subtotal = $cantidadNueva * $precioUnitario;
                $total += $subtotal;

                $procesados[] = $productId;

                $detalleExistente = $detallesAnteriores->get($productId);
                $producto = $productos->get($productId);

                if ($detalleExistente) {
                    // Si es completed → completed, ajustar diferencia
                    if ($originalStatus === 'completed' && $nuevoStatus === 'completed') {
                        $diferencia = $cantidadNueva - $detalleExistente->quantity;
                        $producto->quantity += $diferencia;
                        $producto->save();
                        $this->actualizarEstadoProducto($producto);
                    }

                    // Si es pending → completed, sumar stock completo
                    if ($originalStatus === 'pending' && $nuevoStatus === 'completed') {
                        $producto->quantity += $cantidadNueva;
                        $producto->save();
                        $this->actualizarEstadoProducto($producto);
                    }

                    // Actualizar detalle
                    $detalleExistente->update([
                        'quantity' => $cantidadNueva,
                        'unit_cost' => $precioUnitario,
                        'subtotal' => $subtotal,
                    ]);
                } else {
                    // ➕ Nuevo producto
                    DetalleCompra::create([
                        'purchase_id' => $compra->id,
                        'product_id' => $productId,
                        'quantity' => $cantidadNueva,
                        'unit_cost' => $precioUnitario,
                        'subtotal' => $subtotal,
                    ]);

                    if ($nuevoStatus === 'completed') {
                        $producto->quantity += $cantidadNueva;
                        $producto->save();
                        $this->actualizarEstadoProducto($producto);
                    }
                }

                // Inventario
                $inventarioExistente = $inventarioAnterior->get($productId);
                $inventoryReason = $nuevoStatus === 'pending' ? 'Compra pendiente ID: ' . $compra->id : 'Compra ID: ' . $compra->id;
                if ($inventarioExistente) {
                    $inventarioExistente->update([
                        'quantity' => $cantidadNueva,
                        'reason' => $inventoryReason,
                    ]);
                } else {
                    Inventory::create([
                        'product_id' => $productId,
                        'type' => 'purchase',
                        'quantity' => $cantidadNueva,
                        'reason' => $inventoryReason,
                        'reference_id' => $compra->id,
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            // 🗑 Detalles eliminados
            $eliminados = $detallesAnteriores->keys()->diff($procesados);
            foreach ($eliminados as $pid) {
                $detalle = $detallesAnteriores[$pid];
                $producto = $productos[$pid] ?? Product::find($pid);
                $inventario = $inventarioAnterior[$pid] ?? null;

                if ($producto && $originalStatus === 'completed' && $nuevoStatus === 'completed') {
                    $producto->quantity -= $detalle->quantity;
                    $producto->save();
                    $this->actualizarEstadoProducto($producto);
                }

                $detalle->delete();
                if ($inventario) $inventario->delete();
            }

            $compra->total_cost = $total;
            $compra->save();

            $this->logCompra($compra->id, 'updated', [
                'old_data' => $originalData,
                'old_details' => $detallesAnteriores->toArray(),
            ], [
                'new_data' => $compra->getChanges(),
                'new_details' => $details,
            ]);
        });

        return response()->json(['message' => 'Compra actualizada correctamente.']);
    }

        public function destroy($id)
    {
        $compra = Compra::with('detalles.producto')->findOrFail($id);

        if ($compra->status === 'cancelled') {
            return response()->json(['message' => 'Esta compra ya fue anulada.'], 400);
        }

        DB::transaction(function () use ($compra) {
            foreach ($compra->detalles as $detalle) {
                $producto = $detalle->producto;

                // Si la compra estaba completada, se revierte el stock
                if ($compra->status === 'completed') {
                    if ($producto) {
                        if ($producto->quantity < $detalle->quantity) {
                            throw new \Exception("No se puede anular. Stock insuficiente para el producto: {$producto->name}");
                        }

                        $producto->quantity -= $detalle->quantity;
                        $producto->save();
                        $this->actualizarEstadoProducto($producto);
                    }

                    Inventory::create([
                        'product_id' => $detalle->product_id,
                        'type' => 'adjustment_purchase',
                        'quantity' => -$detalle->quantity,
                        'reason' => 'Anulación de compra ID: ' . $compra->id . ' (estado: completed)',
                        'reference_id' => $compra->id,
                        'user_id' => auth()->id(),
                    ]);
                }

                // Si solo estaba pendiente, no afecta stock pero se deja traza (opcional)
                elseif ($compra->status === 'pending') {
                    Inventory::create([
                        'product_id' => $detalle->product_id,
                        'type' => 'adjustment_purchase',
                        'quantity' => 0,
                        'reason' => 'Anulación de compra ID: ' . $compra->id . ' (estado: pending)',
                        'reference_id' => $compra->id,
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            // Cambiar estado de la compra
            $compra->update(['status' => 'cancelled']);

            // Guardar en log
            $this->logCompra($compra->id, 'cancelled', [
                'old_data' => $compra->toArray(),
                'old_details' => $compra->detalles->toArray(),
            ]);
        });

        return response()->json(['message' => 'Compra anulada correctamente']);
    }


    public function getData()
    {
        $compras = Compra::query()
            ->select([
                'compras.*',
                'suppliers.name as supplier_name',
                'tipodocumento.name as tipodocumento_name',
                'users.name as user_name',
                DB::raw('(SELECT COUNT(*) FROM compras c2 WHERE c2.id <= compras.id) as row_number'),
            ])
            ->leftJoin('suppliers', 'suppliers.id', '=', 'compras.supplier_id')
            ->leftJoin('tipodocumento', 'tipodocumento.id', '=', 'compras.tipodocumento_id')
            ->leftJoin('users', 'users.id', '=', 'compras.user_id');

        return \DataTables::of($compras)
            ->orderColumn('row_number', 'row_number $1')
            ->addColumn('row_number', fn($c) => (int) ($c->row_number ?? 0))
            ->addColumn('proveedor', fn($c) => $c->supplier_name ?? '-')
            ->addColumn('tipo_documento', fn($c) => $c->tipodocumento_name ?? '-')
            ->addColumn('codigo_numero', fn($c) => $c->codigo_numero ?? '-')
            ->addColumn('usuario', fn($c) => $c->user_name ?? '-')
            ->addColumn('fecha', fn($c) => Carbon::parse($c->purchase_date)->format('d/m/Y'))
            ->addColumn('total', fn($c) => 'S/ ' . number_format($c->total_cost, 2))
            ->addColumn('estado', function ($c) {
                return match($c->status) {
                    'completed' => '<span class="badge bg-success p-2">Completada</span>',
                    'pending' => '<span class="badge bg-warning text-dark p-2">Pendiente</span>',
                    'cancelled' => '<span class="badge bg-danger p-2">Anulada</span>',
                    default => '<span class="badge bg-secondary p-2">Desconocido</span>',
                };
            })
            ->orderColumn('proveedor', 'supplier_name $1')
            ->orderColumn('tipo_documento', 'tipodocumento_name $1')
            ->orderColumn('codigo_numero', 'compras.codigo_numero $1')
            ->orderColumn('usuario', 'user_name $1')
            ->orderColumn('fecha', 'compras.purchase_date $1')
            ->orderColumn('total', 'compras.total_cost $1')
            ->addColumn('acciones', function ($c) {
                if ($c->status === 'cancelled') return '';
                $acciones = '';

                if ($c->status === 'pending' && Auth::user()->can('administrar.compras.edit')) {
                    $acciones .= '
                    <button type="button" class="btn btn-sm btn-outline-warning btn-icon waves-effect waves-light edit-btn"
                        data-id="' . $c->id . '" title="Editar">
                        <i class="ri-edit-2-line"></i>
                    </button>';
                }

                if (Auth::user()->can('administrar.compras.delete')) {
                    $acciones .= '
                    <button type="button" class="btn btn-sm btn-outline-danger btn-icon waves-effect waves-light delete-btn"
                        data-id="' . $c->id . '" title="Eliminar">
                        <i class="ri-delete-bin-5-line"></i>
                    </button>';
                }

                $acciones .= '
                    <button type="button" class="btn btn-sm btn-outline-info btn-icon waves-effect waves-light ver-detalle-btn"
                        data-id="' . $c->id . '" title="Ver detalle">
                        <i class="ri-eye-line"></i>
                    </button>';

                return $acciones ?: '<span class="text-muted">Sin acciones</span>';
            })
            ->rawColumns(['acciones', 'estado'])
            ->make(true);
    }


    public function detalle($id)
    {
    $compra = Compra::with('detalles.producto')->findOrFail($id);

    $detalle = $compra->detalles->map(function ($item) {
        $producto = $item->producto;

        $nombreBase = $producto->name ?? 'Sin nombre';

        // Si el producto está archivado, añadimos una nota
        if ($producto && $producto->status === 'archived') {
            $nombreBase .= ' (archived)';
        }

        return [
            'product_name' => $nombreBase,
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
            'subtotal' => $item->quantity * $item->unit_cost,
        ];
    });

    return response()->json(['detalle' => $detalle]);
    }

        protected function logCompra($compraId, $accion, $datosAntes = [], $datosDespues = [])
    {
        CompraLog::create([
            'compra_id' => $compraId,
            'accion' => $accion,
            'datos_antes' => !empty($datosAntes) ? json_encode($datosAntes) : null,
            'datos_despues' => !empty($datosDespues) ? json_encode($datosDespues) : null,
            'ip' => request()->ip(),
            'user_id' => auth()->id(),
        ]);
    }


}
