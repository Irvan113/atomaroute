<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\Journal;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CommandPaletteAndDesktopEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_command_palette_search()
    {
        $response = $this->getJson('/api/command-palette/search?q=test');
        $response->assertStatus(401);
    }

    public function test_command_palette_search_returns_tasks_journals_and_flashcards()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Task::create([
            'user_id' => $user->id,
            'title' => 'Belajar Struktur Data Tree',
            'date' => Carbon::today()->toDateString(),
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        Journal::create([
            'user_id' => $user->id,
            'title' => 'Refleksi Graph Algoritma',
            'content' => 'Belajar lintasan terpendek Dijkstra.',
            'date' => Carbon::today()->toDateString(),
            'mood' => 'happy',
        ]);

        Flashcard::create([
            'user_id' => $user->id,
            'front' => 'Apa itu Binary Search Tree?',
            'back' => 'Tree terurut biner.',
            'deck' => 'Informatika',
            'next_review_at' => Carbon::now(),
        ]);

        // Search for 'Struktur'
        $response = $this->getJson('/api/command-palette/search?q=Struktur');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tasks' => [['id', 'title', 'subtitle', 'badge', 'is_completed', 'action']],
            'journals',
            'flashcards',
        ]);

        $this->assertCount(1, $response->json('tasks'));
        $this->assertEquals('Belajar Struktur Data Tree', $response->json('tasks.0.title'));

        // Search for 'Tree'
        $response2 = $this->getJson('/api/command-palette/search?q=Tree');
        $response2->assertStatus(200);
        $this->assertCount(1, $response2->json('tasks'));
        $this->assertCount(1, $response2->json('flashcards'));
        $this->assertEquals('Apa itu Binary Search Tree?', $response2->json('flashcards.0.title'));

        // Search for 'Graph'
        $response3 = $this->getJson('/api/command-palette/search?q=Graph');
        $response3->assertStatus(200);
        $this->assertCount(1, $response3->json('journals'));
        $this->assertEquals('Refleksi Graph Algoritma', $response3->json('journals.0.title'));
    }

    public function test_command_palette_search_handles_empty_or_short_query()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Rutin',
            'date' => Carbon::today()->toDateString(),
            'mode' => 'sistematis',
        ]);

        $response = $this->getJson('/api/command-palette/search?q=');
        $response->assertStatus(200);
        $response->assertJson([
            'tasks' => [],
            'journals' => [],
            'flashcards' => [],
        ]);
    }

    public function test_tasks_page_renders_command_palette()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/tasks');
        $response->assertStatus(200);
        $response->assertSee('command-palette', false);
    }
}
