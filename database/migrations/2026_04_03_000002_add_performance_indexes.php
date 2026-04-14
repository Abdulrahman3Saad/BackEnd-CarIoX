<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ بنتحقق من وجود الـ index قبل ما نضيفه — لو موجود نتخطاه
        $this->addIndexIfNotExists('cars',      'idx_cars_status_available',  ['status', 'is_available']);
        $this->addIndexIfNotExists('cars',      'idx_cars_user_id',           ['user_id']);
        $this->addIndexIfNotExists('cars',      'idx_cars_service_id',        ['service_id']);

        $this->addIndexIfNotExists('orders',    'idx_orders_user_status',     ['user_id', 'order_status']);
        $this->addIndexIfNotExists('orders',    'idx_orders_car_status',      ['car_id', 'order_status']);

        $this->addIndexIfNotExists('payments',  'idx_payments_user_status',   ['user_id', 'status']);
        $this->addIndexIfNotExists('payments',  'idx_payments_order_type',    ['order_id', 'payment_type']);
        $this->addIndexIfNotExists('payments',  'idx_payments_swap_id',       ['swap_id']);

        $this->addIndexIfNotExists('car_swaps', 'idx_swaps_requester_status', ['requester_id', 'status']);
        $this->addIndexIfNotExists('car_swaps', 'idx_swaps_receiver_status',  ['receiver_id', 'status']);
    }

    public function down(): void
    {
        $drops = [
            'cars'      => ['idx_cars_status_available', 'idx_cars_user_id', 'idx_cars_service_id'],
            'orders'    => ['idx_orders_user_status', 'idx_orders_car_status'],
            'payments'  => ['idx_payments_user_status', 'idx_payments_order_type', 'idx_payments_swap_id'],
            'car_swaps' => ['idx_swaps_requester_status', 'idx_swaps_receiver_status'],
        ];

        foreach ($drops as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->dropIndexIfExists($table, $index);
            }
        }
    }

    // ===================================================
    // Helpers
    // ===================================================

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.STATISTICS
            WHERE table_schema = DATABASE()
              AND table_name   = ?
              AND index_name   = ?
        ", [$table, $indexName]);

        return $result[0]->cnt > 0;
    }

    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return; // موجود بالفعل — تخطى
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
            $t->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($indexName) {
            $t->dropIndex($indexName);
        });
    }
};
