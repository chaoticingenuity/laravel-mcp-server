<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: This migration is OPTIONAL and only needed if you're using the default
     * ApiKey model provided by the package. Most production implementations should
     * use custom storage patterns (user table columns, JSON columns, etc.).
     *
     * To publish this migration:
     * php artisan vendor:publish --tag=mcp-migrations-api-keys
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();

            // Core API key fields
            $table->string('key')->unique();
            $table->string('client_identifier');
            $table->string('name')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            // Enhanced features (v1.1.0+)
            $table->integer('usage_count')->default(0);
            $table->integer('rate_limit_per_minute')->nullable();
            $table->integer('rate_limit_burst')->nullable();

            // User relationship (configurable)
            $table->unsignedBigInteger('user_id');

            $table->timestamps();

            // Performance indexes for common query patterns
            $table->index(['key', 'is_active', 'expires_at'], 'api_keys_lookup_idx');
            $table->index(['user_id', 'is_active'], 'api_keys_user_active_idx');
            $table->index(['client_identifier', 'is_active'], 'api_keys_client_active_idx');
            $table->index('last_used_at', 'api_keys_last_used_idx');
            $table->index('usage_count', 'api_keys_usage_idx');
            $table->index('rate_limit_per_minute', 'api_keys_rate_limit_idx');

            // Foreign key constraint (users can customize this)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
