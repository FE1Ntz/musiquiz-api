<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Services\ArtistImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
#[MaxExceptions(3)]
class ImportArtistCatalogJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Artist $artist,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ArtistImportService $importService): void
    {
        $importService->importCatalog($this->artist);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('ImportArtistCatalogJob failed permanently', [
            'deezer_artist_id' => $this->artist->deezer_id,
            'artist_name' => $this->artist->name,
            'error' => $exception?->getMessage(),
        ]);
    }
}
