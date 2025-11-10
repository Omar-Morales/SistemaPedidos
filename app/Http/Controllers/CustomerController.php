<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    protected const LOCATIONS = [
        'Cdra 7',
        'Cdra 8',
        'Cdra 9',
        'Cdra 10',
        'Cdra 11',
        'Cdra 12',
        'Cdra 13',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'permission:administrar.clientes.index'])->only('index', 'getData', 'show');
        $this->middleware(['auth', 'permission:administrar.clientes.create'])->only('create', 'store');
        $this->middleware(['auth', 'permission:administrar.clientes.edit'])->only('edit', 'update');
        $this->middleware(['auth', 'permission:administrar.clientes.delete'])->only('destroy');
    }

    public function index()
    {
        return view('customer.index');
    }

    public function create()
    {
    }

    public function store(Request $request)
    {
        $request->merge(['name' => trim($request->name)]);

        $request->validate([
            'ruc' => 'required|string|max:11',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers')->where(fn($query) => $query->where('status', 'active')),
            ],
            'phone' => 'nullable|string|max:15',
            'address' => [
                'required',
                'string',
                Rule::in(self::LOCATIONS),
            ],
        ]);

        $normalizedName = mb_strtolower($request->name);
        $existingByName = Customer::whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])->first();

        if ($existingByName && $existingByName->status === 'inactive') {
            $existingByName->update([
                'ruc' => $request->ruc,
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address,
                'status' => 'active',
            ]);

            return response()->json(['message' => 'Tienda reactivada correctamente.']);
        }

        Customer::create([
            'ruc' => $request->ruc,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => 'active',
        ]);

        return response()->json(['message' => 'Tienda creada correctamente.']);
    }

    public function show($id)
    {
        return response()->json(Customer::findOrFail($id));
    }

    public function edit(string $id)
    {
    }

    public function update(Request $request, $id)
    {
        $request->merge(['name' => trim($request->name)]);

        $request->validate([
            'ruc' => 'required|string|max:11',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers')->ignore($id)->where(fn($query) => $query->where('status', 'active')),
            ],
            'phone' => 'nullable|string|max:15',
            'address' => [
                'required',
                'string',
                Rule::in(self::LOCATIONS),
            ],
        ]);

        $customer = Customer::findOrFail($id);
        $customer->update([
            'ruc' => $request->ruc,
            'name' => trim($request->name),
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return response()->json(['message' => 'Tienda actualizada correctamente.']);
    }

    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->status = 'inactive';
        $customer->save();

        return response()->json(['message' => 'Tienda eliminada correctamente.']);
    }

    public function getData(Request $request)
    {
        $customers = Customer::select([
                'id',
                'ruc',
                'name',
                'address',
                'phone',
                'status',
                DB::raw("(SELECT COUNT(*) FROM customers c2 WHERE c2.status = 'active' AND c2.id <= customers.id) as row_number"),
            ])
            ->where('status', 'active');

        if ($request->ajax()) {
            return DataTables::of($customers)
                ->orderColumn('row_number', 'row_number $1')
                ->addColumn('row_number', fn($customer) => (int) ($customer->row_number ?? 0))
                ->addColumn('location', fn($customer) => $customer->address ?? '-')
                ->addColumn('acciones', function ($customer) {
                    $acciones = '';

                    if (Auth::user()->can('administrar.clientes.edit')) {
                        $acciones .= '
                            <button type="button"
                                class="btn btn-outline-warning btn-sm btn-icon waves-effect waves-light edit-btn"
                                data-id="' . $customer->id . '"
                                title="Editar">
                                <i class="ri-edit-2-line"></i>
                            </button>';
                    }
                    if (Auth::user()->can('administrar.clientes.delete')) {
                        $acciones .= '
                            <button type="button"
                                class="btn btn-outline-danger btn-sm btn-icon waves-effect waves-light delete-btn"
                                data-id="' . $customer->id . '"
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

    public function select(Request $request)
    {
        $query = Customer::query();

        if ($request->has('include_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('status', 'active')
                    ->orWhere('id', $request->include_id);
            });
        } else {
            $query->where('status', 'active');
        }

        $clientes = $query->orderBy('id')->get()->map(function ($cliente) {
            return [
                'id' => $cliente->id,
                'text' => $cliente->status === 'active'
                    ? $cliente->name
                    : $cliente->name . ' (inactivo)',
            ];
        });

        return response()->json($clientes);
    }
}
