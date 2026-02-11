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
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('agora_channel', 120)->nullable()->after('service_type_id');
            $table->integer('queue_number')->nullable()->after('agora_channel');
            $table->dateTime('expired_at')->nullable()->after('queue_number');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn(['agora_channel','queue_number','expired_at']);
        });
    }
};
