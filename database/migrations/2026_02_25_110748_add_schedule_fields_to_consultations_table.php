<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

 /**
     * Run the migrations.
     */
    return new class extends Migration
    {
        public function up(): void
        {
            Schema::table('consultations', function (Blueprint $table) {
    
               
    
                // durasi khusus muthowif (jam)
                if (!Schema::hasColumn('consultations', 'duration_hours')) {
                    $table->integer('duration_hours')->nullable()->after('duration_minutes');
                }
    
                if (!Schema::hasColumn('consultations', 'scheduled_start')) {
                    $table->timestamp('scheduled_start')->nullable()->after('duration_hours');
                }
    
    
                if (!Schema::hasColumn('consultations', 'scheduled_end')) {
                    $table->timestamp('scheduled_end')->nullable()->after('scheduled_start');
                }
    
            });
        }
    
        public function down(): void
        {
            Schema::table('consultations', function (Blueprint $table) {
                $table->dropColumn([
                    'duration_hours',
                    'scheduled_start',
                    'scheduled_end'
                ]);
            });
        }
    };