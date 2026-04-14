<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cars MODIFY COLUMN status ENUM('pending','active','rejected','completed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE cars SET status = 'active' WHERE status = 'completed'");
        DB::statement("ALTER TABLE cars MODIFY COLUMN status ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending'");
    }
};