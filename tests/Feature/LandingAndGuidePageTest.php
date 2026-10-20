<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAndGuidePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_successfully_with_downloads_and_guide()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Pasang App Windows');
        $response->assertSee('macOS App');
        $response->assertSee('Panduan');
        $response->assertSee('/images/atoma_icon.png');
        $response->assertSee('toggleDarkMode');
        $response->assertSee('localStorage.getItem(\'darkMode\')', false);
    }

    public function test_guide_page_renders_successfully_for_guest_and_user()
    {
        // Guest
        $guestResponse = $this->get('/guide');
        $guestResponse->assertStatus(200);
        $guestResponse->assertSee('Panduan');
        $guestResponse->assertSee('Halaman Tugas');
        $guestResponse->assertSee('Input Tugas & Dekomposisi Target');
        $guestResponse->assertSee('Tugas Olahraga');
        $guestResponse->assertSee('Tugas Finansial');
        $guestResponse->assertSee('Otomasi Tugas Berulang');
        $guestResponse->assertSee('Milestone', false);
        $guestResponse->assertSee('Pomodoro', false);
        $guestResponse->assertSee('toggleDarkMode');

        // Authenticated User
        $user = User::factory()->create();
        $userResponse = $this->actingAs($user)->get('/guide');
        $userResponse->assertStatus(200);
        $userResponse->assertSee('Buka Dashboard');
    }

    public function test_login_page_renders_dashboard_icon()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('/images/atoma_icon.png');
        $response->assertSee('localStorage.getItem(\'darkMode\')', false);
    }
}
