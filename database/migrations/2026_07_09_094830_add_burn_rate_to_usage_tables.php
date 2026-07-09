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
        Schema::table('usage_snapshots', function (Blueprint $table) {
            $table->decimal('burn_rate_cents_per_hour', 14, 4)->nullable()->after('application_count');
        });

        Schema::table('usage_items', function (Blueprint $table) {
            $table->decimal('burn_rate_cents_per_hour', 14, 4)->nullable()->after('cost_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_snapshots', function (Blueprint $table) {
            $table->dropColumn('burn_rate_cents_per_hour');
        });

        Schema::table('usage_items', function (Blueprint $table) {
            $table->dropColumn('burn_rate_cents_per_hour');
        });
    }
};
