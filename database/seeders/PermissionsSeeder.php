<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
$permissions = [
            // Dashboard
            ['name' => 'administrar.dashboard.index', 'description' => 'Ver el Dashboard'],

            // Mantenimiento
            ['name' => 'administrar.categorias.index', 'description' => 'Ver listado de Categorías'],
            ['name' => 'administrar.categorias.create', 'description' => 'Crear Categorías'],
            ['name' => 'administrar.categorias.edit', 'description' => 'Editar Categorías'],
            ['name' => 'administrar.categorias.delete', 'description' => 'Eliminar Categorías'],

            ['name' => 'administrar.productos.index', 'description' => 'Ver listado de Productos'],
            ['name' => 'administrar.productos.create', 'description' => 'Crear Productos'],
            ['name' => 'administrar.productos.edit', 'description' => 'Editar Productos'],
            ['name' => 'administrar.productos.delete', 'description' => 'Eliminar Productos'],

            ['name' => 'administrar.clientes.index', 'description' => 'Ver listado de Clientes'],
            ['name' => 'administrar.clientes.create', 'description' => 'Crear Clientes'],
            ['name' => 'administrar.clientes.edit', 'description' => 'Editar Clientes'],
            ['name' => 'administrar.clientes.delete', 'description' => 'Eliminar Clientes'],

            ['name' => 'administrar.proveedores.index', 'description' => 'Ver listado de Proveedores'],
            ['name' => 'administrar.proveedores.create', 'description' => 'Crear Proveedores'],
            ['name' => 'administrar.proveedores.edit', 'description' => 'Editar Proveedores'],
            ['name' => 'administrar.proveedores.delete', 'description' => 'Eliminar Proveedores'],

            ['name' => 'administrar.usuarios.index', 'description' => 'Ver listado de Usuarios'],
            ['name' => 'administrar.usuarios.create', 'description' => 'Crear Usuarios'],
            ['name' => 'administrar.usuarios.edit', 'description' => 'Editar Usuarios'],
            ['name' => 'administrar.usuarios.delete', 'description' => 'Eliminar Usuarios'],

            ['name' => 'administrar.roles.index', 'description' => 'Ver listado de Roles'],
            ['name' => 'administrar.roles.create', 'description' => 'Crear Roles'],
            ['name' => 'administrar.roles.edit', 'description' => 'Editar Roles'],
            ['name' => 'administrar.roles.delete', 'description' => 'Eliminar Roles'],

            // Compras
            ['name' => 'administrar.compras.index', 'description' => 'Ver listado de Compras'],
            ['name' => 'administrar.compras.create', 'description' => 'Crear Compras'],
            ['name' => 'administrar.compras.edit', 'description' => 'Editar Compras'],
            ['name' => 'administrar.compras.delete', 'description' => 'Eliminar Compras'],

            // Ventas
            ['name' => 'administrar.ventas.index', 'description' => 'Ver listado de Ventas'],
            ['name' => 'administrar.ventas.create', 'description' => 'Crear Ventas'],
            ['name' => 'administrar.ventas.edit', 'description' => 'Editar Ventas'],
            ['name' => 'administrar.ventas.delete', 'description' => 'Eliminar Ventas'],

            // Inventario
            ['name' => 'administrar.inventarios.index', 'description' => 'Ver Cierre Diario'],
            ['name' => 'administrar.inventarios.export', 'description' => 'Exportar Cierre Diario'],

        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description'], 'guard_name' => 'web']
            );
        }
    }
}
