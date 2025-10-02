<?php

use ChaoticIngenuity\LaravelMCP\Http\Controllers\MCPController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', [MCPController::class, 'handle'])
    ->middleware(['mcp.security', 'mcp.auth', 'mcp.throttle', 'mcp.logging'])
    ->name('mcp.handle');

// Handle non-POST requests gracefully
Route::match(['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], '/mcp', [MCPController::class, 'handleInvalidMethod'])
    ->name('mcp.invalid_method');
