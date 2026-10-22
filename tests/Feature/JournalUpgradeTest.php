<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JournalUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_can_be_created_with_reference_url_tags_and_wikilinks(): void
    {
        $user = User::factory()->create();

        // Create an initial target journal note
        $targetJournal = Journal::create([
            'user_id' => $user->id,
            'title' => 'Belajar Premiere Pro',
            'content' => 'Dasar-dasar cutting video di Premiere Pro',
            'mood' => '💡'
        ]);

        $this->actingAs($user);

        Volt::test('journal.index')
            ->set('title', 'Trik Color Grading')
            ->set('content', 'Mempelajari cara grading lumetri color. Sangat berkaitan dengan [[Belajar Premiere Pro]] #video-editing')
            ->set('reference_url', 'https://youtube.com/watch?v=example123')
            ->set('mood', '🔥')
            ->call('saveJournal')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('journals', [
            'user_id' => $user->id,
            'title' => 'Trik Color Grading',
            'reference_url' => 'https://youtube.com/watch?v=example123',
            'mood' => '🔥'
        ]);

        $newJournal = Journal::where('title', 'Trik Color Grading')->first();
        $this->assertNotNull($newJournal);
        $this->assertContains('video-editing', $newJournal->tags);

        // Assert WikiLink relationship was recorded in DB
        $this->assertDatabaseHas('journal_links', [
            'source_journal_id' => $newJournal->id,
            'target_journal_id' => $targetJournal->id,
        ]);
    }

    public function test_get_notes_list_json_returns_latest_notes_immediately(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $j1 = Journal::create([
            'user_id' => $user->id,
            'title' => 'RPL Pertemuan 3',
            'content' => 'Materi diagram UML class diagram dan sequence diagram',
            'mood' => '💡'
        ]);

        $test = Volt::test('journal.index');
        $notes = $test->instance()->getNotesListJson();

        $this->assertIsArray($notes);
        $this->assertEquals($j1->id, $notes[0]['id']);
        $this->assertEquals('RPL Pertemuan 3', $notes[0]['title']);
    }

    public function test_editor_markup_contains_drive_link_and_tab_indentation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/journal');
        $response->assertStatus(200);
        $response->assertSee('/drive');
        $response->assertSee('Google Drive / PDF');
        $response->assertSee('promptInsertDriveLink');
        $response->assertSee('insertDriveLinkCard');
        $response->assertSee('handleTabKey');
        $response->assertSee('.journal-tab-space');
        $response->assertSee('focusEditor()');
    }

    public function test_sanitize_rich_content_preserves_tab_spaces_and_adds_tabindex_minus_one_to_links(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Volt::test('journal.index');
        $htmlWithLink = '<p>Halo ini teks <a href="javascript:void(0)" data-note-id="5" data-note-title="Catatan RPL" class="journal-note-link">Catatan RPL</a> dan ada <span class="journal-tab-space" style="display:inline-block; width:2.5rem;" contenteditable="false">&emsp;&emsp;</span>jarak lebar.</p>';
        
        $sanitized = $test->instance()->sanitizeRichContent($htmlWithLink);

        // Assert tabindex="-1" is injected into note link
        $this->assertStringContainsString('tabindex="-1"', $sanitized);
        $this->assertStringContainsString('journal-note-link', $sanitized);

        // Assert .journal-tab-space and width:2.5rem are preserved
        $this->assertStringContainsString('journal-tab-space', $sanitized);
        $this->assertStringContainsString('width:2.5rem', $sanitized);
    }

    public function test_broken_html_in_journal_content_does_not_break_root_element(): void
    {
        config(['app.debug' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);

        // Note with rogue closing div and unclosed tags
        Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Rusak',
            'content' => '<p>Teks pertama</p></div><p>Teks kedua</p><div>Unclosed',
            'mood' => '📝',
        ]);

        $response = $this->get('/journal');
        $response->assertStatus(200);
        $response->assertSee('Catatan Rusak');
    }
}
