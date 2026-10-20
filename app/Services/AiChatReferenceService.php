<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatReferenceService
{
    /**
     * Known AI platforms and signatures
     */
    protected static array $providers = [
        'gemini' => [
            'name' => 'Gemini',
            'domains' => ['gemini.google.com', 'share.gemini.google', 'gemini.google', 'bard.google.com'],
            'badge_class' => 'text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200/80 dark:bg-indigo-950/50 dark:text-indigo-300 dark:border-indigo-800/50',
            'icon' => 'gemini',
        ],
        'chatgpt' => [
            'name' => 'ChatGPT',
            'domains' => ['chatgpt.com', 'chat.openai.com', 'openai.com'],
            'badge_class' => 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/80 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/50',
            'icon' => 'chatgpt',
        ],
        'claude' => [
            'name' => 'Claude',
            'domains' => ['claude.ai', 'anthropic.com'],
            'badge_class' => 'text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200/80 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/50',
            'icon' => 'claude',
        ],
        'perplexity' => [
            'name' => 'Perplexity',
            'domains' => ['perplexity.ai'],
            'badge_class' => 'text-teal-700 bg-teal-50 hover:bg-teal-100 border border-teal-200/80 dark:bg-teal-950/50 dark:text-teal-300 dark:border-teal-800/50',
            'icon' => 'perplexity',
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'domains' => ['deepseek.com', 'chat.deepseek.com'],
            'badge_class' => 'text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200/80 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800/50',
            'icon' => 'deepseek',
        ],
        'copilot' => [
            'name' => 'Copilot',
            'domains' => ['copilot.microsoft.com'],
            'badge_class' => 'text-cyan-700 bg-cyan-50 hover:bg-cyan-100 border border-cyan-200/80 dark:bg-cyan-950/50 dark:text-cyan-300 dark:border-cyan-800/50',
            'icon' => 'copilot',
        ],
        'grok' => [
            'name' => 'Grok',
            'domains' => ['x.ai', 'grok.com'],
            'badge_class' => 'text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700',
            'icon' => 'grok',
        ],
        'poe' => [
            'name' => 'Poe',
            'domains' => ['poe.com'],
            'badge_class' => 'text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200/80 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800/50',
            'icon' => 'poe',
        ],
    ];

    /**
     * Check if a URL belongs to a YouTube link
     */
    public static function isYouTubeUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $host = strtolower(parse_url(static::normalizeUrl($url), PHP_URL_HOST) ?? '');
        return str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be');
    }

    /**
     * Check if a URL belongs to an AI chat service
     */
    public static function isAiChatUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $host = strtolower(parse_url(static::normalizeUrl($url), PHP_URL_HOST) ?? '');
        if (empty($host)) {
            return false;
        }

        foreach (static::$providers as $provider) {
            foreach ($provider['domains'] as $domain) {
                if (str_contains($host, $domain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize URL with scheme if missing
     */
    public static function normalizeUrl(?string $url): string
    {
        $url = trim($url ?? '');
        if ($url === '') {
            return '';
        }

        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * Parse and return rich metadata for an AI chat URL
     */
    public static function parseAiChat(?string $url): ?array
    {
        $normalized = static::normalizeUrl($url);
        if ($normalized === '') {
            return null;
        }

        $host = strtolower(parse_url($normalized, PHP_URL_HOST) ?? '');

        foreach (static::$providers as $key => $provider) {
            foreach ($provider['domains'] as $domain) {
                if (str_contains($host, $domain)) {
                    return [
                        'provider' => $key,
                        'name' => $provider['name'],
                        'label' => $provider['name'] . ' Chat',
                        'badge_class' => $provider['badge_class'],
                        'icon' => $provider['icon'],
                        'url' => $normalized,
                        'host' => $host,
                    ];
                }
            }
        }

        // Generic AI chat fallback for other share links
        return [
            'provider' => 'generic_ai',
            'name' => 'Chat AI',
            'label' => 'Referensi AI',
            'badge_class' => 'text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200/80 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800/50',
            'icon' => 'sparkles',
            'url' => $normalized,
            'host' => $host,
        ];
    }

    /**
     * Fetch metadata (title, topic, summary) from an external AI chat share URL.
     * Caches result to avoid redundant network queries.
     */
    public static function fetchChatMetadata(?string $url): ?array
    {
        $normalized = static::normalizeUrl($url);
        if (empty($normalized) || !static::isAiChatUrl($normalized)) {
            return null;
        }

        $cacheKey = 'ai_chat_meta_' . md5($normalized);
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($normalized) {
            $parsed = static::parseAiChat($normalized);
            $providerName = $parsed['name'] ?? 'AI';

            try {
                $response = Http::timeout(4)
                    ->connectTimeout(2)
                    ->withHeaders([
                        'User-Agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.9',
                    ])
                    ->get($normalized);

                if (!$response->successful()) {
                    return [
                        'provider' => $providerName,
                        'title' => null,
                        'topic' => null,
                        'description' => null,
                        'url' => $normalized,
                    ];
                }

                $html = $response->body();
                $title = null;

                $isGenericTagline = function (?string $t): bool {
                    if (empty($t)) return true;
                    $lower = strtolower(trim($t));
                    return str_contains($lower, 'direct access to google ai')
                        || str_contains($lower, 'akses langsung ke ai google')
                        || str_contains($lower, 'created with gemini')
                        || str_contains($lower, 'dibuat dengan gemini')
                        || str_contains($lower, 'a conversational ai tool')
                        || $lower === 'gemini'
                        || $lower === 'chatgpt';
                };

                // 1. Try <title> tag first or <meta property="og:title">
                $extractedTitle = null;
                if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
                    $candidate = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (!$isGenericTagline($candidate)) {
                        $extractedTitle = $candidate;
                    }
                }

                // 2. Try <meta property="og:title"> if title tag didn't yield a specific topic
                if (empty($extractedTitle) && preg_match('/<meta[^>]*property=[\x22\x27]og:title[\x22\x27][^>]*content=[\x22\x27](.*?)[\x22\x27]/si', $html, $m)) {
                    $candidate = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (!$isGenericTagline($candidate)) {
                        $extractedTitle = $candidate;
                    }
                }

                $title = $extractedTitle;

                $cleanTopic = null;
                if (!empty($title)) {
                    // Remove brand prefixes like "‎Gemini - ", "ChatGPT - ", "Claude - "
                    $cleanTopic = trim(preg_replace('/^(?:‎?Gemini|ChatGPT|Claude|Perplexity|DeepSeek)\s*[-–—:|]\s*/iu', '', $title));
                    if ($isGenericTagline($cleanTopic)) {
                        $cleanTopic = null;
                    }
                }

                // 3. Try <meta property="og:description">
                $description = null;
                if (preg_match('/<meta[^>]*(?:property=[\x22\x27]og:description[\x22\x27]|name=[\x22\x27]description[\x22\x27])[^>]*content=[\x22\x27](.*?)[\x22\x27]/si', $html, $m)) {
                    $desc = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (!$isGenericTagline($desc) && !empty($desc)) {
                        $description = $desc;
                    }
                }

                return [
                    'provider' => $providerName,
                    'title' => $title,
                    'topic' => $cleanTopic,
                    'description' => $description,
                    'url' => $normalized,
                ];
            } catch (\Exception $e) {
                Log::warning("Failed to fetch AI chat metadata for {$normalized}: " . $e->getMessage());
                return [
                    'provider' => $providerName,
                    'title' => null,
                    'topic' => null,
                    'description' => null,
                    'url' => $normalized,
                ];
            }
        });
    }
}
