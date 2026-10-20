<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CommunityFeedDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_feed_renders_fresh_topic_cards_and_opens_thread_discussion(): void
    {
        $user = User::factory()->create(['name' => 'Atoma Master']);
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Makanan Hari Ini & Tips Nutrisi',
            'content' => 'Diskusi tentang nutrisi harian untuk menjaga kesehatan saat belajar #Discussions',
            'is_pinned' => true,
        ]);

        $this->actingAs($user);

        Volt::test('social')
            ->assertSee('Community Feed')
            ->assertSee('PINNED')
            ->assertSee('Makanan Hari Ini & Tips Nutrisi')
            ->call('toggleBookmark', $post->id)
            ->assertSet('bookmarkedPostIds', [$post->id])
            ->call('openTopic', $post->id)
            ->assertSet('activeTopicId', $post->id)
            ->assertSee('Makanan Hari Ini & Tips Nutrisi')
            ->set('threadCommentContent', 'Mantap sekali tipsnya!')
            ->call('sendThreadComment')
            ->assertSee('Mantap sekali tipsnya!');

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'content' => 'Mantap sekali tipsnya!'
        ]);
    }

    public function test_user_can_create_topic_with_category_and_delete_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('social')
            ->set('postTitle', 'Topik Baru Produktif')
            ->set('postCategory', 'Milestones')
            ->set('postContent', 'Saya berhasil menyelesaikan proyek hari ini!')
            ->call('createPost');

        $createdPost = Post::where('title', 'Topik Baru Produktif')->first();
        $this->assertNotNull($createdPost);
        $this->assertStringContainsString('#Milestones', $createdPost->content);

        Volt::test('social')
            ->call('togglePin', $createdPost->id);

        $createdPost->refresh();
        $this->assertTrue($createdPost->is_pinned);

        Volt::test('social')
            ->call('deletePost', $createdPost->id);

        $this->assertDatabaseMissing('posts', ['id' => $createdPost->id]);
    }
}
