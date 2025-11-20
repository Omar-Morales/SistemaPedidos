<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE detalle_ventas DROP CONSTRAINT IF EXISTS detalle_ventas_warehouse_check;');
        DB::statement("ALTER TABLE detalle_ventas ADD CONSTRAINT detalle_ventas_warehouse_check CHECK (warehouse::text = ANY (ARRAY['curva','milla','santa_carolina','tienda']::text[]));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE detalle_ventas DROP CONSTRAINT IF EXISTS detalle_ventas_warehouse_check;');
        DB::statement("ALTER TABLE detalle_ventas ADD CONSTRAINT detalle_ventas_warehouse_check CHECK (warehouse::text = ANY (ARRAY['curva','milla','santa_carolina']::text[]));");
    }
};
