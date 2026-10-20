<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Journal;
use App\Services\EmailMagicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

class EmailMagicAndNoteImprovementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_magic_heuristic_parses_email_with_links_and_inserts_tasks()
    {
        $user = User::factory()->create();

        $emailText = "Halo tim, tolong siapkan revisi presentasi sebelum meeting di https://meet.google.com/abc-xyz jam 3 sore. Link dokumen ada di https://docs.google.com/presentation/d/123456. Ini sangat penting dan urgent!";

        $tasks = EmailMagicService::parseEmailToTasks($emailText, $user->id, now()->toDateString());

        $this->assertNotEmpty($tasks);
        $this->assertGreaterThanOrEqual(1, count($tasks));
        $this->assertStringContainsString('https://meet.google.com/abc-xyz', $tasks[0]['description']);
        $this->assertContains('https://meet.google.com/abc-xyz', $tasks[0]['extracted_links']);

        // Insert into database
        $inserted = EmailMagicService::insertTasks($tasks, $user->id, now()->toDateString());
        $this->assertGreaterThanOrEqual(1, $inserted);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'is_completed' => false,
        ]);
    }

    public function test_tasks_view_can_open_and_process_email_magic()
    {
        $user = User::factory()->create();

        $emailText = "Tolong bayar tagihan invoice di https://finance.company.com/inv-900 dan periksa laporan mingguan.";

        Volt::actingAs($user)
            ->test('tasks')
            ->call('openEmailMagicModal')
            ->assertSet('showEmailMagicModal', true)
            ->set('emailMagicInput', $emailText)
            ->call('processEmailMagic')
            ->call('confirmInsertEmailTasks')
            ->assertSet('showEmailMagicModal', false);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
        ]);
    }

    public function test_journal_sort_and_export_notes()
    {
        $user = User::factory()->create();
        Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Alpha',
            'content' => '<p>Konten Alpha</p>',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Beta',
            'content' => '<p>Konten Beta</p>',
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $component = Volt::actingAs($user)
            ->test('journal.index')
            ->set('noteSortBy', 'title_asc');

        $grouped = $component->get('groupedJournals');
        $this->assertNotEmpty($grouped);

        // Test export download
        $response = $component->call('exportNotes');
        $response->assertFileDownloaded('atoma-notes-backup-' . date('Y-m-d') . '.json');
    }
}
