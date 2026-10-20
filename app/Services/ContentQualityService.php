<?php

namespace App\Services;

class ContentQualityService
{
    /**
     * Common dummy words & spam patterns
     */
    protected static array $dummyKeywords = [
        'test', 'testing', 'asdf', 'qwerty', 'dummy', 'abnadaaca',
        'abcd', '1234', 'hahaha', 'hehehe', 'coba', 'cobaa', 'tes'
    ];

    /**
     * Validate content quality to prevent low-effort spam & gibberish.
     *
     * @param string $text
     * @param int $minWords
     * @param int $minChars
     * @return array ['valid' => bool, 'message' => string|null]
     */
    public static function validateText(string $text, int $minWords = 2, int $minChars = 5): array
    {
        $clean = trim($text);

        if (empty($clean)) {
            return ['valid' => false, 'message' => 'Input tidak boleh kosong.'];
        }

        if (mb_strlen($clean) < $minChars) {
            return ['valid' => false, 'message' => "Input terlalu pendek (minimal {$minChars} karakter)."];
        }

        $words = preg_split('/\s+/', $clean);
        if (count($words) < $minWords) {
            return ['valid' => false, 'message' => "Mohon tuliskan deskripsi yang lebih bermakna (minimal {$minWords} kata)."];
        }

        $lower = strtolower($clean);

        // 1. Check direct dummy keywords
        foreach (self::$dummyKeywords as $keyword) {
            if ($lower === $keyword || preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $lower)) {
                // Allow if it's part of a longer sentence (>= 5 words), otherwise flag
                if (count($words) < 5) {
                    return ['valid' => false, 'message' => 'Mohon gunakan kalimat asli dan hindari kata penguji (seperti test/asdf).'];
                }
            }
        }

        // 2. Check single character repetition (e.g., "aaaaa", "zzzzzz")
        if (preg_match('/(.)\1{4,}/i', $clean)) {
            return ['valid' => false, 'message' => 'Terdeteksi pengulangan karakter acak. Mohon tulis kalimat yang valid.'];
        }

        // 3. Check pattern repetition (e.g., "abcabcabc", "123123123")
        if (preg_match('/(.{2,4})\1{3,}/i', str_replace(' ', '', $lower))) {
            return ['valid' => false, 'message' => 'Terdeteksi pengulangan frasa acak. Mohon tulis kalimat yang berkualitas.'];
        }

        // 4. Gibberish / Entropy & Unique character ratio check for single short inputs
        $alphanumericOnly = preg_replace('/[^a-zA-Z]/', '', $lower);
        if (strlen($alphanumericOnly) >= 6) {
            $charCounts = count_chars($alphanumericOnly, 1);
            $uniqueChars = count($charCounts);
            $totalChars = strlen($alphanumericOnly);

            // Extremely low unique character ratio (e.g., "abnadaaca" has 9 chars but only 4 unique chars 'a','b','n','d','c')
            // If unique char ratio < 0.35 and input is short, flag as gibberish
            $ratio = $uniqueChars / $totalChars;
            if ($ratio < 0.35 && $totalChars < 20) {
                return ['valid' => false, 'message' => 'Teks tampak tidak acak atau berkualitas rendah. Tulis kalimat yang lebih bermakna.'];
            }
        }

        return ['valid' => true, 'message' => null];
    }
}
