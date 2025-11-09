<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\DB;

class CompraSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // Obtener proveedores y tipos de documento
            $suppliers = Supplier::all();
            $tiposDocumento = TipoDocumento::where('type', 'compra')->get();
            $products = Product::all();

            if ($suppliers->isEmpty() || $tiposDocumento->isEmpty() || $products->isEmpty()) {
                $this->command->warn('No hay datos suficientes en suppliers, tipos_documento o products para generar compras.');
                return;
            }

            // Generar 10 compras de prueba
            for ($i = 0; $i < 10; $i++) {
                $supplier = $suppliers->random();
                $tipoDocumento = $tiposDocumento->random();

                // Crear la compra
                $compra = Compra::create([
                    'supplier_id' => $supplier->id,
                    'user_id' => 1, // Ajustar según el usuario administrador o de prueba
                    'tipodocumento_id' => $tipoDocumento->id,
                    'total_cost' => 0, // Se actualizará después de agregar detalles
                    'purchase_date' => now()->subDays(rand(1, 30)), // Fecha aleatoria en el último mes
                    'status' => 'completed',
                    'codigo' => 'TEMP',
                ]);

                $totalCost = 0;

                // Cada compra tendrá entre 1 y 5 detalles
                $detalleCount = rand(1, 15);
                for ($j = 0; $j < $detalleCount; $j++) {
                    $product = $products->random();
                    $quantity = rand(1, 10);
                    $unitCost = rand(10, 100);
                    $subtotal = $quantity * $unitCost;

                    // Crear detalle de compra
                    DetalleCompra::create([
                        'purchase_id' => $compra->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'subtotal' => $subtotal,
                    ]);

                    // Registrar en inventario
                    Inventory::create([
                        'product_id' => $product->id,
                        'reference_id' => $compra->id,
                        'type' => 'purchase',
                        'quantity' => $quantity,
                        'reason' => 'Compra ID: ' . $compra->id,
                        'user_id' => 1, // Ajustar según el usuario administrador o de prueba
                    ]);

                    // Actualizar stock del producto
                    $product->quantity += $quantity;
                    $product->save();

                    // Sumar al total de la compra
                    $totalCost += $subtotal;
                }

                // Actualizar el total de la compra
                    $codigo = 'CMP-' . str_pad($compra->id, 5, '0', STR_PAD_LEFT);
                    $compra->update(['codigo' => $codigo, 'total_cost' => $totalCost]);

            }

            $this->command->info('Se generaron compras de prueba con detalles.');
        });
    }
}



