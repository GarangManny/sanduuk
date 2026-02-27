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
        // Enforce that personal balances never go negative
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE users ADD CONSTRAINT check_user_balance_non_negative CHECK (balance >= 0)');

        // Enforce that chama balances never go negative
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE chamas ADD CONSTRAINT check_chama_balance_non_negative CHECK (total_balance >= 0)');
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE users DROP CONSTRAINT check_user_balance_non_negative');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE chamas DROP CONSTRAINT check_chama_balance_non_negative');
    }
};
