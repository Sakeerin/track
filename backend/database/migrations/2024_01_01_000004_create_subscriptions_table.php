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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->string('channel', 20); // email, sms, line, webhook
            $table->string('destination', 255);
            $table->json('events'); // array of event codes to notify
            $table->boolean('active')->default(true);
            $table->boolean('consent_given')->default(false);
            $table->string('consent_ip', 45)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('unsubscribe_token', 100)->unique()->nullable();
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');

            $table->index(['shipment_id', 'channel'], 'idx_shipment_channel');
            $table->index('destination');
            $table->index('unsubscribe_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};