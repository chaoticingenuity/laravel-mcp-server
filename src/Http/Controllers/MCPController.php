<?php

namespace ChaoticIngenuity\LaravelMCP\Http\Controllers;

use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Routing\Controller;
use ChaoticIngenuity\LaravelMCP\MCPServer;

class MCPController extends Controller
{
  public function __construct(
    private MCPServer $mcpServer
  ) {
  }

  public function handle(Request $request): JsonResponse
  {
    try {
      $response = $this->mcpServer->handleRequest($request->all());
      return response()->json($response);
    } catch (\Exception $e) {
      return response()->json([
        'jsonrpc' => '2.0',
        'error' => [
          'code' => -32603,
          'message' => 'Internal error',
          'data' => app()->environment('local') ? $e->getMessage() : 'Server error'
        ],
        'id' => $request->input('id')
      ], 500);
    }
  }
}