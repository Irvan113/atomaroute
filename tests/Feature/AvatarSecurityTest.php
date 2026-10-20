<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AvatarSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AvatarSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_valid_image_and_it_is_reencoded_to_webp(): void
    {
        $user = User::factory()->create();

        // Create a fake image (e.g. 600x800 portrait)
        $file = UploadedFile::fake()->image('profile.jpg', 600, 800);

        // Mock Gemini API to pass moderation
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"is_safe": true, "category": "safe", "reason": "Foto normal dan sopan"}']
                            ]
                        ]
                    ]
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 258,
                    'candidatesTokenCount' => 30,
                ]
            ], 200),
        ]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('avatar', $file)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        $this->assertStringEndsWith('.webp', $user->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($user->avatar_path));

        // Verify stored image dimensions are normalized to 400x400
        $storedContent = Storage::disk('public')->get($user->avatar_path);
        $imageInfo = getimagesizefromstring($storedContent);
        $this->assertSame(400, $imageInfo[0]);
        $this->assertSame(400, $imageInfo[1]);
    }

    public function test_old_avatar_file_is_deleted_when_new_avatar_is_uploaded(): void
    {
        $user = User::factory()->create();
        $oldPath = 'avatars/avatar_' . $user->id . '_old12345.webp';
        Storage::disk('public')->put($oldPath, 'sample old avatar bytes');
        $user->update(['avatar_path' => $oldPath]);

        $this->assertTrue(Storage::disk('public')->exists($oldPath));

        $newFile = UploadedFile::fake()->image('new_avatar.png', 400, 400);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"is_safe": true, "category": "safe", "reason": "OK"}']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('avatar', $newFile)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();

        // Old file must be deleted
        $this->assertFalse(Storage::disk('public')->exists($oldPath));
        // New file must exist
        $this->assertTrue(Storage::disk('public')->exists($user->avatar_path));
    }

    public function test_fake_or_corrupt_non_image_is_rejected(): void
    {
        $user = User::factory()->create();

        // Create a fake PHP script with .jpg extension
        $maliciousFile = UploadedFile::fake()->createWithContent('shell.php.jpg', '<?php echo "hacked"; ?>');

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('avatar', $maliciousFile)
            ->call('updateProfileInformation')
            ->assertHasErrors('avatar');

        $this->assertDatabaseHas('system_logs', [
            'category' => 'security',
            'action' => 'avatar.invalid_mime',
        ]);
    }

    public function test_extreme_pixel_bomb_dimension_is_rejected(): void
    {
        $user = User::factory()->create();

        // Dimensions exceeding 6000px threshold
        $bomb = UploadedFile::fake()->image('huge.jpg', 6500, 6500);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('avatar', $bomb)
            ->call('updateProfileInformation')
            ->assertHasErrors('avatar');

        $this->assertDatabaseHas('system_logs', [
            'category' => 'security',
            'action' => 'avatar.pixel_bomb_blocked',
        ]);
    }

    public function test_moderation_blocks_bikini_or_ecchi_anime_content(): void
    {
        $user = User::factory()->create();

        // Create a valid image with warm tones
        $img = imagecreatetruecolor(300, 300);
        $skinColor = imagecolorallocate($img, 220, 160, 130);
        imagefill($img, 0, 0, $skinColor);
        ob_start();
        imagejpeg($img);
        $data = ob_get_clean();
        imagedestroy($img);

        $file = UploadedFile::fake()->createWithContent('bikini_photo.jpg', $data);

        // Mock Gemini AI detecting bikini or ecchi content
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"is_safe": false, "category": "bikini_or_swimwear", "reason": "Terdeteksi mengenakan pakaian renang atau berbusana minim"}']
                            ]
                        ]
                    ]
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 258,
                    'candidatesTokenCount' => 45,
                ]
            ], 200),
        ]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('avatar', $file)
            ->call('updateProfileInformation')
            ->assertHasErrors('avatar');

        $this->assertDatabaseHas('system_logs', [
            'category' => 'security',
            'action' => 'avatar.nsfw_blocked',
        ]);
    }

    public function test_local_skin_tone_ratio_calculation(): void
    {
        // 1. All-black image (0% skin tone)
        $blackImg = imagecreatetruecolor(100, 100);
        $blackColor = imagecolorallocate($blackImg, 0, 0, 0);
        imagefill($blackImg, 0, 0, $blackColor);
        $ratioBlack = AvatarSecurityService::calculateSkinToneRatio($blackImg);
        imagedestroy($blackImg);

        $this->assertSame(0.0, $ratioBlack);

        // 2. All-skin tone image (100% skin tone)
        $skinImg = imagecreatetruecolor(100, 100);
        $skinColor = imagecolorallocate($skinImg, 220, 150, 120);
        imagefill($skinImg, 0, 0, $skinColor);
        $ratioSkin = AvatarSecurityService::calculateSkinToneRatio($skinImg);
        imagedestroy($skinImg);

        $this->assertGreaterThan(0.95, $ratioSkin);
    }
}
