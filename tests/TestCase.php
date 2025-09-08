<?php

namespace ChaoticIngenuity\LaravelMCP\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ChaoticIngenuity\LaravelMCP\Providers\MCPServiceProvider;

abstract class TestCase extends Orchestra
{
  protected function getPackageProviders($app): array
  {
    return [
      MCPServiceProvider::class,
    ];
  }

  public function getEnvironmentSetUp($app): void
  {
    config()->set('database.default', 'testing');
  }
}