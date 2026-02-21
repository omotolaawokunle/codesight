<?php

namespace App\Jobs;

use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexRepositoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public readonly Repository $repository
    ) {}

    public function handle(): void
    {
        // TODO: Implement indexing pipeline in MVP
        // 1. Update status to 'in_progress'
        // 2. Clone repository via GitManager
        // 3. Scan and filter files (skip binary, >1MB)
        // 4. Send files to AST service in batches
        // 5. Generate embeddings via EmbeddingService
        // 6. Store vectors in Qdrant via VectorDBService
        // 7. Store chunk metadata in PostgreSQL
        // 8. Update status to 'completed'
        // On failure: update status to 'failed' with error message
    }

    public function failed(\Throwable $exception): void
    {
        $this->repository->update([
            'indexing_status' => 'failed',
            'indexing_error' => $exception->getMessage(),
        ]);
    }
}
