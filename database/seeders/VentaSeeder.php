<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DetalleVenta;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\TipoDocumento;
use App\Models\Transaction;
use App\Models\Venta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VentaSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $customers = Customer::all();
            $tiposDocumento = TipoDocumento::where('type', 'venta')->get();
            $products = Product::where('quantity', '>', 0)->get();

            if ($customers->isEmpty() || $tiposDocumento->isEmpty() || $products->isEmpty()) {
                $this->command->warn('No hay datos suficientes en customers, tipos_documento o products con stock para generar ventas.');
                return;
            }

            for ($i = 0; $i < 10; $i++) {
                $customer = $customers->random();
                $tipoDocumento = $tiposDocumento->random();

                $venta = Venta::create([
                    'customer_id' => $customer->id,
                    'user_id' => 1, // Ajustar segn el usuario administrador o de prueba
                    'tipodocumento_id' => $tipoDocumento->id,
                    'total_price' => 0,
                    'amount_paid' => 0,
                    'sale_date' => now()->subDays(rand(1, 30)),
                    'status' => 'delivered',
                    'payment_status' => 'pending',
                    'difference' => 0,
                    'payment_method' => 'cash',
                    'delivery_type' => rand(0, 1) ? 'pickup' : 'delivery',
                    'warehouse' => collect(['curva','milla','santa_carolina'])->random(),
                    'codigo' => 'TEMP',
                ]);

                $totalPrice = 0;
                $detalleCount = rand(1, 5);

                for ($j = 0; $j < $detalleCount; $j++) {
                    $product = $products->random();
                    if ($product->quantity < 1) {
                        continue;
                    }

                    $quantity = rand(1, min(5, $product->quantity));
                    $unitPrice = rand(10, 100);
                    $subtotal = $quantity * $unitPrice;

                DetalleVenta::create([
                    'sale_id' => $venta->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit' => '1',
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'status' => 'delivered',
                    'payment_status' => 'paid',
                    'amount_paid' => $subtotal,
                    'difference' => 0,
                    'warehouse' => $venta->warehouse,
                    'delivery_type' => $venta->delivery_type,
                    'payment_method' => $venta->payment_method,
                ]);

                    Inventory::create([
                        'product_id' => $product->id,
                        'reference_id' => $venta->id,
                        'type' => 'sale',
                        'quantity' => -$quantity,
                        'reason' => 'Venta ID: ' . $venta->id,
                        'user_id' => 1, // Ajustar segn el usuario administrador o de prueba
                    ]);

                    $product->decrement('quantity', $quantity);
                    $totalPrice += $subtotal;
                }

                $codigo = 'VTA-' . str_pad((string) $venta->id, 5, '0', STR_PAD_LEFT);
                $venta->update([
                    'codigo' => $codigo,
                    'total_price' => $totalPrice,
                    'amount_paid' => $totalPrice,
                    'payment_status' => 'paid',
                    'difference' => 0,
                ]);

                Transaction::create([
                    'type' => 'sale',
                    'amount' => $totalPrice,
                    'reference_id' => $venta->id,
                    'description' => 'Venta entregada ID: ' . $venta->id,
                    'user_id' => 1, // Ajustar segn el usuario administrador o de prueba
                ]);
            }

            $this->command->info('Se generaron ventas de prueba con detalles.');
        });
    }
}


