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
        Schema::create('eta_lanes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('origin_facility_id');
            $table->uuid('destination_facility_id');
            $table->string('service_type'); // 'standard', 'express', 'economy'
            $table->integer('base_hours'); // Base delivery time in hours
            $table->integer('min_hours')->nullable(); // Minimum delivery time
            $table->integer('max_hours')->nullable(); // Maximum delivery time
            $table->json('day_adjustments')->nullable(); // Per-day adjustments
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->foreign('origin_facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('destination_facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->unique(['origin_facility_id', 'destination_facility_id', 'service_type'], 'unique_lane_service');
            $table->index(['origin_facility_id', 'destination_facility_id']);
            $table->index('service_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eta_lanes');
    }
};