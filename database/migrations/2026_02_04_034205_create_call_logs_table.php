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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('consultation_id');
            $table->enum('call_type', ['audio', 'video']);
            $table->datetime('started_at');
            $table->datetime('ended_at');
            $table->integer('duration_seconds');
            $table->enum('status', ['connected', 'missed', 'failed', 'ended']);
            $table->string('provider');
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
        Schema::dropIfExists('call_logs');
    }
};
