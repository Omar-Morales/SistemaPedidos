<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['warehouse', 'sale_date'], 'ventas_warehouse_sale_date_index');
            $table->index('sale_date', 'ventas_sale_date_index');
            $table->index('payment_status', 'ventas_payment_status_index');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->index('sale_id', 'detalle_ventas_sale_id_index');
            $table->index('warehouse', 'detalle_ventas_warehouse_index');
            $table->index('status', 'detalle_ventas_status_index');
            $table->index('payment_status', 'detalle_ventas_payment_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_warehouse_sale_date_index');
            $table->dropIndex('ventas_sale_date_index');
            $table->dropIndex('ventas_payment_status_index');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropIndex('detalle_ventas_sale_id_index');
            $table->dropIndex('detalle_ventas_warehouse_index');
            $table->dropIndex('detalle_ventas_status_index');
            $table->dropIndex('detalle_ventas_payment_status_index');
        });
    }
};
