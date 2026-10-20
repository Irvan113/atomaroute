<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Journal;
use App\Models\Flashcard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;
use Carbon\Carbon;

class FlashcardSpacedRepetitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_edit_and_delete_flashcard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Create Flashcard
        Volt::test('flashcard.index')
            ->set('front', 'Apa itu Spaced Repetition?')
            ->set('back', 'Teknik belajar dengan mengulang materi dalam interval waktu yang bertambah panjang.')
            ->set('deck', 'Ilmu Belajar')
            ->call('saveCard')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('flashcards', [
            'user_id' => $user->id,
            'front' => 'Apa itu Spaced Repetition?',
            'deck' => 'Ilmu Belajar',
            'repetitions' => 0,
            'interval_days' => 1,
        ]);

        $card = Flashcard::first();

        // 2. Edit Flashcard
        Volt::test('flashcard.index')
            ->call('editCard', $card->id)
            ->assertSet('front', 'Apa itu Spaced Repetition?')
            ->set('front', 'Apa definisi Spaced Repetition?')
            ->call('saveCard')
            ->assertHasNoErrors();

        $this->assertEquals('Apa definisi Spaced Repetition?', $card->fresh()->front);

        // 3. Delete Flashcard
        Volt::test('flashcard.index')
            ->call('deleteCard', $card->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('flashcards', [
            'id' => $card->id,
        ]);
    }

    public function test_spaced_repetition_rating_logic_updates_interval_and_ease(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $card = Flashcard::create([
            'user_id' => $user->id,
            'front' => 'Capital of France?',
            'back' => 'Paris',
            'deck' => 'Geography',
            'repetitions' => 0,
            'interval_days' => 1,
            'ease_factor' => 2.50,
            'next_review_at' => Carbon::now()->subDay(),
        ]);

        // Rate Good (3)
        $card->recordReview('good');
        $card->refresh();

        $this->assertEquals(1, $card->repetitions);
        $this->assertEquals(1, $card->interval_days);

        // Rate Good again (second repetition -> interval should become 3)
        $card->recordReview('good');
        $card->refresh();

        $this->assertEquals(2, $card->repetitions);
        $this->assertEquals(3, $card->interval_days);

        // Rate Again (1) -> resets repetitions to 0 and interval to 1
        $card->recordReview('again');
        $card->refresh();

        $this->assertEquals(0, $card->repetitions);
        $this->assertEquals(1, $card->interval_days);
        $this->assertEquals('00:00:00', $card->next_review_at->format('H:i:s'));
    }

    public function test_extract_flashcards_from_journal_note(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $journal = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Docker & Container',
            'content' => "Docker adalah platform untuk membangun dan menjalankan aplikasi dalam container.\nDockerfile: Script instruksi untuk membuat Docker image.\nBagaimana cara stop container? Gunakan docker stop <container_id>",
        ]);

        Volt::test('journal.index')
            ->call('extractFlashcardsFromNote', $journal->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('flashcards', [
            'user_id' => $user->id,
            'journal_id' => $journal->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $journal->flashcards()->count());
    }

    public function test_finance_task_completion_triggers_transaction(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = $user->tasks()->create([
            'title' => 'Bayar Tagihan Listrik PLN',
            'category' => 'Finance',
            'date' => now()->toDateString(),
            'is_completed' => false,
        ]);

        // Toggle complete triggers Finance modal
        Volt::test('tasks')
            ->call('toggleComplete', $task->id)
            ->assertSet('showFinancePromptModal', true)
            ->assertSet('financeTaskId', $task->id)
            ->set('financeTaskAmount', 250000)
            ->set('financeTaskType', 'expense')
            ->set('financeTaskCategory', 'Tagihan')
            ->call('saveFinanceFromTask')
            ->assertSet('showFinancePromptModal', false);

        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $user->id,
            'type' => 'expense',
            'amount' => 250000,
            'category' => 'Tagihan',
        ]);
    }

    public function test_ai_task_template_modal_and_apply(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('tasks')
            ->call('openAiTemplateModal')
            ->assertSet('showAiTemplateModal', true)
            ->call('applyTaskTemplate', 'deep_work')
            ->assertSet('showAiTemplateModal', false);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Deep Work: Sprint Pengembangan Fitur',
            'category' => 'Work',
            'estimated_minutes' => 90,
        ]);

        $task = $user->tasks()->where('title', 'Deep Work: Sprint Pengembangan Fitur')->first();
        $this->assertCount(4, $task->subTasks);
    }
}
