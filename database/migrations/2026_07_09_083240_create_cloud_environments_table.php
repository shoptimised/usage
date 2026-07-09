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
        Schema::create('cloud_environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloud_application_id')->constrained()->cascadeOnDelete();
            $table->string('cloud_id')->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->index();
            $table->string('vanity_domain')->nullable();
            $table->string('php_major_version', 8)->nullable();
            $table->boolean('uses_octane')->default(false);
            $table->boolean('uses_hibernation')->default(false);
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
        Schema::dropIfExists('cloud_environments');
    }
};
