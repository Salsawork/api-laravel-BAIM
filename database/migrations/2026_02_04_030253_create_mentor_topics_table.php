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
        Schema::create('mentor_topics', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mentor_id');
            $table->unsignedInteger('topic_category_id');
        
            $table->timestamps();
        
            $table->unique(['mentor_id','topic_category_id']);
        
            $table->foreign('mentor_id')
                  ->references('id')
                  ->on('mentors')
                  ->cascadeOnDelete();
        
            $table->foreign('topic_category_id')
                  ->references('id')
                  ->on('topic_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentor_topics');
    }
};
