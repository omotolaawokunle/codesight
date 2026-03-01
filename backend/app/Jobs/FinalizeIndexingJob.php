<?php

namespace App\Jobs;

use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FinalizeIndexingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $repositoryId,
    ) {}

    public function handle(): void
    {
        $repository = Repository::find($this->repositoryId);

        if (!$repository) {
            Log::warning('FinalizeIndexingJob: repository not found', [
                'repository_id' => $this->repositoryId,
            ]);
            return;
        }

        $repository->update([
            'indexing_status'       => 'completed',
            'indexing_completed_at' => now(),
        ]);

        Log::info('FinalizeIndexingJob: repository indexing completed', [
            'repository_id' => $this->repositoryId,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('FinalizeIndexingJob: failed to finalize', [
            'repository_id' => $this->repositoryId,
            'error'         => $exception->getMessage(),
        ]);

        Repository::find($this->repositoryId)?->update([
            'indexing_status' => 'failed',
            'indexing_error'  => $exception->getMessage(),
        ]);
    }
}
