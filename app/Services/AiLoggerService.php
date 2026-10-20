<?php

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Http\Client\Response;

class AiLoggerService
{
    /**
     * Get the configured model pool prioritized by RPD quota.
     */
    public static function getModelPool(): array
    {
        return config('services.gemini.models', [
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-3-flash',
            'gemini-2.5-flash',
        ]);
    }

    /**
     * Get primary model name.
     */
    public static function getPrimaryModel(): string
    {
        return config('services.gemini.primary_model', 'gemini-3.5-flash-lite');
    }

    /**
     * Record an AI API request log.
     */
    public static function log(
        ?int $userId,
        string $feature,
        ?string $promptText = null,
        ?string $responseText = null,
        ?string $model = null,
        int $promptTokens = 0,
        int $completionTokens = 0,
        int $executionTimeMs = 0,
        string $status = 'success',
        ?string $errorMessage = null
    ): AiUsageLog {
        $model = $model ?: self::getPrimaryModel();
        $total = $promptTokens + $completionTokens;

        return AiUsageLog::create([
            'user_id' => $userId,
            'feature' => $feature,
            'prompt_text' => $promptText,
            'response_text' => $responseText,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $total,
            'execution_time_ms' => $executionTimeMs,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Log directly from a Gemini HTTP Response.
     */
    public static function logResponse(
        ?int $userId,
        string $feature,
        string $model,
        Response $response,
        int $executionTimeMs,
        ?string $promptText = null,
        ?string $responseText = null
    ): AiUsageLog {
        $status = 'success';
        $errorMessage = null;

        if ($response->status() === 429) {
            $status = 'rate_limited';
            $errorMessage = 'Rate limit (429) exceeded';
        } elseif (!$response->successful()) {
            $status = 'error';
            $errorMessage = "HTTP {$response->status()}: " . mb_substr($response->body(), 0, 255);
        }

        if ($responseText === null && $response->successful()) {
            $responseText = $response->json('candidates.0.content.parts.0.text');
        }

        $promptTokens = (int) $response->json('usageMetadata.promptTokenCount', 0);
        $completionTokens = (int) $response->json('usageMetadata.candidatesTokenCount', 0);
        
        // Fallback token approximation if usageMetadata is absent
        if ($promptTokens === 0 && $promptText) {
            $promptTokens = (int) ceil(mb_strlen($promptText) / 4);
        }
        if ($completionTokens === 0 && $responseText) {
            $completionTokens = (int) ceil(mb_strlen($responseText) / 4);
        }

        return self::log(
            userId: $userId,
            feature: $feature,
            promptText: $promptText,
            responseText: $responseText,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            executionTimeMs: $executionTimeMs,
            status: $status,
            errorMessage: $errorMessage
        );
    }
}
