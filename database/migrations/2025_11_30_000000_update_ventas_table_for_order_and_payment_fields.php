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
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');
            $table->string('delivery_type', 20)->default('pickup');
        });

        if (Schema::hasColumn('ventas', 'status')) {
            DB::statement('ALTER TABLE ventas RENAME COLUMN status TO status_legacy');

            Schema::table('ventas', function (Blueprint $table) {
                $table->string('status', 20)->default('pending');
            });

            DB::table('ventas')->update([
                'status' => DB::raw("
                    CASE status_legacy
                        WHEN 'completed' THEN 'delivered'
                        WHEN 'cancelled' THEN 'cancelled'
                        WHEN 'in_progress' THEN 'in_progress'
                        ELSE 'pending'
                    END
                "),
            ]);

            Schema::table('ventas', function (Blueprint $table) {
                $table->dropColumn('status_legacy');
            });
        }

        DB::table('ventas')->whereNull('delivery_type')->update(['delivery_type' => 'pickup']);
        DB::table('ventas')
            ->where('status', 'delivered')
            ->where('amount_paid', 0)
            ->update(['amount_paid' => DB::raw('total_price')]);

        DB::table('ventas')->update([
            'payment_status' => DB::raw("
                CASE
                    WHEN amount_paid IS NULL OR amount_paid <= 0 THEN 'pending'
                    WHEN amount_paid < total_price THEN 'to_collect'
                    WHEN amount_paid > total_price THEN 'change'
                    ELSE 'paid'
                END
            "),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ventas', 'status')) {
            DB::statement('ALTER TABLE ventas RENAME COLUMN status TO status_new');

            Schema::table('ventas', function (Blueprint $table) {
                $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            });

            DB::table('ventas')->update([
                'status' => DB::raw("
                    CASE status_new
                        WHEN 'delivered' THEN 'completed'
                        WHEN 'cancelled' THEN 'cancelled'
                        ELSE 'pending'
                    END
                "),
            ]);

            Schema::table('ventas', function (Blueprint $table) {
                $table->dropColumn('status_new');
            });
        }

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'payment_status', 'amount_paid']);
        });
    }
};
