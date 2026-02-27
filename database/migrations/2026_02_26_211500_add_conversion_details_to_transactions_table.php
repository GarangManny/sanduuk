<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('exchange_rate', 15, 6)->nullable()->after('amount');
            $table->decimal('converted_amount', 15, 2)->nullable()->after('exchange_rate');
            $table->string('currency', 10)->nullable()->after('converted_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'converted_amount', 'currency']);
        });
    }
};
