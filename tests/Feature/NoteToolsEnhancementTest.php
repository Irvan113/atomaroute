<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NoteToolsEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_convert_checklist_items_to_daily_tasks()
    {
        $user = User::factory()->create();

        $content = '
            <h2>Target Kerja Hari Ini</h2>
            <div class="flex items-center gap-2.5 my-1.5 journal-checklist-item" style="clear:both;">
                <input type="checkbox" class="w-4 h-4">
                <span>Riset Kompetitor dan Benchmark</span>
            </div>
            <div class="flex items-center gap-2.5 my-1.5 journal-checklist-item" style="clear:both;">
                <input type="checkbox" class="w-4 h-4" checked="checked">
                <span>Tugas Yang Sudah Beres</span>
            </div>
            <div class="flex items-center gap-2.5 my-1.5 journal-checklist-item" style="clear:both;">
                <input type="checkbox" class="w-4 h-4">
                <span>Kirim Dokumen Proposal ke Klien</span>
            </div>
        ';

        $journal = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Meeting Pagi',
            'content' => $content,
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');
        $comp->call('convertChecklistToTasks', $journal->id);

        $comp->assertDispatched('toast', function ($name, $params) {
            return str_contains($params['message'] ?? '', 'Berhasil membuat 2 Tugas Harian');
        });
        $comp->assertDispatched('trigger-confetti');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Riset Kompetitor dan Benchmark',
            'category' => 'Note',
            'is_completed' => false,
        ]);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Kirim Dokumen Proposal ke Klien',
            'category' => 'Note',
            'is_completed' => false,
        ]);

        // Already checked task should NOT be created
        $this->assertDatabaseMissing('tasks', [
            'user_id' => $user->id,
            'title' => 'Tugas Yang Sudah Beres',
        ]);
    }

    public function test_convert_checklist_from_active_editing_session()
    {
        $user = User::factory()->create();

        $comp = Volt::actingAs($user)->test('journal.index');
        $comp->call('openCreateModal');
        $comp->set('title', 'Catatan Belajar');
        $comp->set('content', '
            <p>Poin penting:</p>
            - [ ] Latihan 20 Soal Algoritma
            - [ ] Review Flashcard Spaced Repetition
        ');

        $comp->call('convertChecklistToTasks');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Latihan 20 Soal Algoritma',
        ]);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Review Flashcard Spaced Repetition',
        ]);
    }

    public function test_format_content_supports_rich_formatting_elements()
    {
        $user = User::factory()->create();
        $comp = Volt::actingAs($user)->test('journal.index');

        $richHtml = '
            <h2>Judul</h2>
            <mark class="journal-highlight">Teks penting</mark>
            <blockquote>Kutipan inspiratif</blockquote>
            <pre><code>let x = 10;</code></pre>
            <hr>
            <div class="journal-callout callout-idea">
                <span>💡</span>
                <div>Ide cemerlang</div>
            </div>
        ';

        $formatted = $comp->instance()->formatContent($richHtml);

        $this->assertStringContainsString('<mark class="journal-highlight">Teks penting</mark>', $formatted);
        $this->assertStringContainsString('<blockquote>Kutipan inspiratif</blockquote>', $formatted);
        $this->assertStringContainsString('<pre><code>let x = 10;</code></pre>', $formatted);
        $this->assertStringContainsString('<hr>', $formatted);
        $this->assertStringContainsString('callout-idea', $formatted);
    }

    public function test_desktop_quick_notes_switcher_and_note_management()
    {
        $user = User::factory()->create();

        $note1 = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Pertama',
            'content' => 'Ini isi catatan pertama yang penting',
            'tags' => ['Riset'],
        ]);

        $note2 = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Kedua',
            'content' => 'Ini isi catatan kedua untuk referensi',
            'tags' => ['Ide'],
        ]);

        $comp = Volt::actingAs($user)->test('journal.index');

        // Test allNotesList computed property
        $notesList = $comp->get('allNotesList');
        $this->assertCount(2, $notesList);
        $this->assertEquals('Catatan Kedua', $notesList[0]['title']);

        // Test opening note 1 and switching to note 2
        $comp->call('editJournal', $note1->id);
        $comp->assertSet('editingJournalId', $note1->id)
            ->assertSet('title', 'Catatan Pertama');

        // Edit title and switch note (should auto-save note 1 and load note 2)
        $comp->set('title', 'Catatan Pertama Terupdate');
        $comp->call('switchNote', $note2->id);

        $comp->assertSet('editingJournalId', $note2->id)
            ->assertSet('title', 'Catatan Kedua');

        $this->assertDatabaseHas('journals', [
            'id' => $note1->id,
            'title' => 'Catatan Pertama Terupdate',
        ]);

        // Test creating new note from sidebar
        $comp->call('createNewNoteFromSidebar');
        $comp->assertSet('editingJournalId', null)
            ->assertSet('title', '');
    }

    public function test_format_content_cleans_ai_font_tags_and_broken_attributes()
    {
        $user = User::factory()->create();
        $comp = Volt::actingAs($user)->test('journal.index');

        // Emulate typical clipboard payload pasted from ChatGPT/AI
        $dirtyAiHtml = '<font color="#101010" face="system-ui, -apple-system, sans-serif"><p>Hari 1: Latihan Renang #workout #training</p></font>';
        
        $sanitized = $comp->instance()->sanitizeRichContent($dirtyAiHtml);
        $this->assertStringNotContainsString('<font', $sanitized);
        $this->assertStringNotContainsString('face="system-ui', $sanitized);
        $this->assertStringNotContainsString('color="#101010"', $sanitized);
        $this->assertStringContainsString('Hari 1: Latihan Renang #workout #training', $sanitized);

        // Test formatContent does not turn hex code into hashtag nor corrupt attributes
        $formatted = $comp->instance()->formatContent($dirtyAiHtml);
        $this->assertStringNotContainsString('<font', $formatted);
        $this->assertStringNotContainsString('face="system-ui', $formatted);
        $this->assertStringNotContainsString('#101010" face=', $formatted);
        $this->assertStringContainsString('#workout', $formatted);
        $this->assertStringContainsString('#training', $formatted);

        // Also test dirty residual leftover text from previous corrupted pastes
        $corruptedFragment = '<p>1. #101010" face="system-ui, -apple-system, sans-serif">Tugas harian</p>';
        $cleanedFragment = $comp->instance()->formatContent($corruptedFragment);
        $this->assertStringNotContainsString('#101010" face=', $cleanedFragment);
        $this->assertStringContainsString('Tugas harian', $cleanedFragment);
    }
}
