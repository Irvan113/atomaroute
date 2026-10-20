<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class MultiLanguageI18nTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_files_are_valid_json(): void
    {
        $idContent = file_get_contents(base_path('lang/id.json'));
        $enContent = file_get_contents(base_path('lang/en.json'));

        $this->assertNotFalse($idContent);
        $this->assertNotFalse($enContent);

        $idJson = json_decode($idContent, true);
        $enJson = json_decode($enContent, true);

        $this->assertIsArray($idJson);
        $this->assertIsArray($enJson);
        $this->assertGreaterThan(30, count($idJson));
        $this->assertGreaterThan(150, count($enJson));
    }

    public function test_english_locale_translates_indonesian_keys(): void
    {
        App::setLocale('en');

        $this->assertEquals('Task Title', __('Judul Tugas'));
        $this->assertEquals('Add', __('Tambah'));
        $this->assertEquals('Feature Guide', __('Panduan Fitur'));
        $this->assertEquals('Cancel', __('Batal'));
        $this->assertEquals('Save Changes', __('Simpan Perubahan'));
        $this->assertEquals('Create Task', __('Buat Tugas'));
        $this->assertEquals('Notes', __('Note'));
        $this->assertEquals('Finance', __('Keuangan'));
        $this->assertEquals('Leaderboard', __('Papan Peringkat (Leaderboard)'));
        $this->assertEquals('Light / Dark Mode', __('Mode Terang / Gelap'));
        $this->assertEquals('Log Out', __('Keluar Akun'));
    }

    public function test_indonesian_locale_translates_english_defaults(): void
    {
        App::setLocale('id');

        $this->assertEquals('Informasi Profil', __('Profile Information'));
        $this->assertEquals('Perbarui Kata Sandi', __('Update Password'));
        $this->assertEquals('Hapus Akun', __('Delete Account'));
        $this->assertEquals('Simpan Perubahan', __('Save Changes'));
        $this->assertEquals('Batal', __('Cancel'));
        $this->assertEquals('Ingat saya', __('Remember me'));
        $this->assertEquals('Lupa kata sandi?', __('Forgot password?'));
        $this->assertEquals('Masuk', __('Log in'));
        $this->assertEquals('Daftar', __('Register'));
        $this->assertEquals('Tugas', __('Tasks'));
        $this->assertEquals('Catatan', __('Note'));
        $this->assertEquals('Panduan Fitur', __('Feature Guide'));
    }

    public function test_user_locale_preference_can_be_saved(): void
    {
        $user = User::factory()->create([
            'locale' => 'id',
        ]);

        $this->actingAs($user);

        $user->locale = 'en';
        $user->save();

        $this->assertEquals('en', $user->fresh()->locale);
    }

    public function test_insights_subtitles_and_hints_translation(): void
    {
        App::setLocale('en');

        // Subtitles & descriptions
        $this->assertEquals('Energy distribution & task category proportion', __('Distribusi energi & proporsi kategori tugas'));
        $this->assertEquals('When your brain & energy are at their peak', __('Kapan otak & energimu berada di titik optimal'));
        $this->assertEquals('Your productivity strength across 4 fundamental dimensions', __('Kekuatan produktivitasmu dalam 4 dimensi fundamental'));
        $this->assertEquals('Total Pomodoro session hours', __('Total jam sesi Pomodoro'));
        $this->assertEquals('Streak & 30-day attendance', __('Streak & kehadiran 30 hari'));
        $this->assertEquals('On-time vs deadline completion', __('Tepat waktu vs deadline'));
        $this->assertEquals('Subtasks, categories, & estimates', __('Subtask, kategori, & estimasi'));
        $this->assertEquals('Current Level Progress (Exponential Curve)', __('Progres Level Ini (Kurva Eksponensial)'));
        $this->assertEquals('ATOMA multi-tier ranking system', __('Sistem rank multi-tier ATOMA'));
        $this->assertEquals('Lifetime XP is permanent and never resets, while seasonal levels refresh every quarter.', __('Lifetime XP abadi dan tidak pernah direset, sedangkan level musiman diperbarui tiap kuartal.'));
    }

    public function test_dynamic_parameterized_translations(): void
    {
        App::setLocale('en');

        // Dynamic tip with day placeholder
        $day = __('Senin');
        $this->assertEquals('Monday', $day);
        $translatedTip = __('Hari :day adalah hari paling efektifmu. Gunakan hari ini untuk menuntaskan milestone penting.', ['day' => $day]);
        $this->assertEquals('Monday is your most effective day. Use this day to complete key milestones.', $translatedTip);

        // Dynamic notice with category and percentage placeholder
        $category = __('Kerja / Proyek');
        $this->assertEquals('Work / Projects', $category);
        $translatedNotice = __('Fokusmu sangat didominasi oleh :cat (:pct%). Sempatkan jeda dan olahraga agar tidak burnout!', [
            'cat' => $category,
            'pct' => 65,
        ]);
        $this->assertEquals('Your focus is heavily dominated by Work / Projects (65%). Take a break and exercise to avoid burnout!', $translatedNotice);
    }

    public function test_feedback_and_streak_modal_translations(): void
    {
        App::setLocale('en');

        // Feedback Modal
        $this->assertEquals('View Letter History', __('Lihat Riwayat Surat'));
        $this->assertEquals('Write Letter Now', __('Tulis Surat Sekarang'));
        $this->assertEquals('Message Subject', __('Subjek Pesan'));
        $this->assertEquals('Feedback & Letter to Developer', __('Feedback & Letter to Developer'));

        // Streak Modal
        $this->assertEquals('How Streaks Work', __('Cara Kerja Streak'));
        $this->assertEquals('Longest Streak', __('Streak Terpanjang'));
        $this->assertEquals('Streak Protection', __('Streak Protection'));
        $this->assertEquals('Streak Calendar', __('Streak Calendar'));
    }

    public function test_prestige_and_social_translations(): void
    {
        App::setLocale('en');

        // Prestige Page
        $this->assertEquals('Level Up & Prestige System', __('Sistem Level Up & Prestige'));
        $this->assertEquals('Back to Tasks', __('Kembali ke Tasks'));
        $this->assertEquals('Allocation & Daily EXP Cap Progress', __('Alokasi & Progress Batas EXP Harian'));
        $this->assertEquals('5 Prestige Tiers in ATOMA', __('5 Tingkatan Prestige di ATOMA'));
        $this->assertEquals('EXP Requirement Calculator & Formula', __('Kalkulator & Rumus Kebutuhan EXP'));

        // Social Page
        $this->assertEquals('Open Full Forum', __('Buka Forum Penuh'));
        $this->assertEquals('Create New Topic', __('Buat Topik Baru'));
        $this->assertEquals('Direct Messages', __('Pesan Langsung'));
        $this->assertEquals('Nudge Duel', __('Senggol Bacok (Duel)'));
    }

    public function test_skills_and_audio_preferences_translations(): void
    {
        App::setLocale('en');

        // Skills Pathway
        $this->assertEquals('Congratulations! Skill Mastered!', __('Selamat! Skill Berhasil Dikuasai!'));
        $this->assertEquals('Certificate of Mastery', __('Sertifikat Penguasaan'));
        $this->assertEquals('AI Evaluation Checkpoint', __('Checkpoint Evaluasi AI'));
        $this->assertEquals('Time for Self-Recovery', __('Waktunya Pemulihan Diri'));

        // Audio & Task Preferences
        $this->assertEquals('Task Display & Interaction Preferences', __('Preferensi Tampilan & Interaksi Tugas'));
        $this->assertEquals('Notification Sound Trigger Schedule:', __('Jadwal Pemicu Suara Notifikasi:'));
        $this->assertEquals('Test Selected Sound Now', __('Tes Suara Pilihan Sekarang'));
    }
}
