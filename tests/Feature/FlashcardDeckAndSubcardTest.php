<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Flashcard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;
use Carbon\Carbon;

class FlashcardDeckAndSubcardTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_add_subcard_creates_card_in_active_deck(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('flashcard.index')
            ->set('activeDeck', 'English Vocabulary')
            ->set('quickFront', 'ubiquitous')
            ->set('quickBack', 'present everywhere')
            ->call('quickAddSubCard')
            ->assertHasNoErrors()
            ->assertSet('quickFront', '')
            ->assertSet('quickBack', '');

        $this->assertDatabaseHas('flashcards', [
            'user_id' => $user->id,
            'deck' => 'English Vocabulary',
            'front' => 'ubiquitous',
            'back' => 'present everywhere',
            'repetitions' => 0,
            'interval_days' => 1,
        ]);
    }

    public function test_bulk_import_parses_multiple_separators_and_creates_subcards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $bulkText = "apple = buah apel\nbook : sebuah buku\ncat - seekor kucing\ndog\tseekor anjing";

        Volt::test('flashcard.index')
            ->set('bulkDeckName', 'Vocabulary N5')
            ->set('bulkInputText', $bulkText)
            ->call('importBulkCards')
            ->assertHasNoErrors()
            ->assertSet('showBulkModal', false);

        $this->assertDatabaseCount('flashcards', 4);
        $this->assertDatabaseHas('flashcards', ['front' => 'apple', 'back' => 'buah apel', 'deck' => 'Vocabulary N5']);
        $this->assertDatabaseHas('flashcards', ['front' => 'book', 'back' => 'sebuah buku', 'deck' => 'Vocabulary N5']);
        $this->assertDatabaseHas('flashcards', ['front' => 'cat', 'back' => 'seekor kucing', 'deck' => 'Vocabulary N5']);
        $this->assertDatabaseHas('flashcards', ['front' => 'dog', 'back' => 'seekor anjing', 'deck' => 'Vocabulary N5']);
    }

    public function test_review_launcher_filters_by_mode_and_batch_size(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create 5 cards due
        for ($i = 1; $i <= 5; $i++) {
            Flashcard::create([
                'user_id' => $user->id,
                'deck' => 'Vocab',
                'front' => "Due Card {$i}",
                'back' => "Answer {$i}",
                'repetitions' => 1,
                'interval_days' => 3,
                'ease_factor' => 2.50,
                'next_review_at' => Carbon::now()->subDay(),
            ]);
        }

        // Create 3 struggle cards (low ease factor)
        for ($i = 1; $i <= 3; $i++) {
            Flashcard::create([
                'user_id' => $user->id,
                'deck' => 'Vocab',
                'front' => "Struggle Card {$i}",
                'back' => "Answer {$i}",
                'repetitions' => 2,
                'interval_days' => 1,
                'ease_factor' => 1.80,
                'next_review_at' => Carbon::now()->addDays(5),
            ]);
        }

        // Create 4 new cards (repetitions = 0)
        for ($i = 1; $i <= 4; $i++) {
            Flashcard::create([
                'user_id' => $user->id,
                'deck' => 'Vocab',
                'front' => "New Card {$i}",
                'back' => "Answer {$i}",
                'repetitions' => 0,
                'interval_days' => 1,
                'ease_factor' => 2.50,
                'next_review_at' => Carbon::now(),
            ]);
        }

        // Test Struggle mode with batch size 2
        Volt::test('flashcard.index')
            ->set('launcherDeck', 'Vocab')
            ->set('reviewMode', 'struggle')
            ->set('reviewBatchSize', 2)
            ->call('startConfiguredStudySession')
            ->assertSet('isStudying', true)
            ->assertCount('studyCards', 2);

        // Test New cards mode with batch size 10 (should get all 4 new cards)
        Volt::test('flashcard.index')
            ->set('launcherDeck', 'Vocab')
            ->set('reviewMode', 'new')
            ->set('reviewBatchSize', 10)
            ->call('startConfiguredStudySession')
            ->assertSet('isStudying', true)
            ->assertCount('studyCards', 4);
    }

    public function test_rating_card_updates_session_stats_and_awards_xp(): void
    {
        $user = User::factory()->create(['xp' => 10]);
        $this->actingAs($user);

        $card = Flashcard::create([
            'user_id' => $user->id,
            'deck' => 'Vocab',
            'front' => 'Test Front',
            'back' => 'Test Back',
            'repetitions' => 0,
            'interval_days' => 1,
            'ease_factor' => 2.50,
            'next_review_at' => Carbon::now()->subHour(),
        ]);

        Volt::test('flashcard.index')
            ->set('launcherDeck', 'all')
            ->set('reviewMode', 'due')
            ->set('reviewBatchSize', 10)
            ->call('startConfiguredStudySession')
            ->assertSet('isStudying', true)
            ->call('rateCard', 'good')
            ->assertSet('studiedCount', 1)
            ->assertSet('studyComplete', true)
            ->assertDispatched('trigger-confetti');

        $this->assertEquals(5, $user->fresh()->pending_xp);
        $card->refresh();
        $this->assertEquals(1, $card->repetitions);
    }

    public function test_delete_deck_removes_all_its_subcards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Flashcard::create([
            'user_id' => $user->id,
            'deck' => 'To Delete',
            'front' => 'Card 1',
            'back' => 'Back 1',
        ]);
        Flashcard::create([
            'user_id' => $user->id,
            'deck' => 'To Delete',
            'front' => 'Card 2',
            'back' => 'Back 2',
        ]);
        Flashcard::create([
            'user_id' => $user->id,
            'deck' => 'Keep',
            'front' => 'Card 3',
            'back' => 'Back 3',
        ]);

        Volt::test('flashcard.index')
            ->call('deleteDeck', 'To Delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('flashcards', ['deck' => 'To Delete']);
        $this->assertDatabaseHas('flashcards', ['deck' => 'Keep']);
    }

    public function test_deck_view_navigation_and_stats(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Flashcard::create([
            'user_id' => $user->id,
            'deck' => 'Math',
            'front' => 'Pythagoras',
            'back' => 'a^2 + b^2 = c^2',
            'repetitions' => 4, // Mastered
            'interval_days' => 10,
            'ease_factor' => 2.50,
            'next_review_at' => Carbon::now()->addDays(5),
        ]);

        Volt::test('flashcard.index')
            ->assertSet('currentView', 'decks')
            ->call('openDeckView', 'Math')
            ->assertSet('currentView', 'cards')
            ->assertSet('activeDeck', 'Math')
            ->call('backToDecks')
            ->assertSet('currentView', 'decks')
            ->assertSet('activeDeck', null);
    }

    public function test_continuing_review_from_completed_study_closes_study_modal_and_opens_launcher(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Flashcard::create([
            'user_id' => $user->id,
            'deck' => 'Vocab',
            'front' => 'Apple',
            'back' => 'Apel',
            'repetitions' => 0,
            'interval_days' => 1,
            'ease_factor' => 2.50,
            'next_review_at' => Carbon::now()->subHour(),
        ]);

        Volt::test('flashcard.index')
            ->set('launcherDeck', 'Vocab')
            ->set('reviewMode', 'due')
            ->call('startConfiguredStudySession')
            ->assertSet('isStudying', true)
            ->call('rateCard', 'easy')
            ->assertSet('studyComplete', true)
            // Click "Lanjut Review / Mode Lain"
            ->call('openReviewLauncher', 'Vocab')
            ->assertSet('isStudying', false)
            ->assertSet('studyComplete', false)
            ->assertSet('showReviewLauncher', true)
            ->assertSet('launcherDeck', 'Vocab');
    }
}

