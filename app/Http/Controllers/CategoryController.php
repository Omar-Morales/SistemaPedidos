<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct()
    {
        // 🔹 Solo el ADMINISTRADOR y MANTENEDOR pueden acceder a este controlador
        $this->middleware(['auth', 'permission:administrar.categorias.index'])->only('index', 'getData');
        $this->middleware(['auth', 'permission:administrar.categorias.create'])->only('create', 'store');
        $this->middleware(['auth', 'permission:administrar.categorias.edit'])->only('edit', 'update');
        $this->middleware(['auth', 'permission:administrar.categorias.delete'])->only('destroy');

    }

    public function index()
    {
        return view('categoria.index');
    }

    public function create()
    {
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'max:255',
                Rule::unique('categories')->where(fn($query) => $query->where('status', 'active')),
            ],
            'description' => 'nullable|string',
        ]);

        $description = $request->filled('description')
            ? $request->description
            : 'CALIDAD 100% GARANTIZADA';

        $existing = Category::where('name', $request->name)->first();

        if ($existing && $existing->status === 'inactive') {
            $existing->description = $description;
            $existing->status = 'active';
            $existing->save();

            return response()->json(['message' => 'Categoría reactivada correctamente.']);
        }

        Category::create([
            'name' => $request->name,
            'description' => $description,
        ]);

        return response()->json(['message' => 'Categoría creada correctamente.']);
    }

    public function show($id)
    {
        $categoria = Category::findOrFail($id);
        return response()->json($categoria);
    }

    public function edit(string $id)
    {
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => [
                'required',
                'max:255',
                Rule::unique('categories')->ignore($id)->where(fn($query) => $query->where('status', 'active')),
            ],
            'description' => 'nullable|string',
        ]);

        $categoria = Category::findOrFail($id);
        $description = $request->filled('description')
            ? $request->description
            : 'CALIDAD 100% GARANTIZADA';

        $categoria->update([
            'name' => $request->name,
            'description' => $description,
        ]);

        return response()->json(['message' => 'Categoría actualizada correctamente.']);
    }

    public function destroy(string $id)
    {
        $categoria = Category::findOrFail($id);

        $tieneProductosActivos = $categoria->products()
            ->whereIn('status', ['available', 'sold'])
            ->exists();

        if ($tieneProductosActivos) {
            return response()->json([
                'message' => 'No puedes eliminar esta categoría porque tiene productos asociados activos.'
            ], 422);
        }

        $categoria->status = 'inactive';
        $categoria->save();
        //$categoria->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }

    public function getData(Request $request)
    {
    $categorias = Category::select([
            'id',
            'name',
            'description',
            DB::raw("(SELECT COUNT(*) FROM categories c2 WHERE c2.status = 'active' AND c2.id <= categories.id) as row_number"),
        ])->where('status', 'active');

     if ($request->ajax()) {
    return DataTables::of($categorias)
        ->orderColumn('row_number', 'row_number $1')
        ->addColumn('row_number', fn($categoria) => (int) ($categoria->row_number ?? 0))
        ->addColumn('acciones', function ($categoria) {
        $acciones = '';

        if (Auth::user()->can('administrar.categorias.edit')) {
            $acciones .= '
            <button type="button"
                    class="btn btn-outline-warning btn-sm btn-icon waves-effect waves-light edit-btn"
                    data-id="' . $categoria->id . '"
                    title="Editar">
                <i class="ri-edit-2-line"></i>
            </button>';
        }

        if (Auth::user()->can('administrar.categorias.delete')) {
            $acciones .= '
            <button type="button"
                    class="btn btn-outline-danger btn-sm btn-icon waves-effect waves-light delete-btn"
                    data-id="' . $categoria->id . '"
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
    $query = Category::query();

    // Incluir categoría inactiva si viene desde edición
    if ($request->has('include_id')) {
        $query->where(function ($q) use ($request) {
            $q->where('status', 'active')
              ->orWhere('id', $request->include_id);
        });
    } else {
        $query->where('status', 'active');
    }

    // Formatea el resultado para Select2
    $categorias = $query->orderBy('id')->get()->map(function ($categoria) {
        return [
            'id' => $categoria->id,
            'text' => $categoria->status === 'active'
                      ? $categoria->name
                      : $categoria->name . ' (inactiva)', // para distinguir
        ];
    });

    return response()->json($categorias);
    }
}
