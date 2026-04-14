<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // العميل هو اللي بيختار: عايز السيارة بسائق ولا بدون
            // بيتحفظ في الـ order مش في الـ car
            $table->boolean('with_driver')->default(false)->after('return_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('with_driver');
        });
    }
};