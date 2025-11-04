<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('subtotal');
            $table->string('payment_status', 20)->default('pending')->after('status');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_status');
            $table->decimal('difference', 10, 2)->default(0)->after('amount_paid');
        });

        $detalles = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'dv.sale_id', '=', 'v.id')
            ->select(
                'dv.id',
                'dv.sale_id',
                'dv.subtotal',
                'v.total_price',
                'v.amount_paid',
                'v.payment_status',
                'v.status'
            )
            ->get();

        $ventaAcumulado = [];

        foreach ($detalles as $detalle) {
            $ventaTotal = (float) $detalle->total_price;
            $ventaPagado = (float) $detalle->amount_paid;

            if (!isset($ventaAcumulado[$detalle->sale_id])) {
                $ventaAcumulado[$detalle->sale_id] = [
                    'asignado' => 0,
                    'detalles' => [],
                ];
            }

            $proporcion = $ventaTotal > 0 ? ($detalle->subtotal / $ventaTotal) : 0;
            $montoDetalle = $ventaPagado > 0 ? round($ventaPagado * $proporcion, 2) : 0;

            $ventaAcumulado[$detalle->sale_id]['detalles'][] = [
                'id' => $detalle->id,
                'subtotal' => (float) $detalle->subtotal,
                'monto' => $montoDetalle,
            ];

            $ventaAcumulado[$detalle->sale_id]['asignado'] += $montoDetalle;

            DB::table('detalle_ventas')
                ->where('id', $detalle->id)
                ->update([
                    'status' => $detalle->status ?? 'pending',
                    'payment_status' => $detalle->payment_status ?? 'pending',
                    'amount_paid' => $montoDetalle,
                    'difference' => round((float) $detalle->subtotal - $montoDetalle, 2),
                ]);
        }

        foreach ($ventaAcumulado as $ventaId => $datosVenta) {
            $venta = DB::table('ventas')->find($ventaId);
            if (!$venta) {
                continue;
            }

            $totalDetalle = array_sum(array_column($datosVenta['detalles'], 'subtotal'));
            $montoAsignado = $datosVenta['asignado'];
            $ajuste = round((float) $venta->amount_paid - $montoAsignado, 2);

            if (abs($ajuste) > 0 && $datosVenta['detalles']) {
                // Ajustar el ultimo detalle para que la suma coincida con el monto pagado de la venta.
                $ultimoDetalle = array_pop($datosVenta['detalles']);
                $nuevoMonto = round($ultimoDetalle['monto'] + $ajuste, 2);
                $nuevoMonto = max(0, $nuevoMonto);

                DB::table('detalle_ventas')
                    ->where('id', $ultimoDetalle['id'])
                    ->update([
                        'amount_paid' => $nuevoMonto,
                        'difference' => round($ultimoDetalle['subtotal'] - $nuevoMonto, 2),
                    ]);
            }

            DB::table('ventas')
                ->where('id', $ventaId)
                ->update([
                    'difference' => round((float) $venta->total_price - (float) $venta->amount_paid, 2),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn(['status', 'payment_status', 'amount_paid', 'difference']);
        });
    }
};
