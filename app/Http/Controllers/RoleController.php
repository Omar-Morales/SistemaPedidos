<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct()
    {
        $this->middleware(['auth', 'permission:administrar.roles.index'])->only(['index', 'getData']);
        $this->middleware(['auth', 'permission:administrar.roles.create'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:administrar.roles.edit'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:administrar.roles.delete'])->only('destroy');
    }

    public function index()
    {
        return view('roles.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
        'name' => 'required|string|unique:roles,name',
        'permissions' => 'array',
        'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $request->name]);
        $permissions = Permission::whereIn('id', $request->permissions ?? [])->get();
        $role->syncPermissions($permissions);

        return response()->json(['message' => 'Rol creado correctamente.']);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json($role);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $request->name]);
        $permissions = Permission::whereIn('id', $request->permissions ?? [])->get();
        $role->syncPermissions($permissions);

        return response()->json(['message' => 'Rol actualizado correctamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return response()->json(['message' => 'Rol eliminado correctamente.']);
    }

    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $roles = Role::with('permissions')
                ->select('id','name', 'created_at',
                    DB::raw('(SELECT COUNT(*) FROM roles r2 WHERE r2.id <= roles.id) as row_number'));
            return DataTables::of($roles)
                ->orderColumn('row_number', 'row_number $1')
                ->addColumn('row_number', fn($r) => (int) ($r->row_number ?? 0))
                ->addColumn('created_at', fn($c) => Carbon::parse($c->created_at)->format('d/m/Y'))
                ->addColumn('acciones', function ($r) {
                    $acciones = '';

                    if (Auth::user()->can('administrar.roles.edit')) {
                        $acciones .= '
                        <button type="button"
                            class="btn btn-outline-warning btn-sm btn-icon waves-effect waves-light edit-btn"
                            data-id="' . $r->id . '"
                            title="Editar">
                        <i class="ri-edit-2-line"></i>
                        </button>
                        ';
                    }

                    if (Auth::user()->can('administrar.roles.delete')) {
                        $acciones .= '
                        <button type="button"
                            class="btn btn-outline-danger btn-sm btn-icon waves-effect waves-light delete-btn"
                            data-id="' . $r->id . '"
                            title="Eliminar">
                        <i class="ri-delete-bin-5-line"></i>
                        </button>
                        ';
                    }

                    return $acciones ?: '<span class="text-muted">Sin acciones</span>';
                })
                ->rawColumns(['acciones'])
                ->make(true);
        }

        return response()->json(['error' => 'Acceso no permitido'], 403);
    }

    public function getPermissions()
    {
        return Permission::select('id', 'name', 'description')->get();
    }

    public function list()
    {
        return response()->json(Role::select('id', 'name')->get());
    }
}
