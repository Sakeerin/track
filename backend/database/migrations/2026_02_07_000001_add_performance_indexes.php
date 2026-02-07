<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite index for shipment queries with status and updated_at
        // This improves cache warming queries
        Schema::table('shipments', function (Blueprint $table) {
            // Index for active shipment queries (cache warming)
            $table->index(['current_status', 'updated_at'], 'idx_shipments_status_updated');
            
            // Index for estimated delivery queries
            $table->index('estimated_delivery', 'idx_shipments_estimated_delivery');
        });

        // Add covering index for event timeline queries
        Schema::table('events', function (Blueprint $table) {
            // Index for source-based filtering
            $table->index('source', 'idx_events_source');
            
            // Composite index for event timeline with ordering
            $table->index(['shipment_id', 'event_time', 'event_code'], 'idx_events_timeline');
        });

        // Add index for subscription notification queries
        Schema::table('subscriptions', function (Blueprint $table) {
            // Index for active subscriptions by shipment
            $table->index(['shipment_id', 'active'], 'idx_subscriptions_active');
        });

        // Create partial index for PostgreSQL (only active shipments)
        // This significantly improves queries that filter by status
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_shipments_active ON shipments (tracking_number, updated_at) WHERE current_status NOT IN (\'delivered\', \'returned\')');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('idx_shipments_status_updated');
            $table->dropIndex('idx_shipments_estimated_delivery');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_source');
            $table->dropIndex('idx_events_timeline');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_active');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_shipments_active');
        }
    }
};
