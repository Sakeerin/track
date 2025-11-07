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
        Schema::create('eta_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('rule_type'); // 'service_modifier', 'holiday_adjustment', 'cutoff_time', 'congestion'
            $table->json('conditions'); // JSON conditions for rule application
            $table->json('adjustments'); // JSON adjustments to apply (hours, days, multiplier)
            $table->integer('priority')->default(0); // Higher priority rules override lower ones
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['rule_type', 'active']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eta_rules');
    }
};