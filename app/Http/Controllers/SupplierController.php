<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Supplier;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function __construct()
    {
        // 🔹 Solo el ADMINISTRADOR y MANTENEDOR pueden acceder a este controlador
        $this->middleware(['auth', 'permission:administrar.proveedores.index'])->only('index', 'getData', 'show');
        $this->middleware(['auth', 'permission:administrar.proveedores.create'])->only('create', 'store');
        $this->middleware(['auth', 'permission:administrar.proveedores.edit'])->only('edit', 'update');
        $this->middleware(['auth', 'permission:administrar.proveedores.delete'])->only('destroy');
    }

    public function index()
    {
        return view('supplier.index');
    }

    public function create()
    {
    }

    public function store(Request $request)
    {
        $request->validate([
            'ruc' => 'required|unique:suppliers|max:11',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:suppliers,email',
            'phone' => 'required|string|max:15',
            //'status' => 'required|in:active,inactive',
        ]);

        $data = $request->only(['ruc', 'name', 'email', 'phone']);

        Supplier::create($data);

        return response()->json(['message' => 'Proveedor creado correctamente.']);
    }

    public function show(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        return response()->json($supplier);
    }

    public function edit(string $id)
    {
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'ruc' => 'required|max:11|unique:suppliers,ruc,' . $id,
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:suppliers,email,' . $id,
            'phone' => 'required|string|max:15',
            //'status' => 'required|in:active,inactive',
        ]);

        $supplier = Supplier::findOrFail($id);
        $data = $request->only(['ruc', 'name', 'email', 'phone']);

        $supplier->update($data);

        return response()->json(['message' => 'Proveedor actualizado correctamente.']);
    }

    public function destroy(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->status = 'inactive';
        $supplier->save();
        //$supplier->delete();

        return response()->json(['message' => 'Proveedor eliminado correctamente.']);
    }

            public function getData(Request $request)
    {
        $suppliers = Supplier::select([
                'id',
                'ruc',
                'name',
                'email',
                'phone',
                'status',
                DB::raw("(SELECT COUNT(*) FROM suppliers s2 WHERE s2.status = 'active' AND s2.id <= suppliers.id) as row_number"),
            ])
            ->where('status', 'active');


        if ($request->ajax()) {
        return DataTables::of($suppliers)
            ->orderColumn('row_number', 'row_number $1')
            ->addColumn('row_number', fn($supplier) => (int) ($supplier->row_number ?? 0))
            ->addColumn('acciones', function ($supplier) {
            $acciones = '';

            if (Auth::user()->can('administrar.proveedores.edit')) {
            $acciones .= '
                    <button type="button"
                            class="btn btn-outline-warning btn-sm btn-icon waves-effect waves-light edit-btn"
                            data-id="' . $supplier->id . '"
                            title="Editar">
                        <i class="ri-edit-2-line"></i>
                    </button>';
            }
            if (Auth::user()->can('administrar.proveedores.delete')) {
            $acciones .= '
                    <button type="button"
                            class="btn btn-outline-danger btn-sm btn-icon waves-effect waves-light delete-btn"
                            data-id="' . $supplier->id . '"
                            title="Eliminar">
                        <i class="ri-delete-bin-5-line"></i>
                    </button>';
            }

            return $acciones ?: '<span class="text-muted">Sin acciones</span>';
            })
            ->rawColumns(['acciones'])
            ->make(true);
            }

        return response()->json(['error' => 'Acceso no permitido'], 403);
    }

    // SupplierController.php
    public function select(Request $request)
    {
        $query = Supplier::query();

        // Incluir proveedor inactivo si es edición
        if ($request->has('include_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('status', 'active')
                ->orWhere('id', $request->include_id);
            });
        } else {
            $query->where('status', 'active');
        }

        $proveedores = $query->orderBy('id')->get()->map(function ($proveedor) {
            return [
                'id' => $proveedor->id,
                'text' => $proveedor->status === 'active'
                        ? $proveedor->name
                        : $proveedor->name . ' (inactivo)',
            ];
        });

        return response()->json($proveedores);
    }


}

