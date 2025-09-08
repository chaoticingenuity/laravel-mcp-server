<?php

use Illuminate\Support\Facades\Route;
use ChaoticIngenuity\LaravelMCP\Http\Controllers\MCPController;

Route::post('/mcp', [MCPController::class, 'handle'])
  ->middleware(['mcp.security', 'mcp.auth', 'mcp.throttle', 'mcp.logging'])
  ->name('mcp.handle')
;