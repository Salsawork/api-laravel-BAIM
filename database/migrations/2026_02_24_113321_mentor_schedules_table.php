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
        Schema::create('mentor_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
        
            $table->enum('day_of_week', [
                'senin','selasa','rabu',
                'kamis','jumat','sabtu','minggu'
            ]);
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
        
            // harga khusus (muthowif wajib)
            $table->decimal('price',12,2)->nullable();
        
            $table->boolean('is_active')->default(1);
        
            $table->timestamps();
        
            // $table->foreign('mentor_id')
            //     ->references('id')
            //     ->on('mentors')
            //     ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
