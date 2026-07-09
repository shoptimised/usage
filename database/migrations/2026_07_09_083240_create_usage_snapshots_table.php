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
        Schema::create('usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('period');
            $table->timestamp('period_from')->nullable()->index();
            $table->timestamp('period_to')->nullable();
            $table->string('currency', 10)->nullable();
            $table->bigInteger('current_spend_cents')->default(0);
            $table->bigInteger('bandwidth_cost_cents')->nullable();
            $table->integer('bandwidth_usage_percentage')->nullable();
            $table->unsignedBigInteger('bandwidth_allowance_bytes')->nullable();
            $table->bigInteger('credits_used_cents')->nullable();
            $table->bigInteger('credits_total_cents')->nullable();
            $table->bigInteger('alert_threshold_cents')->nullable();
            $table->integer('alert_remaining_percentage')->nullable();
            $table->bigInteger('resources_cost_cents')->default(0);
            $table->bigInteger('addons_cost_cents')->default(0);
            $table->bigInteger('applications_cost_cents')->default(0);
            $table->unsignedInteger('application_count')->default(0);
            $table->timestamp('cloud_updated_at')->nullable();
            $table->json('raw');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_snapshots');
    }
};
