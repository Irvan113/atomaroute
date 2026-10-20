<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\McpToken;
use App\Models\User;
use App\Services\McpServerService;

class McpServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:serve {--token= : The ATOMA MCP Bearer Token} {--user= : User ID fallback}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start ATOMA Model Context Protocol (MCP) server over stdio for Claude Desktop / Cursor';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawToken = $this->option('token');
        $userId = $this->option('user');

        $user = null;

        if ($rawToken) {
            $mcpToken = McpToken::findByToken($rawToken);
            if ($mcpToken && $mcpToken->user) {
                $user = $mcpToken->user;
                $mcpToken->update(['last_used_at' => now()]);
            }
        } elseif ($userId) {
            $user = User::find($userId);
        } else {
            // Default to first user if single user setup
            $user = User::first();
        }

        if (!$user) {
            fwrite(STDERR, "Error: User or valid MCP Token not found.\n");
            return 1;
        }

        fwrite(STDERR, "ATOMA MCP stdio server started for user: {$user->email}\n");

        while ($line = fgets(STDIN)) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $payload = json_decode($line, true);
            if (!is_array($payload)) {
                $err = [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: Invalid JSON string.',
                    ],
                ];
                fwrite(STDOUT, json_encode($err) . "\n");
                fflush(STDOUT);
                continue;
            }

            $response = McpServerService::handleJsonRpc($payload, $user);

            // Output JSON-RPC response strictly to STDOUT
            fwrite(STDOUT, json_encode($response) . "\n");
            fflush(STDOUT);
        }

        return 0;
    }
}
