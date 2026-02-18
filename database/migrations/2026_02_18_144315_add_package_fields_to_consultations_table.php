<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {

            // khusus muthowif
            $table->integer('people_count')
                ->nullable()
                ->after('departure_date');

            $table->decimal('package_price',12,2)
                ->nullable()
                ->after('people_count');

            $table->decimal('total_price',12,2)
                ->nullable()
                ->after('package_price');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn([
                'people_count',
                'package_price',
                'total_price'
            ]);
        });
    }
};
