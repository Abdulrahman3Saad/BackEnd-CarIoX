public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        if (!Schema::hasColumn('orders', 'return_date')) {
            $table->date('return_date')->nullable()->after('expected_delivery_date');
        }
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        if (Schema::hasColumn('orders', 'return_date')) {
            $table->dropColumn('return_date');
        }
    });
}