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
        Schema::create('mentor_services', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('mentor_id');
            $table->unsignedTinyInteger('service_type_id');

            $table->decimal('price',12,2)->nullable();
            $table->unsignedInteger('duration_minutes');

            $table->timestamps();

            $table->foreign('mentor_id')
                ->references('id')
                ->on('mentors')
                ->cascadeOnDelete();

            $table->foreign('service_type_id')
                ->references('id')
                ->on('service_types');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentor_services');
    }
};
