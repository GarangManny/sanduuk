<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('chama_id')->nullable()->constrained()->nullOnDelete();
    $table->string('type'); // contribution, withdraw_request, transfer
    $table->foreignId('recorded_by')->constrained('users'); // admin
    $table->string('from_account'); // personal, chama
    $table->string('to_account'); // personal, chama
    $table->decimal('amount', 15, 2);
    $table->string('status')->default('completed'); // pending, approved
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
