<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoExpireOrders extends Command
{
    protected $signature   = 'orders:auto-expire';
    protected $description = 'إلغاء الطلبات المنتهية المدة تلقائياً';

    public function handle(): void
    {
        // ① إلغاء confirmed orders المنتهية (غير تأجير)
        $expired = Order::where('order_status', 'confirmed')
            ->whereDate('expected_delivery_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($expired as $order) {
            DB::transaction(function () use ($order, &$count) {
                $car = Car::lockForUpdate()->find($order->car_id);
                if ($car) {
                    $car->update(['is_available' => true]);
                }
                $order->update(['order_status' => 'canceled']);
                Payment::where('order_id', $order->id)
                    ->where('status', 'completed')
                    ->update(['status' => 'refunded']);
                $count++;
            });
        }

        // ② تسجيل تحذير للتأجيرات المتأخرة
        $overdueRentals = Order::where('order_status', 'in_progress')
            ->whereNotNull('return_date')
            ->whereDate('return_date', '<', now()->toDateString())
            ->get();

        $this->info("✅ تم إلغاء {$count} طلب منتهي المدة.");
        $this->info("⚠️ يوجد {$overdueRentals->count()} تأجير متأخر.");
    }
}
