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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->string('event_id', 100);
            $table->string('event_code', 50);
            $table->timestamp('event_time');
            $table->uuid('facility_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('source', 50);
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('location_id')->references('id')->on('facilities');

            $table->unique(['shipment_id', 'event_id', 'event_time'], 'unique_event');
            $table->index(['shipment_id', 'event_time'], 'idx_shipment_time');
            $table->index('event_code');
            $table->index('event_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};