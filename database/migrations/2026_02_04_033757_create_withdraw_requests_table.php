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
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('wallet_id');
            $table->decimal('withdraw_amount',12,2);
            $table->string('bank_id')->nullable();
            $table->string('bank_account')->nullable();
            $table->enum('status',['pending','approved','rejected','paid'])->default('pending');
        
            $table->timestamps();
        
            $table->foreign('wallet_id')
                  ->references('id')
                  ->on('wallets')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraw_requests');
    }
};
