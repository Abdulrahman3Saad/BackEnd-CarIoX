<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * إضافة عمود type (enum) لجدول services
 *
 * بدل الـ string matching الهش اللي كان بيتعمل في الكود
 * (str_contains($name, 'rent') || str_contains($name, 'تأجير') ...)
 * دلوقتي بقينا نقارن services.type = 'rent' مباشرة
 *
 * القيم المسموح بيها: sale | rent | swap
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->enum('type', ['sale', 'rent', 'swap'])
                ->default('sale')
                ->after('name');
        });

        // ✅ Migration تلقائي للبيانات الموجودة بناءً على اسم الخدمة
        DB::statement("
            UPDATE services SET type = CASE
                WHEN LOWER(name) LIKE '%rent%'     THEN 'rent'
                WHEN LOWER(name) LIKE '%تأجير%'    THEN 'rent'
                WHEN LOWER(name) LIKE '%إيجار%'    THEN 'rent'
                WHEN LOWER(name) LIKE '%swap%'     THEN 'swap'
                WHEN LOWER(name) LIKE '%exchange%' THEN 'swap'
                WHEN LOWER(name) LIKE '%تبديل%'   THEN 'swap'
                WHEN LOWER(name) LIKE '%استبدال%' THEN 'swap'
                ELSE 'sale'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
