<?php

namespace App\Services;

use App\Enums\Difficulty;

class SnippetTimingService
{
    /**
     * The maximum duration of a Deezer preview in seconds.
     */
    private const int PREVIEW_DURATION_SECONDS = 30;

    /**
     * Calculate deterministic snippet timing for a given round.
     *
     * Uses the track ID and round number as a seed for reproducible results.
     *
     * @return array{snippet_start_second: int, snippet_end_second: int, snippet_length_seconds: int}
     */
    public function calculateSnippetTiming(Difficulty $difficulty, int $trackId, int $roundNumber): array
    {
        $snippetLength = $difficulty->snippetLengthSeconds();
        $maxStart = max(0, self::PREVIEW_DURATION_SECONDS - $snippetLength);

        // Deterministic start position based on track ID and round number
        $seed = crc32($trackId.'-'.$roundNumber);
        $startSecond = abs($seed) % ($maxStart + 1);

        return [
            'snippet_start_second' => $startSecond,
            'snippet_end_second' => $startSecond + $snippetLength,
            'snippet_length_seconds' => $snippetLength,
        ];
    }
}
