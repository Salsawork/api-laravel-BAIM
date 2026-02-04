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
        Schema::create('mentors', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('user_id')->unique();
            $table->unsignedTinyInteger('user_type_id');
    
            $table->string('full_name',150)->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->unsignedInteger('experience_years')->nullable();
    
            $table->text('description')->nullable();
    
            $table->string('ktp_photo')->nullable();
    
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_holder_name')->nullable();
    
            $table->boolean('is_verified')->default(0);
            $table->boolean('is_online')->default(0);
    
            $table->decimal('rating_avg',3,2)->default(0);
            $table->unsignedInteger('total_sessions')->default(0);
    
            $table->unsignedInteger('cooldown_minutes')->default(30);
            $table->timestamps();
            $table->foreign('user_type_id')
                ->references('id')
                ->on('user_types');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentors');
    }
};
