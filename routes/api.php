<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\McpServerController;

/*
|--------------------------------------------------------------------------
| API Routes - Model Context Protocol (MCP) Server
|--------------------------------------------------------------------------
|
| Endpoint standar JSON-RPC 2.0 untuk klien AI seperti Claude Desktop,
| Cursor IDE, Antigravity, dan AI Agents lainnya.
|
*/

Route::get('/mcp', [McpServerController::class, 'info'])->name('api.mcp.info');
Route::post('/mcp', [McpServerController::class, 'handle'])->name('api.mcp.handle');
