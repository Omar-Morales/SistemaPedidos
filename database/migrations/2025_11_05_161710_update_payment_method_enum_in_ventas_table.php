<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::statement("ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_payment_method_check");
            DB::statement("ALTER TABLE ventas ALTER COLUMN payment_method TYPE TEXT");

            DB::statement("
                UPDATE ventas
                SET payment_method = CASE LOWER(TRIM(payment_method))
                    WHEN 'cash' THEN 'efectivo'
                    WHEN 'card' THEN 'trans_bbva'
                    WHEN 'transfer' THEN 'trans_bcp'
                    WHEN 'trans bcp' THEN 'trans_bcp'
                    WHEN 'trans. bcp' THEN 'trans_bcp'
                    WHEN 'trans bbva' THEN 'trans_bbva'
                    WHEN 'trans. bbva' THEN 'trans_bbva'
                    ELSE LOWER(TRIM(payment_method))
                END
            ");

            DB::statement("
                ALTER TABLE ventas
                ADD CONSTRAINT ventas_payment_method_check
                    CHECK (payment_method IS NULL OR payment_method IN ('efectivo', 'trans_bcp', 'trans_bbva', 'yape', 'plin'))
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::statement("ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_payment_method_check");

            DB::statement("
                UPDATE ventas
                SET payment_method = CASE LOWER(TRIM(payment_method))
                    WHEN 'efectivo' THEN 'cash'
                    WHEN 'trans_bbva' THEN 'card'
                    WHEN 'trans_bcp' THEN 'transfer'
                    WHEN 'yape' THEN 'cash'
                    WHEN 'plin' THEN 'cash'
                    ELSE payment_method
                END
            ");

            DB::statement("
                ALTER TABLE ventas
                ADD CONSTRAINT ventas_payment_method_check
                    CHECK (payment_method IS NULL OR payment_method IN ('cash', 'card', 'transfer'))
            ");
        });
    }
};
