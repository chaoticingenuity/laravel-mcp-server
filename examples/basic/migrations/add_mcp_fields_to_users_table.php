<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTE: This migration is OPTIONAL and only needed if you want to add MCP
     * fields directly to your users table. Many implementations will prefer
     * different approaches:
     * 
     * - Store permissions in roles/permissions tables (Bouncer, Spatie Permission)
     * - Use JSON columns differently
     * - Store in separate profile/settings tables
     * - Use custom logic in User model methods
     * 
     * To publish this migration:
     * php artisan vendor:publish --tag=mcp-migrations
     * 
     * Then customize the fields and table name as needed for your implementation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Core MCP access control
            $table->boolean('mcp_enabled')->default(false);
            
            // Permission storage (alternative to Bouncer/Spatie Permission)
            $table->json('mcp_permissions')->nullable();
            $table->json('mcp_field_access')->nullable();
            
            // Token-based authentication (alternative to API keys table)
            $table->json('mcp_tokens')->nullable();
            
            // Scope-based permissions (v1.1.0+)
            $table->json('mcp_scopes')->nullable();
            
            // Performance index for MCP access queries
            $table->index('mcp_enabled', 'users_mcp_enabled_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_mcp_enabled_idx');
            $table->dropColumn([
                'mcp_enabled',
                'mcp_permissions', 
                'mcp_field_access',
                'mcp_tokens',
                'mcp_scopes'
            ]);
        });
    }
};