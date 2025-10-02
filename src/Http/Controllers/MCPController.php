<?php

namespace ChaoticIngenuity\LaravelMCP\Http\Controllers;

use ChaoticIngenuity\LaravelMCP\MCPServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MCPController extends Controller
{
    public function __construct(
        private MCPServer $mcpServer
    ) {}

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
                    'data' => app()->environment('local') ? $e->getMessage() : 'Server error',
                ],
                'id' => $request->input('id'),
            ], 500);
        }
    }

    public function handleInvalidMethod(Request $request): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request - Only POST method allowed for MCP endpoints',
                'data' => [
                    'method' => $request->method(),
                    'allowed_methods' => ['POST']
                ]
            ],
            'id' => $request->input('id', null)
        ], 405)->header('Allow', 'POST');
    }
}
