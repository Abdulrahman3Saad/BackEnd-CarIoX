<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->string('image_path');
            $table->string('image_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_images');
    }
};