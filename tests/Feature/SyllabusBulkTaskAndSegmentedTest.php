<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\SubTask;
use App\Models\Task;
use App\Models\User;
use App\Services\AiSkillPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyllabusBulkTaskAndSegmentedTest extends TestCase
{
    use RefreshDatabase;

    protected string $sampleSyllabus = <<<'EOD'
## BAGIAN 1: Rekap Jadwal Latihan Fisik Mingguan (Versi Final Kos-Friendly)

Target Utama (Oktober 2028): **10K Sub-40**, **Pace 4–5 di Zona 2**, **Renang Gaya Dada 50m (30–40 dtk)**, serta **Fisik Proporsional (Hypertrophy + Abs)**.

### A. Matriks Mingguan Triathlon + Otot + Abs + GtG

| Hari | Subuh / Pagi (Pasca-Subuh) | Siang / Jeda Kuliah | Sore (15.45 – 17.00) | Status GtG & Cuci Baju |
| --- | --- | --- | --- | --- |
| **Senin** *(Kuliah 08.50–17.10)* | **05.00–05.35:** **Lari #1** (Zona 2 Murni, 30 mnt) | Kuliah 4 Matkul (Sisipkan GtG di kos pagi & sore) | Pulang kuliah 17.10, dekompresi otot | **GtG Hari 1** (4 set tersebar) |
| **Selasa** *(Kosong Kuliah)* | **08.00–08.45:** **Renang #1** (Teknik & Sprint 50m Dada) | Deep Work Coding & Istirahat Siang | **16.00–17.00:** **Workout Otot A (Upper)** + **Abs #1** | **GtG Hari 2** (2 set ringan) + **Cuci Pakaian #1** |
| **Rabu** *(Kuliah 07.00–17.10)* | **05.00–05.30:** **Lari #2** (Zona 2 Recovery, 25–30 mnt) | Jeda 08.50–10.40 (Makan ke-2 & istirahat) | Pulang kuliah 17.10, pemulihan pasif | **GtG Hari 3** (4 set tersebar) |
| **Kamis** *(Kuliah 07.00 & 15.30)* | **05.00–05.40:** **Sepeda #1** (Zona 2 Kadensi, 40 mnt) | **Jeda Kos (09.00–15.00):** **Abs #2 (10 mnt)** + Coding | **15.30–17.10:** Kuliah Sore | **GtG Hari 4** (4 set tersebar) + **Cuci Pakaian #2** |
| **Jumat** *(Kosong Kuliah)* | **05.15–06.00:** **Sepeda #2** (Aerobik Santai, 45 mnt) | Deep Work Coding, Sholat Jumat, Istirahat | **16.00–16.45:** **Renang #2** (Endurans & Hidroterapi) | **GtG Hari 5** (4 set tersebar) |
| **Sabtu** *(Kosong Kuliah)* | **05.15–06.00:** **Lari #3** (Long Run / Progresif, 45 mnt) | Santai, Bikin Konten, Evaluasi Mingguan | **15.30–16.30:** **Workout Otot B (Lower & Tendon)** + **Abs #3** | *Libur GtG* + **Cuci Pakaian #3** |
| **Minggu** *(Full Rest Day)* | **Istirahat Total** (Bangun tetap pagi tanpa beban fisik) | Pemulihan jaringan ikat, jalan santai, persiapan pekan baru | Rileks penuh | *Libur GtG* |

## BAGIAN 2: Arsitektur Harian "90-Day Dopamine Detox & Better Lifestyle"

### Tabel Urutan Rutinitas Harian (Jam demi Jam)

| Jam | Kebiasaan / Aktivitas | Kategori & Aturan Eksekusi |
| --- | --- | --- |
| **04.00 – 04.30** | **1. Bangun Tidur + 30 Menit Off-Screen**<br><br>**2. Beberes Kamar (Merapikan Kasur)**<br><br>**3. Sholat Subuh (Saat Masuk Waktu)** | Zero Digital Input. |
| **04.30 – 04.50** | **4. Hafal Al-Qur'an (15–20 Menit)** | Gunakan Mushaf fisik |
| **04.50 – 04.55** | **5. Tes Koran / Pauli-Kraepelin (5 Menit)** | Pemantik kognitif |
| **05.00 – 05.45** | **6. Workout Pagi (Lari / Sepeda)** + **GtG Set 1** | Minum 1 sdm Raw Honey |
| **05.45 – 06.45** | **7. Mandi, Sarapan Oatmeal/Protein + Minum Vitamin**<br><br>**8. Nyuci Pakaian (Selasa, Kamis, Sabtu - 15 mnt)** | Vitamin dikonsumsi |
| **06.45 – 08.40** | **9. Build Web / Belajar Programming (Sesi Deep Work)** | Deep work coding |
| **08.50 – 17.10** | **10. Kuliah / Akademik + Sholat Dzuhur & Ashar Tepat Waktu** + **GtG Set 2 & 3** | Kuliah |
| **17.15 – 18.00** | **11. Bikin Konten (45 Menit)** + **GtG Set 4** | Creative output |
| **18.00 – 18.30** | **Sholat Maghrib + Makan Malam Tinggi Protein** | Pemulihan otot |
| **18.30 – 19.30** | **12. Controlled Gaming: Genshin Daily (15 mnt) + Compe EA FC (45 mnt)** | Jendela Dopamin |
| **19.30 – 20.00** | **Sholat Isya + Persiapan Barang Besok** | Menurunkan ritme |
| **20.00 – 20.30** | **13. Journaling (15 Menit) + Layar Redup** | Evaluasi hari ini |
| **20.30 – 04.00** | **14. Tidur Malam Berkualitas (7 – 7,5 Jam)** | Kamar gelap |
EOD;

    public function test_it_parses_syllabus_habits_and_weekly_training_matrix()
    {
        $parsed = AiSkillPlannerService::parseSyllabusContent($this->sampleSyllabus);

        $this->assertNotEmpty($parsed['habits']);
        $this->assertGreaterThanOrEqual(14, count($parsed['habits']));

        // Check specific habits
        $habitTitles = array_column($parsed['habits'], 'title');
        $this->assertTrue(collect($habitTitles)->contains(fn($t) => str_contains($t, 'Bangun Tidur')));
        $this->assertTrue(collect($habitTitles)->contains(fn($t) => str_contains($t, 'Hafal Al-Qur\'an')));
        $this->assertTrue(collect($habitTitles)->contains(fn($t) => str_contains($t, 'Tes Koran')));
        $this->assertTrue(collect($habitTitles)->contains(fn($t) => str_contains($t, 'Build Web')));
        $this->assertTrue(collect($habitTitles)->contains(fn($t) => str_contains($t, 'Controlled Gaming')));

        // Check weekly matrix
        $this->assertCount(7, $parsed['weekly_matrix']);
        $this->assertEquals('Senin', $parsed['weekly_matrix'][1]['day_name']);
        $this->assertStringContainsString('Lari #1', $parsed['weekly_matrix'][1]['pagi']);
        $this->assertStringContainsString('Renang #1', $parsed['weekly_matrix'][2]['pagi']);
        $this->assertStringContainsString('Workout Otot A', $parsed['weekly_matrix'][2]['sore']);
        $this->assertTrue($parsed['weekly_matrix'][7]['is_rest']);
    }

    public function test_it_synthesizes_14_bulk_subtasks_per_day_from_syllabus()
    {
        $user = User::factory()->create();

        // Assume start on a Monday (e.g. 2026-10-19 is Monday)
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => '90 Days Challenge Dopamine Detox',
            'prompt' => 'Goal 90 hari detox dan triathlon',
            'target_days' => 90,
            'start_date' => '2026-10-19', // Monday
            'mode' => 'systematic',
            'training_days' => ['1', '2', '3', '4', '5', '6', '7'],
            'interview_context' => [
                'syllabus_text' => $this->sampleSyllabus,
            ],
            'setup_status' => 'active',
            'current_day' => 1,
        ]);

        $service = new AiSkillPlannerService();
        $roadmap = $service->generateFallbackRoadmap($skill);

        $this->assertCount(90, $roadmap);

        // Day 1 is Monday (ISO 1)
        $day1 = $roadmap[0];
        $this->assertStringContainsString('Senin', $day1['title']);
        $this->assertStringContainsString('Lari #1', $day1['title']);
        $this->assertGreaterThanOrEqual(14, count($day1['sub_tasks']));

        // Verify Day 1 specific subtasks
        $day1Tasks = $day1['sub_tasks'];
        $this->assertTrue(collect($day1Tasks)->contains(fn($t) => str_contains($t, 'Bangun Tidur')));
        $this->assertTrue(collect($day1Tasks)->contains(fn($t) => str_contains($t, 'Lari #1')));
        $this->assertTrue(collect($day1Tasks)->contains(fn($t) => str_contains($t, 'Controlled Gaming')));
        $this->assertTrue(collect($day1Tasks)->contains(fn($t) => str_contains($t, 'Tidur Malam')));

        // Day 2 is Tuesday (ISO 2)
        $day2 = $roadmap[1];
        $this->assertStringContainsString('Selasa', $day2['title']);
        $day2Tasks = $day2['sub_tasks'];
        $this->assertTrue(collect($day2Tasks)->contains(fn($t) => str_contains($t, 'Renang #1')));
        $this->assertTrue(collect($day2Tasks)->contains(fn($t) => str_contains($t, 'Workout Otot A')));
        $this->assertTrue(collect($day2Tasks)->contains(fn($t) => str_contains($t, 'Cuci Pakaian') || str_contains($t, 'Nyuci Pakaian')));

        // Day 7 is Sunday (ISO 7)
        $day7 = $roadmap[6];
        $this->assertStringContainsString('Minggu', $day7['title']);
        $this->assertTrue($day7['is_rest_day']);
        $day7Tasks = $day7['sub_tasks'];
        $this->assertTrue(collect($day7Tasks)->contains(fn($t) => str_contains($t, 'Istirahat Total')));
    }

    public function test_milestone_show_renders_segmented_subtasks_checklist()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => '90 Days Dopamine Detox',
            'prompt' => 'Habit and fitness transformation',
            'target_days' => 90,
            'start_date' => '2026-10-19',
            'mode' => 'systematic',
            'setup_status' => 'active',
            'current_day' => 1,
        ]);

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1 (Senin): Pagi Lari Zona 2 + Kuliah',
            'description' => 'Target hari pertama',
            'status' => 'pending',
        ]);

        // Create 14 bulk subtasks
        $tasks = [
            '[04.00 – 04.30] Bangun Tidur + 30 Menit Off-Screen',
            '[04.00 – 04.30] Beberes Kamar (Merapikan Kasur)',
            '[04.00 – 04.30] Sholat Subuh (Saat Masuk Waktu)',
            '[04.30 – 04.50] Hafal Al-Qur\'an (15–20 Menit)',
            '[04.50 – 04.55] Tes Koran / Pauli-Kraepelin (5 Menit)',
            '[05.00 – 05.35] Lari #1 (Zona 2 Murni, 30 mnt)',
            '[05.45 – 06.45] Mandi, Sarapan Oatmeal/Protein + Minum Vitamin',
            '[06.45 – 08.40] Build Web / Belajar Programming (Sesi Deep Work)',
            '[08.50 – 17.10] Kuliah / Akademik + Sholat Dzuhur & Ashar Tepat Waktu',
            '[17.15 – 18.00] Bikin Konten (45 Menit)',
            '[18.00 – 18.30] Sholat Maghrib + Makan Malam Tinggi Protein',
            '[18.30 – 19.30] Controlled Gaming: Genshin Daily + Compe EA FC',
            '[20.00 – 20.30] Journaling (15 Menit) + Layar Redup',
            '[20.30 – 04.00] Tidur Malam Berkualitas (7 – 7,5 Jam)',
        ];

        foreach ($tasks as $t) {
            $target->subTasks()->create([
                'title' => $t,
                'is_completed' => false,
            ]);
        }

        $response = $this->get(route('skills.show', $skill->id));
        $response->assertStatus(200);

        // Verify checklist header and counts
        $response->assertSee('Checklist Subtask:');
        $response->assertSee('0/14 (0%)');

        // Verify time badges and phase pills
        $response->assertSee('Semua');
        $response->assertSee('Pagi');
        $response->assertSee('Siang');
        $response->assertSee('Malam');
        $response->assertSee('04.00 – 04.30');
        $response->assertSee('Bangun Tidur + 30 Menit Off-Screen');
        $response->assertSee('Controlled Gaming');
    }

    public function test_it_creates_bulk_tasks_with_subtasks_on_task_board_from_milestone()
    {
        $user = User::factory()->create();

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => '90 Days Dopamine Detox',
            'prompt' => 'Habit and fitness transformation',
            'target_days' => 90,
            'start_date' => now()->toDateString(),
            'mode' => 'systematic',
            'setup_status' => 'active',
            'current_day' => 1,
        ]);

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1: Lari Zona 2 + Kuliah',
            'description' => 'Target hari pertama',
            'status' => 'pending',
        ]);

        // Add 14 subtasks to target
        $subtasksData = [
            '[04.00 – 04.30] Bangun Tidur + 30 Menit Off-Screen',
            '[04.30 – 04.50] Hafal Al-Qur\'an (15–20 Menit)',
            '[04.50 – 04.55] Tes Koran / Pauli-Kraepelin (5 Menit)',
            '[05.00 – 05.35] Workout Pagi (Lari #1) + GtG Set 1',
            '[05.45 – 06.45] Mandi, Sarapan Oatmeal/Protein + Minum Vitamin',
            '[06.45 – 08.40] Build Web / Belajar Programming (Sesi Deep Work)',
            '[08.50 – 17.10] Kuliah / Akademik + Sholat Dzuhur & Ashar Tepat Waktu',
            '[17.15 – 18.00] Bikin Konten (45 Menit)',
            '[18.00 – 18.30] Sholat Maghrib + Makan Malam Tinggi Protein',
            '[18.30 – 19.30] Controlled Gaming: Genshin Daily + Compe EA FC',
            '[19.30 – 20.00] Sholat Isya + Persiapan Barang Besok',
            '[20.00 – 20.30] Journaling (15 Menit) + Layar Redup',
            '[20.30 – 04.00] Tidur Malam Berkualitas (7 – 7,5 Jam)',
        ];

        foreach ($subtasksData as $t) {
            $target->subTasks()->create(['title' => $t, 'is_completed' => false]);
        }

        // Generate bulk tasks for Day 1
        $createdTasks = AiSkillPlannerService::createBulkTasksForTarget($skill, $target, $user);

        $this->assertCount(13, $createdTasks);
        $this->assertEquals(13, Task::where('skill_target_id', $target->id)->count());

        // Test Bangun Tidur task details
        $bangunTask = Task::where('skill_target_id', $target->id)->where('title', 'like', '%Bangun Tidur%')->first();
        $this->assertNotNull($bangunTask);
        $this->assertEquals('04:00', $bangunTask->start_time);
        $this->assertEquals('04:30', $bangunTask->deadline);
        $this->assertEquals('Health', $bangunTask->category);
        $this->assertGreaterThanOrEqual(2, $bangunTask->subTasks()->count());

        // Test Workout task details
        $workoutTask = Task::where('skill_target_id', $target->id)->where('title', 'like', '%Workout Pagi%')->first();
        $this->assertNotNull($workoutTask);
        $this->assertEquals('05:00', $workoutTask->start_time);
        $this->assertEquals('high', $workoutTask->priority);
        $this->assertGreaterThanOrEqual(2, $workoutTask->subTasks()->count());

        // Test Coding task details
        $codeTask = Task::where('skill_target_id', $target->id)->where('title', 'like', '%Build Web%')->first();
        $this->assertNotNull($codeTask);
        $this->assertEquals('Work', $codeTask->category);
        $this->assertEquals('high', $codeTask->priority);
    }

    public function test_tasks_page_renders_time_block_filter_and_switches_blocks()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $today = now()->toDateString();

        // Create tasks across morning, afternoon, and evening
        Task::create([
            'user_id' => $user->id,
            'title' => '[04.00 – 04.30] Bangun Tidur',
            'date' => $today,
            'start_time' => '04:00',
            'mode' => 'sistematis',
            'is_completed' => true,
        ]);

        Task::create([
            'user_id' => $user->id,
            'title' => '[14.00 – 15.30] Kuliah Siang',
            'date' => $today,
            'start_time' => '14:00',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        Task::create([
            'user_id' => $user->id,
            'title' => '[19.00 – 20.00] Evaluasi Malam',
            'date' => $today,
            'start_time' => '19:00',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        \Livewire\Livewire::test('tasks')
            ->set('mode', 'sistematis')
            ->set('selectedDate', $today)
            ->call('loadTasks')
            ->assertSee('Semua Jadwal')
            ->assertSee('Pagi')
            ->assertSee('Siang')
            ->assertSee('Malam')
            // Switch to Morning
            ->set('filterTimeBlock', 'morning')
            ->assertSee('Bangun Tidur')
            ->assertDontSee('Kuliah Siang')
            ->assertDontSee('Evaluasi Malam')
            // Switch to Afternoon
            ->set('filterTimeBlock', 'afternoon')
            ->assertSee('Kuliah Siang')
            ->assertDontSee('Bangun Tidur')
            ->assertDontSee('Evaluasi Malam')
            // Switch to Evening
            ->set('filterTimeBlock', 'evening')
            ->assertSee('Evaluasi Malam')
            ->assertDontSee('Bangun Tidur')
            ->assertDontSee('Kuliah Siang')
            // Reset to All
            ->set('filterTimeBlock', 'all')
            ->assertSee('Bangun Tidur')
            ->assertSee('Kuliah Siang')
            ->assertSee('Evaluasi Malam');
    }

    public function test_completing_milestone_bulk_tasks_updates_progress_and_auto_completes_target()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $today = now()->toDateString();

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => '90 Days Dopamine Detox',
            'prompt' => 'Challenge',
            'target_days' => 90,
            'start_date' => $today,
            'mode' => 'systematic',
            'setup_status' => 'active',
            'current_day' => 1,
        ]);

        $target1 = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1: Kickoff',
            'description' => 'Target 1',
            'status' => 'pending',
        ]);

        $target2 = $skill->targets()->create([
            'day_number' => 2,
            'title' => 'Hari 2: Pendalaman',
            'description' => 'Target 2',
            'status' => 'pending',
        ]);

        // Create 2 bulk tasks for target1
        $task1 = Task::create([
            'user_id' => $user->id,
            'skill_id' => $skill->id,
            'skill_target_id' => $target1->id,
            'title' => '[04.00 – 05.00] Task Pagi',
            'date' => $today,
            'start_time' => '04:00',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $task2 = Task::create([
            'user_id' => $user->id,
            'skill_id' => $skill->id,
            'skill_target_id' => $target1->id,
            'title' => '[18.00 – 19.00] Task Malam',
            'date' => $today,
            'start_time' => '18:00',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $component = \Livewire\Livewire::test('tasks')
            ->set('mode', 'sistematis')
            ->set('selectedDate', $today);

        // Complete 1st task: Target should still be pending (50%)
        $component->call('toggleComplete', $task1->id);

        $target1->refresh();
        $this->assertEquals('pending', $target1->status);
        $this->assertEquals(50, $target1->progress_percentage);

        // Complete 2nd task: Target should now be completed (100%), and day advances!
        $component->call('toggleComplete', $task2->id);

        $target1->refresh();
        $skill->refresh();
        $this->assertEquals('completed', $target1->status);
        $this->assertEquals(100, $target1->progress_percentage);
        $this->assertEquals(2, $skill->current_day);
    }

    public function test_day_card_description_has_clean_count_summary()
    {
        $user = User::factory()->create();

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => '90 Days Detox',
            'prompt' => 'Detox',
            'target_days' => 90,
            'start_date' => '2026-10-19',
            'mode' => 'systematic',
            'training_days' => ['1', '2', '3', '4', '5', '6', '7'],
            'interview_context' => [
                'syllabus_text' => $this->sampleSyllabus,
            ],
            'setup_status' => 'active',
            'current_day' => 1,
        ]);

        $service = new AiSkillPlannerService();
        $roadmap = $service->generateFallbackRoadmap($skill);

        $day1 = $roadmap[0];
        // Must contain concise summary format
        $this->assertStringContainsString('Hari 1 dari tantangan 90 Days Detox', $day1['description']);
        $this->assertStringContainsString('tugas harian terjadwal', $day1['description']);
        $this->assertStringContainsString('Fokus utama hari ini:', $day1['description']);
    }
}
