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
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_number', 50)->unique();
            $table->string('reference_number', 100)->nullable();
            $table->string('service_type', 50);
            $table->uuid('origin_facility_id')->nullable();
            $table->uuid('destination_facility_id')->nullable();
            $table->string('current_status', 50);
            $table->uuid('current_location_id')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamps();

            $table->foreign('origin_facility_id')->references('id')->on('facilities');
            $table->foreign('destination_facility_id')->references('id')->on('facilities');
            $table->foreign('current_location_id')->references('id')->on('facilities');

            $table->index('tracking_number');
            $table->index('reference_number');
            $table->index('current_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};