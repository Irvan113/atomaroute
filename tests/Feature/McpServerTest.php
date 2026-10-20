<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\McpToken;
use App\Models\Task;
use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_info_endpoint_returns_online_status()
    {
        $response = $this->getJson('/api/mcp');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'online',
                'server' => 'ATOMA Model Context Protocol (MCP) Server',
            ]);
    }

    public function test_mcp_endpoint_rejects_unauthorized_request()
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', -32001);
    }

    public function test_mcp_initialize_and_tools_list()
    {
        $user = User::factory()->create();
        $token = McpToken::createForUser($user, 'Test Client');

        // Test initialize
        $initResponse = $this->withHeader('Authorization', 'Bearer ' . $token->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2024-11-05',
                ],
            ]);

        $initResponse->assertStatus(200)
            ->assertJsonPath('result.protocolVersion', '2024-11-05')
            ->assertJsonPath('result.serverInfo.name', 'atoma-mcp-server');

        // Test tools/list
        $toolsResponse = $this->withHeader('Authorization', 'Bearer ' . $token->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ]);

        $toolsResponse->assertStatus(200);
        $tools = $toolsResponse->json('result.tools');
        $this->assertIsArray($tools);
        $this->assertCount(6, $tools);

        $toolNames = array_column($tools, 'name');
        $this->assertContains('atoma_get_tasks', $toolNames);
        $this->assertContains('atoma_create_task', $toolNames);
        $this->assertContains('atoma_complete_task', $toolNames);
        $this->assertContains('atoma_search_notes', $toolNames);
        $this->assertContains('atoma_create_note', $toolNames);
        $this->assertContains('atoma_get_user_stats', $toolNames);
    }

    public function test_mcp_create_task_and_complete_task_with_xp()
    {
        $user = User::factory()->create(['xp' => 0, 'level' => 1]);
        $token = McpToken::createForUser($user, 'Test Client');

        // 1. Create task via MCP
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 10,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'atoma_create_task',
                    'arguments' => [
                        'title' => 'Selesaikan PR #101 di Cursor',
                        'priority' => 'high',
                        'category' => 'Work',
                        'estimated_minutes' => 45,
                    ],
                ],
            ]);

        $createResponse->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Selesaikan PR #101 di Cursor',
            'priority' => 'high',
        ]);

        $createdTask = Task::where('user_id', $user->id)->first();
        $this->assertNotNull($createdTask);

        // 2. Query tasks via MCP
        $getTasksResponse = $this->withHeader('Authorization', 'Bearer ' . $token->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 11,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'atoma_get_tasks',
                    'arguments' => [
                        'status' => 'pending',
                    ],
                ],
            ]);

        $getTasksResponse->assertStatus(200);
        $content = $getTasksResponse->json('result.content.0.text');
        $this->assertStringContainsString('Selesaikan PR #101 di Cursor', $content);

        // 3. Complete task via MCP
        $completeResponse = $this->withHeader('Authorization', 'Bearer ' . $token->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 12,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'atoma_complete_task',
                    'arguments' => [
                        'task_id' => $createdTask->id,
                    ],
                ],
            ]);

        $completeResponse->assertStatus(200);
        $createdTask->refresh();
        $this->assertTrue($createdTask->is_completed);

        // User earned XP (credited to daily/pending XP according to gamification engine)
        $user->refresh();
        $this->assertGreaterThan(0, $user->daily_xp_earned);
        $this->assertGreaterThan(0, $createdTask->fresh()->earned_xp);
    }

    public function test_mcp_multi_tenant_isolation()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $tokenA = McpToken::createForUser($userA, 'Client A');

        $taskB = Task::create([
            'user_id' => $userB->id,
            'title' => 'Rahasia User B',
            'is_completed' => false,
        ]);

        // User A attempts to complete User B's task
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 99,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'atoma_complete_task',
                    'arguments' => [
                        'task_id' => $taskB->id,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('tidak ditemukan di akun Anda', $text);
        $this->assertFalse($taskB->fresh()->is_completed);
    }

    public function test_mcp_create_note_and_search_notes()
    {
        $user = User::factory()->create();
        $token = McpToken::createForUser($user, 'Client Notes');

        // Create Note via MCP
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 20,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'atoma_create_note',
                    'arguments' => [
                        'title' => 'Rencana Desain Arsitektur Microservices',
                        'content' => 'Dokumentasi detail mengenai decoupling service order dan payment.',
                    ],
                ],
            ]);

        $createResponse->assertStatus(200);
        $this->assertDatabaseHas('journals', [
            'user_id' => $user->id,
            'title' => 'Rencana Desain Arsitektur Microservices',
        ]);

        // Search Note via MCP
        $searchResponse = $this->withHeader('Authorization', 'Bearer ' . $token->token)
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 21,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'atoma_search_notes',
                    'arguments' => [
                        'query' => 'Microservices',
                    ],
                ],
            ]);

        $searchResponse->assertStatus(200);
        $text = $searchResponse->json('result.content.0.text');
        $this->assertStringContainsString('Rencana Desain Arsitektur Microservices', $text);
    }

    public function test_profile_mcp_token_manager_livewire_component()
    {
        $user = User::factory()->create();

        $component = Volt::actingAs($user)
            ->test('profile.mcp-token-manager')
            ->set('tokenName', 'Cursor Mac M3')
            ->call('createToken');

        $this->assertDatabaseHas('mcp_tokens', [
            'user_id' => $user->id,
            'name' => 'Cursor Mac M3',
        ]);

        $token = McpToken::where('user_id', $user->id)->first();
        $this->assertNotNull($token);
        $this->assertStringStartsWith('atoma_mcp_', $token->token);

        // Delete token
        $component->call('deleteToken', $token->id);
        $this->assertDatabaseMissing('mcp_tokens', [
            'id' => $token->id,
        ]);
    }
}
