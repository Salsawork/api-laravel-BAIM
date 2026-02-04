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
        Schema::create('consultations', function (Blueprint $table) {

            $table->bigIncrements('id');
        
            $table->string('order_number',50)->unique();
        
            $table->unsignedBigInteger('customer_user_id');
        
            $table->unsignedInteger('mentor_id');
            $table->unsignedTinyInteger('service_type_id');
            $table->unsignedInteger('topic_category_id');
            $table->unsignedInteger('schedule_id')->nullable();

            $table->decimal('price',12,2);
            $table->unsignedInteger('duration_minutes');
        
            $table->enum('status',['pending','active','completed','cancelled','expired'])->default('pending');
            $table->enum('payment_status',['waiting','paid','failed','refund'])->default('waiting');
        
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
        
            $table->timestamps();
        
            $table->foreign('mentor_id')->references('id')->on('mentors');
            $table->foreign('service_type_id')->references('id')->on('service_types');
            $table->foreign('topic_category_id')->references('id')->on('topic_categories');
            // $table->foreign('schedule_id')
            // ->references('id')
            // ->on('schedules')
            // ->nullOnDelete();
      
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
