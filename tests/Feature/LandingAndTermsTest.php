<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAndTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('ATOMA');
        $response->assertSee('Fitur Seru');
        $response->assertSee('Syarat & Privasi');
        $response->assertSee('Sign Up');
        $response->assertSee('Level Up Your Routine');
        $response->assertSee('Manajemen Tugas Multi-Fungsi');
        $response->assertSee('Roadmap & Kurikulum 30 Hari');
        $response->assertSee('Sistem Level Up & Prestise RPG');
        $response->assertSee('Timer Pomodoro & Audio Terpadu');
        $response->assertSee('Kolaborasi Sosial & Akuntabilitas');
        $response->assertSee('Kartu Belajar Spaced Repetition (SRS)');
        $response->assertDontSee('Tanya Jawab Singkat (FAQ)');
        $response->assertDontSee('#faq');
    }

    public function test_welcome_page_translates_to_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get('/');
        $response->assertStatus(200);
        $response->assertSee('Exciting Features');
        $response->assertSee('Terms & Privacy');
        $response->assertSee('Plan Your Day');
        $response->assertSee('Download Apps');
        $response->assertSee('Sign Up');
        $response->assertSee('Level Up Your Routine');
        $response->assertSee('Multi-Functional Task Management');
        $response->assertSee('30-Day Roadmap & Curriculum');
        $response->assertSee('RPG Level Up & Prestige System');
        $response->assertSee('Integrated Pomodoro & Audio');
        $response->assertSee('Social Collaboration & Accountability');
        $response->assertSee('Spaced Repetition (SRS) Flashcards');
    }

    public function test_terms_page_renders_with_privacy_commitments(): void
    {
        $response = $this->get('/terms');
        $response->assertStatus(200);
        $response->assertSee('Syarat, Ketentuan & Kebijakan Privasi');
        $response->assertSee('Komitmen Kami: Kami Tidak Menjual atau Mengambil Datamu');
        $response->assertSee('Nol Penjualan Data (Zero Data Selling)');
        $response->assertSee('Data Murni Penunjang Produktivitas');
        $response->assertSee('Sign Up');
        $response->assertSee('Level Up Your Routine');
        $response->assertSee('Kembali ke Beranda');
    }

    public function test_terms_page_translates_to_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get('/terms');
        $response->assertStatus(200);
        $response->assertSee('Terms of Service & Privacy Policy');
        $response->assertSee('Our Commitment: We Do Not Sell or Harvest Your Data');
        $response->assertSee('Zero Data Selling');
        $response->assertSee('Sign Up');
        $response->assertSee('Level Up Your Routine');
        $response->assertSee('Back to Home');
    }

    public function test_language_switch_route_updates_session(): void
    {
        $response = $this->get('/lang/en');
        $response->assertRedirect();
        $response->assertSessionHas('locale', 'en');

        $responseId = $this->get('/lang/id');
        $responseId->assertRedirect();
        $responseId->assertSessionHas('locale', 'id');
    }

    public function test_guide_page_renders_with_functional_guide(): void
    {
        $response = $this->get('/guide');
        $response->assertStatus(200);
        $response->assertSee('Dokumentasi Sistem Produktivitas Terpadu');
        $response->assertSee('Alur Kerja & Panduan Fitur Lengkap');
        $response->assertSee('1. Halaman Tugas (Tasks) & Fitur Lengkapnya');
        $response->assertSee('Fitur Tugas Khusus: Olahraga, Keuangan, & Kebiasaan Rutin');
        $response->assertSee('Panduan Rinci: Kurikulum Milestone (Roadmap 30 Hari)');
        $response->assertSee('2. Fitur AI Super-Engine (Smart Automation & Decomposer)');
        $response->assertSee('3. Modul Pomodoro & Audio Fokus Terintegrasi');
        $response->assertSee('4. Halaman Note & Manajemen Finansial Terintegrasi');
        $response->assertSee('5. Halaman Flashcard (Spaced Repetition System)');
        $response->assertSee('6. Halaman Sosial, Kolaborasi, & Akuntabilitas Rekan');
        $response->assertSee('7. Halaman Leaderboard & Sistem Liga Mingguan');
        $response->assertSee('8. Halaman Store (Insentif & Proteksi Konsistensi)');
        $response->assertSee('9. Halaman Profil & Analisis Statistik Karakter');
        $response->assertSee('10. Dukungan Multi-Platform (Desktop & Mobile PWA)');
        $response->assertSee('Sign Up');
        $response->assertSee('Level Up Your Routine');

        // Verify 0 emojis
        preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $response->getContent(), $emojis);
        $this->assertEmpty($emojis[0], 'Guide page contains emojis');
    }

    public function test_guide_page_translates_to_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get('/guide');
        $response->assertStatus(200);
        $response->assertSee('Integrated Productivity System Documentation');
        $response->assertSee('Comprehensive Workflow & Feature Guide');
        $response->assertSee('Sign Up');
        $response->assertSee('Level Up Your Routine');
        $response->assertSee('3. Integrated Pomodoro & Audio Focus Module');
        $response->assertSee('5. Flashcard Page (Spaced Repetition System)');
        $response->assertSee('10. Multi-Platform Support (Desktop & Mobile PWA)');
    }
}
