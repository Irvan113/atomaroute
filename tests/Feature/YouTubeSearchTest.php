<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YouTubeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_search_endpoint_returns_json()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/youtube/search?q=lofi');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'video_id',
            'title',
            'author',
            'thumbnail',
            'results',
        ]);
    }

    public function test_youtube_search_direct_video_id_or_url()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/youtube/search?q=https://www.youtube.com/watch?v=5qap5aO4i9A');
        $response->assertStatus(200);
        $this->assertEquals('5qap5aO4i9A', $response->json('video_id'));
    }
}
