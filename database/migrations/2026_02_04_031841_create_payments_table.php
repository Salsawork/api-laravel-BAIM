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
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('consultation_id');
            $table->string('payment_method')->nullable();

            $table->string('xendit_invoice_id')->nullable();
            $table->string('xendit_external_id')->nullable();

            $table->decimal('service_price',12,2);
            $table->decimal('platform_fee',12,2);
            $table->decimal('total',12,2);

            $table->enum('status',['pending','paid','expired','failed'])->default('pending');

            $table->dateTime('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('consultation_id')
            ->references('id')
            ->on('consultations')
            ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
