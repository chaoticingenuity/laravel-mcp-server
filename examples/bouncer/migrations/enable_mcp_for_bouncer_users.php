<?php
use Illuminate\Database\Migrations\Migration;
use Silber\Bouncer\Bouncer;

return new class extends Migration {
  public function __construct(private string $userModel = null)
  {
    $this->userModel = config('mcp.auth.user_model.class', \App\Models\User::class);
  }

  public function up(): void
  {
    // Enable MCP for users with specific roles
    $mcpRoles = ['admin', 'mcp_user', 'partner_api', 'internal_tool'];

    foreach ($mcpRoles as $role) {
      $users = $this->userModel::scope()->whereIs($role)->get();
      foreach ($users as $user) {
        $user->update(['mcp_enabled' => true]);
      }
    }
  }

  public function down(): void
  {
    // Optionally disable MCP for all users
    $this->userModel::query()->update(['mcp_enabled' => false]);
  }
};