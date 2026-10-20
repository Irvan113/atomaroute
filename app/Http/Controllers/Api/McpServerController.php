<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use App\Services\McpServerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class McpServerController extends Controller
{
    /**
     * Handle incoming JSON-RPC 2.0 requests from MCP clients (Claude Desktop, Cursor, Antigravity, etc.).
     */
    public function handle(Request $request): JsonResponse
    {
        $rawToken = $this->extractToken($request);

        if (!$rawToken) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized: Missing MCP Bearer Token. Sediakan header Authorization: Bearer atoma_mcp_... atau query ?token=...',
                ],
            ], 401);
        }

        $mcpToken = McpToken::findByToken($rawToken);

        if (!$mcpToken || !$mcpToken->user) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized: Token MCP ATOMA tidak valid atau sudah dicabut.',
                ],
            ], 401);
        }

        if ($mcpToken->expires_at && $mcpToken->expires_at->isPast()) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized: Token MCP ATOMA telah kedaluwarsa.',
                ],
            ], 401);
        }

        // Update last used timestamp
        $mcpToken->update(['last_used_at' => now()]);

        // Process request payload
        $payload = $request->all();
        if (empty($payload) || !is_array($payload)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error: Request body must be valid JSON-RPC object.',
                ],
            ], 400);
        }

        $response = McpServerService::handleJsonRpc($payload, $mcpToken->user);

        return response()->json($response);
    }

    /**
     * Provide server info and connection instructions on GET request.
     */
    public function info(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'online',
            'server' => 'ATOMA Model Context Protocol (MCP) Server',
            'version' => '1.0.0',
            'protocol_version' => '2024-11-05',
            'transport' => 'HTTP JSON-RPC 2.0 (POST /api/mcp)',
            'authentication' => 'Bearer Token (Header Authorization: Bearer atoma_mcp_... atau query ?token=...)',
            'tools_count' => count(McpServerService::getAvailableTools()),
            'docs' => 'Generate your personal MCP token in ATOMA Settings -> Integrasi AI & MCP',
        ]);
    }

    /**
     * Extract token from Authorization header or query parameter.
     */
    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header && preg_match('/Bearer\s+(atoma_mcp_\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        // Fallback to query parameter ?token=atoma_mcp_...
        if ($request->has('token') && str_starts_with($request->query('token'), 'atoma_mcp_')) {
            return $request->query('token');
        }

        return null;
    }
}
