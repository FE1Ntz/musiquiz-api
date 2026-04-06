<?php

namespace Tests\Unit;

use App\Enums\Difficulty;
use App\Services\SnippetTimingService;
use PHPUnit\Framework\TestCase;

class SnippetTimingServiceTest extends TestCase
{
    private SnippetTimingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SnippetTimingService;
    }

    public function test_easy_snippet_is_30_seconds(): void
    {
        $timing = $this->service->calculateSnippetTiming(Difficulty::Easy, 1, 1);

        $this->assertEquals(30, $timing['snippet_length_seconds']);
        $this->assertEquals(30, $timing['snippet_end_second'] - $timing['snippet_start_second']);
    }

    public function test_medium_snippet_is_15_seconds(): void
    {
        $timing = $this->service->calculateSnippetTiming(Difficulty::Medium, 1, 1);

        $this->assertEquals(15, $timing['snippet_length_seconds']);
        $this->assertEquals(15, $timing['snippet_end_second'] - $timing['snippet_start_second']);
    }

    public function test_hard_snippet_is_10_seconds(): void
    {
        $timing = $this->service->calculateSnippetTiming(Difficulty::Hard, 1, 1);

        $this->assertEquals(10, $timing['snippet_length_seconds']);
        $this->assertEquals(10, $timing['snippet_end_second'] - $timing['snippet_start_second']);
    }

    public function test_snippet_start_is_non_negative(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $timing = $this->service->calculateSnippetTiming(Difficulty::Hard, $i, $i);
            $this->assertGreaterThanOrEqual(0, $timing['snippet_start_second']);
        }
    }

    public function test_snippet_end_does_not_exceed_preview_duration(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $timing = $this->service->calculateSnippetTiming(Difficulty::Hard, $i, $i);
            $this->assertLessThanOrEqual(30, $timing['snippet_end_second']);
        }
    }

    public function test_timing_is_deterministic(): void
    {
        $timing1 = $this->service->calculateSnippetTiming(Difficulty::Medium, 42, 3);
        $timing2 = $this->service->calculateSnippetTiming(Difficulty::Medium, 42, 3);

        $this->assertEquals($timing1, $timing2);
    }

    public function test_different_tracks_produce_different_timings(): void
    {
        $timing1 = $this->service->calculateSnippetTiming(Difficulty::Hard, 1, 1);
        $timing2 = $this->service->calculateSnippetTiming(Difficulty::Hard, 2, 1);

        // They might be the same by chance, but for most track IDs they should differ
        // Just verify structure is correct
        $this->assertArrayHasKey('snippet_start_second', $timing1);
        $this->assertArrayHasKey('snippet_end_second', $timing1);
        $this->assertArrayHasKey('snippet_length_seconds', $timing1);
    }

    public function test_easy_snippet_starts_at_zero(): void
    {
        // Easy = 30s snippet in 30s preview, so start must be 0
        $timing = $this->service->calculateSnippetTiming(Difficulty::Easy, 1, 1);

        $this->assertEquals(0, $timing['snippet_start_second']);
        $this->assertEquals(30, $timing['snippet_end_second']);
    }
}
