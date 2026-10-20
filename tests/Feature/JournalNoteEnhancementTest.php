<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JournalNoteEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_autosave_creates_draft_and_dispatches_event(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('journal.index')
            ->set('title', 'Draft Otomatis')
            ->set('content', '<p>Catatan penting yang belum sempat ditekan tombol simpan #ide</p>')
            ->call('autoSaveJournal')
            ->assertDispatched('journal-autosaved');

        $this->assertDatabaseHas('journals', [
            'user_id' => $user->id,
            'title' => 'Draft Otomatis',
        ]);

        $createdJournal = Journal::where('title', 'Draft Otomatis')->first();
        $this->assertNotNull($createdJournal);
        $this->assertEquals($createdJournal->id, $component->get('editingJournalId'));
        $this->assertContains('ide', $createdJournal->tags);
    }

    public function test_autosave_updates_existing_draft_without_creating_duplicate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $journal = Journal::create([
            'user_id' => $user->id,
            'title' => 'Draft Awal',
            'content' => 'Konten pertama',
            'mood' => '💡',
        ]);

        Volt::test('journal.index')
            ->call('editJournal', $journal->id)
            ->set('title', 'Draft Setelah Diedit')
            ->set('content', 'Konten revisi otomatis')
            ->call('autoSaveJournal')
            ->assertDispatched('journal-autosaved');

        $this->assertEquals(1, Journal::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('journals', [
            'id' => $journal->id,
            'title' => 'Draft Setelah Diedit',
            'content' => 'Konten revisi otomatis',
        ]);
    }

    public function test_open_add_journal_modal_event_opens_modal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('journal.index')
            ->assertSet('showCreateModal', false)
            ->dispatch('open-add-journal-modal')
            ->assertSet('showCreateModal', true);
    }

    public function test_social_and_flashcard_listen_to_open_modal_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test social open-create-feed-modal
        Volt::test('social')
            ->assertSet('showCreateTopicModal', false)
            ->dispatch('open-create-feed-modal')
            ->assertSet('showCreateTopicModal', true);

        // Test flashcard open-create-flashcard-modal
        Volt::test('flashcard.index')
            ->assertSet('showCardModal', false)
            ->dispatch('open-create-flashcard-modal')
            ->assertSet('showCardModal', true);
    }

    public function test_tag_addition_and_removal_triggers_autosave(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $journal = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Ber-tag',
            'content' => 'Konten catatan',
            'tags' => ['awal'],
        ]);

        Volt::test('journal.index')
            ->call('editJournal', $journal->id)
            ->set('newTag', 'web-build')
            ->call('addTag')
            ->assertDispatched('journal-autosaved');

        $this->assertDatabaseHas('journals', [
            'id' => $journal->id,
            'tags' => json_encode(['awal', 'web-build']),
        ]);

        Volt::test('journal.index')
            ->call('editJournal', $journal->id)
            ->call('removeTag', 0)
            ->assertDispatched('journal-autosaved');

        $this->assertDatabaseHas('journals', [
            'id' => $journal->id,
            'tags' => json_encode(['web-build']),
        ]);
    }

    public function test_note_layout_header_and_content_are_aligned_with_same_max_width(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/journal');
        $response->assertOk();

        // Check header container has max-w-5xl mx-auto
        $response->assertSee('max-w-5xl mx-auto py-3 flex flex-col sm:flex-row justify-between', false);
        // Check main note content has max-w-5xl mx-auto
        $response->assertSee('max-w-5xl mx-auto space-y-5', false);

        // Check finance container has max-w-5xl mx-auto when activeTab is finance
        \Livewire\Volt\Volt::test('journal.index')
            ->set('activeTab', 'finance')
            ->assertSee('space-y-8 max-w-5xl mx-auto', false);
    }

    public function test_note_editor_enhancements_markup_and_features(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/journal');
        $response->assertOk();

        // 1. Z-Index and Zen mode condition on modal top bar
        $response->assertSee('x-show="!zenMode" class="flex justify-between items-center px-4 sm:px-6 py-2 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shrink-0 z-40"', false);

        // 2. Tags relocated to top header
        $response->assertSee('Top Bar Tags Management (Clean, Compact Header Strip)', false);
        $response->assertSee('x-ref="headerTagInput"', false);

        // 3. Enter on title focuses editor
        $response->assertSee('@keydown.enter.prevent="focusEditor()"', false);

        // 4. Toolbar has overflow-visible and compact height
        $response->assertSee('class="hidden sm:flex items-center gap-1 px-4 sm:px-6 py-1.5 bg-gray-50/90 dark:bg-gray-800/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-800 text-gray-700 dark:text-gray-300 shrink-0 overflow-visible z-30"', false);

        // 5. Scoped CSS for lists and headings
        $response->assertSee('[contenteditable="true"] ul', false);
        $response->assertSee('list-style-type: disc !important;', false);
        $response->assertSee('[contenteditable="true"] ol', false);
        $response->assertSee('list-style-type: decimal !important;', false);
        $response->assertSee('[contenteditable="true"] h2', false);

        // 6. Clean toolbar titles with dual Mac (⌘) and PC (Ctrl) shortcuts
        $response->assertSee('title="Huruf Tebal (⌘B / Ctrl+B)"', false);
        $response->assertSee('title="Huruf Miring (⌘I / Ctrl+I)"', false);
        $response->assertSee('title="Coret Teks (⌘⇧X / Alt+Shift+5)"', false);
        $response->assertSee('title="Stabilo Highlight (⌘⇧H / Alt+H)"', false);
        $response->assertSee('title="Daftar Centang Checklist (⌘⇧C / Ctrl+Shift+C)"', false);
        $response->assertSee('title="Daftar Poin Bullet (⌘⇧8 / Ctrl+Shift+8)"', false);
        $response->assertSee('title="Daftar Nomor 1, 2, 3 (⌘⇧7 / Ctrl+Shift+7)"', false);
        $response->assertSee('title="Kutipan Quote (⌘⇧Q / Ctrl+Shift+Q)"', false);
        $response->assertSee('title="Tabel Grid Dinamis (/table)"', false);
        $response->assertSee('title="Garis Pemisah Divider (/divider)"', false);

        // 7. JS helpers present in component
        $response->assertSee('focusEditor()', false);
        $response->assertSee('insertQuote()', false);
        $response->assertSee('insertHeading(tag)', false);
        $response->assertSee('insertTable()', false);
    }

    public function test_mac_and_windows_keyboard_shortcuts_compatibility_in_editor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/journal');
        $response->assertOk();

        // 1. Unified modifier for Mac (metaKey) and PC (ctrlKey)
        $response->assertSee('const isMod = e.ctrlKey || e.metaKey;', false);

        // 2. Bold (⌘B and Ctrl+B)
        $response->assertSee("isMod && !isShift && !isAlt && (key === 'b' || code === 'KeyB')", false);
        $response->assertSee("this.exec('bold')", false);

        // 3. Italic (⌘I and Ctrl+I)
        $response->assertSee("isMod && !isShift && !isAlt && (key === 'i' || code === 'KeyI')", false);
        $response->assertSee("this.exec('italic')", false);

        // 4. Underline (⌘U and Ctrl+U)
        $response->assertSee("isMod && !isShift && !isAlt && (key === 'u' || code === 'KeyU')", false);
        $response->assertSee("this.exec('underline')", false);

        // 5. Strikethrough (⌘⇧X or Ctrl+Shift+X or ⌘⇧5 or Alt+Shift+5)
        $response->assertSee("this.exec('strikeThrough')", false);

        // 6. Highlight (⌘⇧H or Ctrl+Shift+H or Alt+H)
        $response->assertSee("this.toggleHighlight()", false);

        // 7. Checklist (⌘⇧C or Ctrl+Shift+C)
        $response->assertSee("this.insertChecklist()", false);

        // 8. Lists handling shift symbols (* for 8, & for 7)
        $response->assertSee("this.exec('insertUnorderedList')", false);
        $response->assertSee("this.exec('insertOrderedList')", false);

        // 9. Undo & Redo (⌘Z, ⌘⇧Z, Ctrl+Y)
        $response->assertSee("this.exec('undo')", false);
        $response->assertSee("this.exec('redo')", false);

        // 10. Window-level shortcut handler for modal overlay
        $response->assertSee('@keydown.window="handleWindowKeydown($event)"', false);
        $response->assertSee('handleWindowKeydown(e)', false);
    }

    public function test_stabilo_highlight_toggle_and_card_read_more_features(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a note with multi-line content (e.g. list items)
        $journal = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Daftar Pendek',
            'content' => '<p>Target hari ini:</p><ul><li>Item 1</li><li>Item 2</li><li>Item 3</li><li>Item 4</li></ul>',
            'mood' => '📓',
        ]);

        $response = $this->get('/journal');
        $response->assertOk();

        // 1. Stabilo toggle mechanism assertions
        $response->assertSee('marksToUnwrap', false);
        $response->assertSee('newRange.selectNodeContents(mark)', false);
        $response->assertSee("querySelectorAll('mark mark')", false);

        // 2. Card Read More (Baca selengkapnya) dynamic overflow assertions
        $response->assertSee('hasOverflow', false);
        $response->assertSee('checkOverflow()', false);
        $response->assertSee('scrollHeight > this.$refs.contentEl.clientHeight', false);
        $response->assertSee('x-ref="contentEl"', false);
    }

    public function test_journal_docs_layout_sticky_header_and_bubble_menu_ux(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/journal');
        $response->assertOk();

        // 1. Unified Mental Model: Clear Back Navigation with Autosave, no ambiguous top-right X button
        $response->assertSee('title="Kembali ke Daftar Catatan (Otomatis Tersimpan)"', false);
        $response->assertDontSee('title="Tutup (Otomatis Tersimpan)"', false);

        // 2. Hick's Law: Rare actions grouped into More Options [...] dropdown
        $response->assertSee('openMoreMenu', false);
        $response->assertSee('title="Opsi Dokumen & Tampilan Lainnya"', false);
        $response->assertSee('Buka Selalu Mode Zen', false);
        $response->assertSee('Cetak / Simpan PDF', false);

        // 3. Header Sticky Title Pattern: Title only visible when isScrolled is true
        $response->assertSee('x-show="isScrolled"', false);
        $response->assertSee('onCanvasScroll($event)', false);

        // 4. Google Docs Paper Layout & Independent Canvas Scrolling
        $response->assertSee('x-ref="canvasViewport"', false);
        $response->assertSee('readingTimeText', false);
        $response->assertSee('wordCount', false);
        $response->assertSee('charCount', false);

        // 5. Contextual Floating Bubble Menu
        $response->assertSee('showBubbleMenu', false);
        $response->assertSee('bubblePos', false);
        $response->assertSee('applyBubbleFormat', false);
        $response->assertSee('applyBubbleHighlight', false);
        $response->assertSee('applyBubbleHeading', false);

        // 6. Enhanced Slash Command Aliases (/checklist, /tabel, /h1)
        $response->assertSee("id: 'checklist'", false);
        $response->assertSee("id: 'tabel'", false);
        $response->assertSee("id: 'h1'", false);
    }

    public function test_connected_note_links_and_responsive_editor_improvements(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create target note
        $targetNote = Journal::create([
            'user_id' => $user->id,
            'title' => 'Resep Opor Ayam Gurih',
            'content' => 'Bumbu opor ayam: bawang merah, bawang putih, santan kental.',
            'mood' => '🍲',
        ]);

        // AutoSave a note containing an embedded data-note-link
        $sourceNoteContent = '<p>Hari ini kita memasak <a href="javascript:void(0)" data-note-id="' . $targetNote->id . '" data-note-title="Resep Opor Ayam Gurih" class="journal-note-link">🔗 Resep Opor Ayam Gurih</a> bersama keluarga.</p>';

        $component = Volt::test('journal.index')
            ->set('title', 'Menu Makan Malam')
            ->set('content', $sourceNoteContent)
            ->call('autoSaveJournal');

        $sourceNote = Journal::where('title', 'Menu Makan Malam')->first();
        $this->assertNotNull($sourceNote);

        // Verify journal_links was automatically synced
        $this->assertDatabaseHas('journal_links', [
            'source_journal_id' => $sourceNote->id,
            'target_journal_id' => $targetNote->id,
        ]);

        $response = $this->get('/journal');
        $response->assertOk();

        // 1. Note links UI & popups
        $response->assertSee('showNoteLinkModal', false);
        $response->assertSee('showNotePreviewPopup', false);
        $response->assertSee('journal-note-link', false);
        $response->assertSee('openConnectedNote', false);
        $response->assertSee('unlinkNote', false);

        // 2. Global Escape key handling
        $response->assertSee('@keydown.escape.window="handleEscapeKey()"', false);

        // 3. Sticky table edit bar with deleteTableCol
        $response->assertSee('id="table-controls-bar"', false);
        $response->assertSee('deleteTableCol()', false);
        $response->assertSee('addTableCol()', false);

        // 4. Stabilo undo snapshots
        $response->assertSee('customUndo()', false);
        $response->assertSee('saveSnapshot()', false);
        $response->assertSee('undoStack', false);

        // 5. Mini note instant prefetch and cache markup
        $response->assertSee('handleEditorHover', false);
        $response->assertSee('noteCache', false);
        $response->assertSee('activeNotePreview.isLoading', false);
    }

    public function test_mini_note_on_demand_preview_endpoint_and_instant_cache(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $note = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Rahasia Masakan',
            'content' => '<p>Langkah 1: Siapkan rempah-rempah pilihan dan santan segar.</p>',
            'tags' => ['Kuliner', 'Resep'],
            'mood' => '🍳',
        ]);

        $comp = Volt::test('journal.index');

        // Test on-demand getNotePreview endpoint
        $preview = $comp->instance()->getNotePreview($note->id);
        $this->assertEquals($note->id, $preview['id']);
        $this->assertEquals('Catatan Rahasia Masakan', $preview['title']);
        $this->assertStringContainsString('Langkah 1: Siapkan rempah-rempah pilihan', $preview['content_html']);
        $this->assertContains('Kuliner', $preview['tags']);
        $this->assertGreaterThan(0, $preview['word_count']);

        // Test non-existent note returns graceful fallback
        $missingPreview = $comp->instance()->getNotePreview(999999);
        $this->assertEquals('Catatan Tidak Ditemukan', $missingPreview['title']);
        $this->assertStringContainsString('tidak ditemukan', $missingPreview['snippet']);
    }
}

