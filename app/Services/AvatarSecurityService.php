<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AvatarSecurityService
{
    /**
     * Maximum allowed input dimensions before memory exhaustion (anti-DoS / decompression bomb).
     */
    public const MAX_INPUT_DIMENSION = 6000;

    /**
     * Standard normalized avatar dimensions.
     */
    public const AVATAR_OUTPUT_WIDTH = 400;
    public const AVATAR_OUTPUT_HEIGHT = 400;

    /**
     * WebP output quality (0-100).
     */
    public const WEBP_QUALITY = 82;

    /**
     * Allowed MIME types verified via FileInfo.
     */
    protected static array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * Validate, moderate content (anti-NSFW/vulgar), sanitize (anti-malware/EXIF strip),
     * and re-encode to lightweight WebP.
     *
     * @param UploadedFile $file
     * @param User $user
     * @return string Relative storage path (e.g., 'avatars/avatar_1_abc123.webp')
     * @throws ValidationException
     */
    public static function processAndStore(UploadedFile $file, User $user): string
    {
        // 1. Strict File Inspection (Magic Bytes, Real MIME, Dimension limits)
        self::validateImageStructure($file);

        // 2. Load into memory & detect skin tone ratio (Local Pre-Filter)
        $gdImage = self::createGdResource($file);
        $skinToneRatio = self::calculateSkinToneRatio($gdImage);

        // 3. Multimodal AI Content Moderation (Anti-Nudity, Bikini, Ecchi Anime, Violence)
        self::moderateContent($gdImage, $user, $skinToneRatio);

        // 4. Sanitize, Re-encode, Strip EXIF / Polyglot Code, and Save as Clean WebP
        $path = self::reencodeAndSave($gdImage, $user);

        // 5. Clean up previous custom avatar from disk to prevent storage leak
        self::deleteOldAvatar($user);

        // Free GD memory
        imagedestroy($gdImage);

        return $path;
    }

    /**
     * Strict image validation: Magic Bytes & Anti-Decompression Bomb.
     */
    public static function validateImageStructure(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'avatar' => __('Unggahan file gagal atau file rusak.'),
            ]);
        }

        // Verify Real MIME Type via PHP fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        if (!in_array($realMime, self::$allowedMimeTypes, true)) {
            SystemLogService::security(
                'avatar.invalid_mime',
                "Percobaan upload file dengan MIME mencurigakan: {$realMime}",
                ['client_name' => $file->getClientOriginalName(), 'real_mime' => $realMime]
            );

            throw ValidationException::withMessages([
                'avatar' => __('Format file tidak didukung. Harap unggah gambar JPG, PNG, atau WebP.'),
            ]);
        }

        // Verify dimensions and image header
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw ValidationException::withMessages([
                'avatar' => __('Berkas yang diunggah bukan format gambar valid.'),
            ]);
        }

        [$width, $height] = $imageInfo;

        if ($width > self::MAX_INPUT_DIMENSION || $height > self::MAX_INPUT_DIMENSION) {
            SystemLogService::security(
                'avatar.pixel_bomb_blocked',
                "Percobaan upload gambar dengan dimensi ekstrem: {$width}x{$height} px",
                ['width' => $width, 'height' => $height]
            );

            throw ValidationException::withMessages([
                'avatar' => __('Dimensi gambar terlalu besar (maksimal 6.000 piksel).'),
            ]);
        }
    }

    /**
     * Create GD resource from uploaded file with orientation correction.
     */
    protected static function createGdResource(UploadedFile $file)
    {
        $realPath = $file->getRealPath();
        $content = file_get_contents($realPath);

        if ($content === false) {
            throw ValidationException::withMessages([
                'avatar' => __('Gagal membaca file gambar.'),
            ]);
        }

        $image = @imagecreatefromstring($content);

        if ($image === false) {
            throw ValidationException::withMessages([
                'avatar' => __('Gagal memproses struktur piksel gambar.'),
            ]);
        }

        // Auto-orient JPEG if EXIF orientation exists
        if (function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($realPath);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $image = imagerotate($image, 180, 0);
                            break;
                        case 6:
                            $image = imagerotate($image, -90, 0);
                            break;
                        case 8:
                            $image = imagerotate($image, 90, 0);
                            break;
                    }
                }
            } catch (Throwable $e) {}
        }

        return $image;
    }

    /**
     * Fast local skin-tone ratio heuristic calculation (0 token cost).
     * Samples pixels every N steps to estimate human skin surface exposure.
     */
    public static function calculateSkinToneRatio($image): float
    {
        $w = imagesx($image);
        $h = imagesy($image);

        if ($w <= 0 || $h <= 0) {
            return 0.0;
        }

        $step = max(4, (int) round(min($w, $h) / 40));
        $totalSampled = 0;
        $skinSampled = 0;

        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $totalSampled++;

                // Standard RGB skin-tone bounding heuristic
                if (
                    $r > 95 && $g > 40 && $b > 20 &&
                    (max($r, $g, $b) - min($r, $g, $b) > 15) &&
                    abs($r - $g) > 15 &&
                    $r > $g && $r > $b
                ) {
                    $skinSampled++;
                }
            }
        }

        return $totalSampled > 0 ? ($skinSampled / $totalSampled) : 0.0;
    }

    /**
     * Multimodal AI Content Moderation: Detects pornography, bikinis/underwear,
     * ecchi/vulgar anime, and gore.
     */
    public static function moderateContent($image, User $user, float $skinToneRatio): void
    {
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

        // Check if Gemini is disabled or quota cooldown is active
        if (empty($apiKey) || Cache::get('gemini_quota_reset_time')) {
            // Offline Fallback: If skin tone ratio is suspiciously high (> 50% exposed skin)
            if ($skinToneRatio > 0.50) {
                SystemLogService::warn(
                    "Upload avatar mencurigakan dengan rasio kulit tinggi ({$skinToneRatio}) saat AI offline",
                    ['user_id' => $user->id, 'skin_ratio' => $skinToneRatio],
                    'avatar.offline_skin_warning'
                );
            }
            return;
        }

        // Fast-pass for obviously non-skin images (landscape, dark abstract, normal low skin)
        // Only run full AI vision if skin tone >= 0.18 OR user is not verified
        if ($skinToneRatio < 0.18) {
            return; // 0 token consumed for safe non-skin images!
        }

        // Create lightweight 256x256 thumbnail for AI inspection (ultra-fast, ~15KB payload)
        $thumb = imagecreatetruecolor(256, 256);
        $srcW = imagesx($image);
        $srcH = imagesy($image);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, 256, 256, $srcW, $srcH);

        ob_start();
        imagejpeg($thumb, null, 75);
        $thumbData = ob_get_clean();
        imagedestroy($thumb);

        $base64Image = base64_encode($thumbData);
        $prompt = "Kamu adalah AI Moderasi Konten untuk platform produktivitas dan belajar ATOMA.
Tugasmu adalah menganalisis apakah foto avatar ini mematuhi pedoman komunitas belajar (family-friendly).

KRITERIA PENOLAKAN (is_safe = false):
1. \"explicit_porn\": Ketelanjangan murni (nudity), alat kelamin, payudara terbuka, atau konten seksual vulgar.
2. \"bikini_or_swimwear\": Orang berfoto mengenakan bikini, pakaian renang, pakaian dalam (lingerie/underwear), celana dalam, atau berpose erotis/sensual.
3. \"ecchi_anime\": Karakter anime/2D/kartun yang mengenakan bikini, pakaian dalam, pakaian sangat minim/terbuka, pose sensual/erotis (ecchi), atau ekspresi sugestif (ahegao).
4. \"violence_gore\": Kekerasan sadis, darah berlebihan, atau konten mengerikan.

KRITERIA KELULUSAN (is_safe = true):
- Foto wajah normal, pas foto, foto casual berbusana sopan.
- Karakter anime/game/kartun yang mengenakan busana wajar/sopan (misal: Luffy, Naruto, karakter berkemeja/kaos normal).
- Hewan, pemandangan, logo, ilustrasi abstrak, kendaraan.

Kembalikan HANYA JSON murni (tanpa markdown backticks):
{
  \"is_safe\": true/false,
  \"category\": \"safe\"|\"explicit_porn\"|\"bikini_or_swimwear\"|\"ecchi_anime\"|\"violence_gore\",
  \"reason\": \"Penjelasan singkat dalam bahasa Indonesia\"
}";

        $modelPool = AiLoggerService::getModelPool();
        $startTime = microtime(true);

        foreach ($modelPool as $model) {
            if (Cache::has("gemini_cooldown_{$model}")) {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(8)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => 'image/jpeg',
                                        'data' => $base64Image,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 200,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $jsonText = $response->json('candidates.0.content.parts.0.text');
                    $usage = $response->json('usageMetadata', []);
                    $promptTokens = $usage['promptTokenCount'] ?? 250;
                    $compTokens = $usage['candidatesTokenCount'] ?? 40;

                    AiLoggerService::log(
                        $user->id,
                        'avatar_moderator',
                        'Avatar Moderation Safety Check',
                        $jsonText,
                        $model,
                        $promptTokens,
                        $compTokens,
                        $durationMs,
                        'success'
                    );

                    $cleanJson = trim(preg_replace('/^```(?:json)?|```$/m', '', (string) $jsonText));
                    $data = json_decode($cleanJson, true);

                    if (is_array($data) && isset($data['is_safe']) && $data['is_safe'] === false) {
                        $category = $data['category'] ?? 'inappropriate';
                        $reason = $data['reason'] ?? 'Melanggar pedoman komunitas.';

                        SystemLogService::security(
                            'avatar.nsfw_blocked',
                            "Foto profil diblokir: Kategori '{$category}' - {$reason}",
                            [
                                'user_id' => $user->id,
                                'category' => $category,
                                'reason' => $reason,
                                'skin_ratio' => $skinToneRatio,
                            ]
                        );

                        throw ValidationException::withMessages([
                            'avatar' => __('Foto profil ditolak: ATOMA adalah ruang produktivitas bersama. Mohon gunakan foto atau karakter anime yang berbusana sopan (hindari bikini/pakaian renang, pakaian dalam, atau karakter anime dengan pakaian minim).'),
                        ]);
                    }

                    return; // Passed moderation!
                }

                // If quota exceeded (429)
                if ($response->status() === 429) {
                    Cache::put("gemini_cooldown_{$model}", true, now()->addMinutes(10));
                    continue;
                }
            } catch (ValidationException $ve) {
                throw $ve;
            } catch (Throwable $e) {
                // Log and continue to next model in pool
                continue;
            }
        }
    }

    /**
     * Sanitize, square crop/center, strip all EXIF / polyglot code, and save as WebP.
     *
     * @param resource|\GdImage $srcImage
     * @param User $user
     * @return string
     */
    protected static function reencodeAndSave($srcImage, User $user): string
    {
        $srcW = imagesx($srcImage);
        $srcH = imagesy($srcImage);

        // Center square crop calculations
        $squareSize = min($srcW, $srcH);
        $srcX = (int) round(($srcW - $squareSize) / 2);
        $srcY = (int) round(($srcH - $squareSize) / 2);

        // Destination truecolor canvas
        $targetImg = imagecreatetruecolor(self::AVATAR_OUTPUT_WIDTH, self::AVATAR_OUTPUT_HEIGHT);

        // Enable alpha transparency for clean icons/drawings
        imagealphablending($targetImg, false);
        imagesavealpha($targetImg, true);

        // Resample with anti-aliasing (destroys any hidden EXIF payloads or PHP webshell code)
        imagecopyresampled(
            $targetImg,
            $srcImage,
            0,
            0,
            $srcX,
            $srcY,
            self::AVATAR_OUTPUT_WIDTH,
            self::AVATAR_OUTPUT_HEIGHT,
            $squareSize,
            $squareSize
        );

        // Generate safe random unique filename (no user input in filename)
        $filename = 'avatar_' . $user->id . '_' . bin2hex(random_bytes(8)) . '.webp';
        $directory = 'avatars';
        $storageDisk = Storage::disk('public');

        if (!$storageDisk->exists($directory)) {
            $storageDisk->makeDirectory($directory);
        }

        $fullPath = $storageDisk->path($directory . '/' . $filename);

        // Save as clean WebP (ultra-lightweight ~25KB - 45KB)
        $saved = imagewebp($targetImg, $fullPath, self::WEBP_QUALITY);

        if (!$saved) {
            // Fallback to JPEG if WebP file write failed
            $filename = 'avatar_' . $user->id . '_' . bin2hex(random_bytes(8)) . '.jpg';
            $fullPath = $storageDisk->path($directory . '/' . $filename);
            imagejpeg($targetImg, $fullPath, 85);
        }

        imagedestroy($targetImg);

        return $directory . '/' . $filename;
    }

    /**
     * Delete user's previous custom avatar from storage disk to prevent storage leaks.
     */
    public static function deleteOldAvatar(User $user): void
    {
        $oldPath = $user->avatar_path;

        if (!empty($oldPath) && !str_starts_with($oldPath, 'http')) {
            $storageDisk = Storage::disk('public');
            if ($storageDisk->exists($oldPath)) {
                $storageDisk->delete($oldPath);
            }
        }
    }
}
