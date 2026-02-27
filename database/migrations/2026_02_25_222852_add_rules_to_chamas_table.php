<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('chamas', function (Blueprint $table) {
        $table->decimal('contribution_amount', 12, 2)->nullable();
        $table->string('currency')->default('KES');
        $table->string('contribution_period')->default('monthly'); 
        // could be weekly, monthly, custom
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chamas', function (Blueprint $table) {
            //
        });
    }
};
