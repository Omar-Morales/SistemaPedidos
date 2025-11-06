<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Permisos disponibles
        $permissions = Permission::pluck('name')->toArray();

        // Asignar TODOS los permisos al rol Administrador
        $admin = Role::where('name', 'Administrador')->first();
        $admin?->syncPermissions($permissions);

        // Vendedor solo puede ver ventas
        $vendedor = Role::where('name', 'Vendedor')->first();
        $vendedor?->syncPermissions([
            'administrar.ventas.index',
            'administrar.ventas.create',
            'administrar.ventas.edit',
            'administrar.ventas.delete',
        ]);

        // Comprador solo puede ver compras
        $comprador = Role::where('name', 'Comprador')->first();
        $comprador?->syncPermissions([
            'administrar.compras.index',
            'administrar.compras.create',
            'administrar.compras.edit',
            'administrar.compras.delete',
        ]);

        // Mantenedor puede administrar productos, categorías, etc.
        $mantenedor = Role::where('name', 'Mantenedor')->first();
        $mantenedor?->syncPermissions([
            'administrar.categorias.index',
            'administrar.categorias.create',
            'administrar.categorias.edit',
            'administrar.categorias.delete',
            'administrar.productos.index',
            'administrar.productos.create',
            'administrar.productos.edit',
            'administrar.productos.delete',
            // Agrega más según necesites
        ]);

        // Analista ve solo reportes
        $analista = Role::where('name', 'Analista')->first();
        $analista?->syncPermissions([
            'administrar.inventarios.index',
            'administrar.inventarios.export',
        ]);

        // Almacén puede ver inventario
        $almacen = Role::where('name', 'Almacen')->first();
        $almacen?->syncPermissions([
            'administrar.inventarios.index',
        ]);

        $warehouseRoles = [
            'Curva',
            'Milla',
            'Santa Carolina',
        ];

        foreach ($warehouseRoles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->syncPermissions([
                'administrar.ventas.index',
                'administrar.ventas.edit',
            ]);
        }
    }
}
