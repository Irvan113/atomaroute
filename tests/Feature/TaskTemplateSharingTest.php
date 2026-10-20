<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TemplateShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TaskTemplateSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_template_modal_can_open_and_view_presets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('tasks')
            ->call('openAiTemplateModal')
            ->assertSet('showAiTemplateModal', true)
            ->assertSet('templateActiveTab', 'presets')
            ->call('setTemplateCategoryFilter', 'Work')
            ->assertSet('templateCategoryFilter', 'Work')
            ->call('closeAiTemplateModal')
            ->assertSet('showAiTemplateModal', false);
    }

    public function test_apply_curated_preset_creates_task_with_subtasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('tasks')
            ->call('applyTaskTemplate', 'deep_work')
            ->assertSet('showAiTemplateModal', false);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Deep Work: Sprint Pengembangan Fitur',
            'category' => 'Work',
            'estimated_minutes' => 90,
        ]);

        $task = $user->tasks()->where('title', 'Deep Work: Sprint Pengembangan Fitur')->first();
        $this->assertGreaterThanOrEqual(4, $task->subTasks()->count());
    }

    public function test_ai_decomposer_generates_preview_and_applies(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('tasks')
            ->set('aiDecomposePrompt', 'Belajar Laravel Framework Dasar')
            ->call('decomposeAiTask')
            ->assertSet('showTemplatePreview', true)
            ->call('applyPreviewedTask')
            ->assertSet('showTemplatePreview', false)
            ->assertSet('showAiTemplateModal', false);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'category' => 'Study',
        ]);

        $task = $user->tasks()->latest('id')->first();
        $this->assertNotNull($task);
        $this->assertNotEmpty($task->title);
        $this->assertGreaterThanOrEqual(1, $task->subTasks()->count());
    }

    public function test_save_existing_task_as_custom_template(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = $user->tasks()->create([
            'title' => 'Rilis Fitur Payment Gateway',
            'description' => 'SOP rilis modul pembayaran',
            'category' => 'Work',
            'priority' => 'high',
            'estimated_minutes' => 60,
            'mode' => 'sistematis',
            'date' => now()->toDateString(),
        ]);

        $task->subTasks()->createMany([
            ['title' => 'Konfigurasi webhook URL', 'is_completed' => false],
            ['title' => 'Uji sandbox payment response', 'is_completed' => false],
            ['title' => 'Switch ke live production credential', 'is_completed' => false],
        ]);

        Volt::test('tasks')
            ->call('saveTaskAsTemplate', $task->id);

        $this->assertDatabaseHas('task_templates', [
            'user_id' => $user->id,
            'title' => 'Rilis Fitur Payment Gateway',
            'category' => 'Work',
            'is_public' => false,
        ]);

        $template = TaskTemplate::where('title', 'Rilis Fitur Payment Gateway')->first();
        $this->assertCount(3, $template->subtasks);
    }

    public function test_toggle_template_public_and_cloning_by_another_user(): void
    {
        $author = User::factory()->create(['name' => 'Alice']);
        $seeker = User::factory()->create(['name' => 'Bob']);

        $template = TaskTemplate::create([
            'user_id' => $author->id,
            'title' => 'Morning Zen & Breathwork Routine',
            'description' => 'Rutinitas pernapasan 15 menit',
            'category' => 'Health',
            'priority' => 'normal',
            'estimated_minutes' => 15,
            'mode' => 'zen',
            'subtasks' => ['Minum segelas air lemon', 'Pernapasan kotak 4-4-4', 'Stretching bahu'],
            'is_public' => false,
            'clones_count' => 0,
        ]);

        // Author toggles to public
        $this->actingAs($author);
        Volt::test('tasks')
            ->call('togglePublicTemplate', $template->id);

        $this->assertTrue($template->fresh()->is_public);

        // Seeker browses and applies the community template
        $this->actingAs($seeker);
        Volt::test('tasks')
            ->call('applyTaskTemplate', $template->id);

        // Verify task was created for seeker
        $this->assertDatabaseHas('tasks', [
            'user_id' => $seeker->id,
            'title' => 'Morning Zen & Breathwork Routine',
        ]);

        // Verify clone count incremented
        $this->assertEquals(1, $template->fresh()->clones_count);
    }

    public function test_share_template_with_friend_and_friend_accepts(): void
    {
        $sender = User::factory()->create(['name' => 'John']);
        $receiver = User::factory()->create(['name' => 'Jane']);

        // Establish friendship
        $sender->friends()->attach($receiver->id, ['status' => 'accepted']);
        $receiver->friends()->attach($sender->id, ['status' => 'accepted']);

        $template = TaskTemplate::create([
            'user_id' => $sender->id,
            'title' => 'Sprint Coding Fitur AI',
            'category' => 'Work',
            'priority' => 'high',
            'estimated_minutes' => 45,
            'subtasks' => ['Step A', 'Step B'],
            'is_public' => false,
        ]);

        // Sender sends template to friend
        $this->actingAs($sender);
        Volt::test('tasks')
            ->call('openShareWithFriendModal', $template->id)
            ->set('shareFriendId', $receiver->id)
            ->set('shareFriendNote', 'Coba template ini untuk sprint kita!')
            ->call('sendTemplateToFriend')
            ->assertSet('showShareModal', false);

        $this->assertDatabaseHas('template_shares', [
            'task_template_id' => $template->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'pending',
        ]);

        $share = TemplateShare::first();

        // Receiver accepts template
        $this->actingAs($receiver);
        Volt::test('tasks')
            ->call('acceptTemplateShare', $share->id);

        $this->assertEquals('accepted', $share->fresh()->status);
        $this->assertDatabaseHas('tasks', [
            'user_id' => $receiver->id,
            'title' => 'Sprint Coding Fitur AI',
        ]);
    }
}
