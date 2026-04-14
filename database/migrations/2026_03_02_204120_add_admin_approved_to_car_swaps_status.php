<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE car_swaps MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'admin_approved') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE car_swaps MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};