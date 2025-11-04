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
            $table->enum('warehouse', ['curva', 'milla', 'santa_carolina'])->default('curva')->after('difference');
            $table->enum('delivery_type', ['pickup', 'delivery'])->default('pickup')->after('warehouse');
            $table->string('payment_method', 20)->nullable()->after('delivery_type');
        });

        DB::statement(<<<'SQL'
            UPDATE detalle_ventas dv
            SET
                warehouse = v.warehouse,
                delivery_type = v.delivery_type,
                payment_method = v.payment_method
            FROM ventas v
            WHERE dv.sale_id = v.id
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn(['warehouse', 'delivery_type', 'payment_method']);
        });
    }
};
