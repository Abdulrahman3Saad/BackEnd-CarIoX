<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // حذف عمود service_fees من الـ orders — رسوم الخدمة بقت على صاحب الإعلان فقط
            // وبتتسجل تلقائياً في جدول payments لما الأدمن يوافق على الإعلان
            $table->dropColumn('service_fees');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // rollback لو احتجنا نرجع
            $table->decimal('service_fees', 10, 2)->default(0)->after('car_price');
        });
    }
};