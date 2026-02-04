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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultation_id');
            $table->unsignedBigInteger('sender_user_id');
            $table->unsignedBigInteger('receiver_user_id');
            $table->enum('message_type', ['text', 'image', 'file', 'voice']);
            $table->text('message');
            $table->string('attachment')->nullable();
            $table->boolean('is_read')->default(0);
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
        Schema::dropIfExists('messages');
    }
};
