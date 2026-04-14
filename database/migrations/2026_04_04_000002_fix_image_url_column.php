<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تغيير image_url من VARCHAR(255) لـ TEXT
        Schema::table('car_images', function (Blueprint $table) {
            $table->text('image_url')->change();
        });
    }

    public function down(): void
    {
        Schema::table('car_images', function (Blueprint $table) {
            $table->string('image_url')->change();
        });
    }
};
