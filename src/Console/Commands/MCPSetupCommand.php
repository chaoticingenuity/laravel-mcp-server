<?php
namespace ChaoticIngenuity\LaravelMCP\Console\Commands;
use Illuminate\Console\Command as BaseCommand;
use \Symfony\Component\Console\Command\Command;
class MCPSetupCommand extends BaseCommand
{
  protected $signature = 'mcp:setup {--bouncer : Enable Bouncer integration}';
  protected $description = 'Set up Laravel MCP Server with optional Bouncer integration';
  public function handle()
  {
    if ($this->option('bouncer')) {
      if (!class_exists(\Silber\Bouncer\BouncerFacade::class)) {
        $this->error('Bouncer package not installed. Run: composer require silber/bouncer');
        return Command::FAILURE;
      }

      $this->setupBouncer();
    }

    $this->setupBasic();

    return Command::SUCCESS;
  }

  private function setupBouncer()
  {
    $this->info('Setting up MCP with Bouncer integration...');

    // Publish Bouncer-specific files
    $this->call(
      'vendor:publish',
      [
        '--tag' => 'mcp-bouncer-examples'
      ]
    );
  }
  private function setupBasic()
  {
    $this->info('Setting up basic MCP configuration...');

    $this->call(
      'vendor:publish',
      [
        '--tag' => 'mcp-config'
      ]
    );
  }
}