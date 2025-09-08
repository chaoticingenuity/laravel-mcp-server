<?php

use ChaoticIngenuity\LaravelMCP\Http\Controllers\MCPController;
use Illuminate\Support\Facades\Route;

Route
  ::post('/mcp', [MCPController::class, 'handle'])
  ->middleware(['mcp.security', 'mcp.auth', 'mcp.throttle', 'mcp.logging'])
  ->name('mcp.handle')
;