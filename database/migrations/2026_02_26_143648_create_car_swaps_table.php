<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_swaps', function (Blueprint $table) {
            $table->id();

            // صاحب الطلب (اللي عايز يبدل)
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');

            // سيارة صاحب الطلب
            $table->foreignId('requester_car_id')->constrained('cars')->onDelete('cascade');

            // صاحب السيارة التانية (اللي هيستقبل الطلب)
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');

            // السيارة التانية اللي بيبدل بيها
            $table->foreignId('receiver_car_id')->constrained('cars')->onDelete('cascade');

            // بيانات التواصل
            $table->string('requester_phone');
            $table->string('requester_governorate');
            $table->string('requester_address');

            // ملاحظات الطلب
            $table->text('notes')->nullable();

            // فرق السعر (لو في فرق بين السيارتين)
            $table->decimal('price_difference', 10, 2)->default(0);

            // مين بيدفع الفرق (requester = صاحب الطلب / receiver = المستقبل / none = مفيش فرق)
            $table->enum('who_pays_difference', ['requester', 'receiver', 'none'])->default('none');

            // حالة الطلب
            $table->enum('status', [
                'pending',    // في انتظار موافقة صاحب السيارة
                'accepted',   // وافق
                'rejected',   // رفض
                'completed',  // اتم التبديل
                'canceled'    // اتلغى
            ])->default('pending');

            // تاريخ التبديل المتوقع
            $table->date('swap_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_swaps');
    }
};