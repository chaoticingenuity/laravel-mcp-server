<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->boolean('mcp_enabled')->default(false);
      $table->json('mcp_permissions')->nullable();
      $table->json('mcp_field_access')->nullable();
      $table->json('mcp_tokens')->nullable();

      $table->index('mcp_enabled');
    });
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn([
        'mcp_enabled',
        'mcp_permissions',
        'mcp_field_access',
        'mcp_tokens'
      ]);
    });
  }
};