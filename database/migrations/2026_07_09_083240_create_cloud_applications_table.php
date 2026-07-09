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
        Schema::create('cloud_applications', function (Blueprint $table) {
            $table->id();
            $table->string('cloud_id')->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('region');
            $table->string('repository')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamp('cloud_created_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloud_applications');
    }
};
