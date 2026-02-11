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
        Schema::table('mentors', function (Blueprint $table) {

            $table->dateTime('last_seen')
                ->nullable()
                ->after('is_online');

            $table->unsignedBigInteger('current_consultation_id')
                ->nullable()
                ->after('last_seen');
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            $table->dropColumn([
                'is_online',
                'last_seen',
                'current_consultation_id'
            ]);
        });
    }
};
