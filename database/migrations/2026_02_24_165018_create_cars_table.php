<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->string('car_number')->unique();
            $table->string('brand');
            $table->string('model');
            $table->integer('manufacture_year');
            $table->enum('car_condition', ['new', 'used', 'old']);
            $table->enum('fuel_type', ['petrol', 'electric', 'diesel', 'hybrid']);
            $table->enum('transmission', ['automatic', 'manual']);
            $table->integer('mileage');
            $table->integer('seats');
            $table->string('color');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');
            $table->boolean('is_available')->default(false); // ✅ false لحد ما الأدمن يوافق
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
