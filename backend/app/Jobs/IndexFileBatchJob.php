<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Services\Indexer;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class IndexFileBatchJob implements ShouldQueue
{
    use Queueable, Batchable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly Repository $repository,
        public readonly array $filePaths,
    ) {}

    public function handle(Indexer $indexer): void
    {
        Log::info('IndexFileBatchJob: starting', [
            'repository_id' => $this->repository->id,
            'file_count'    => count($this->filePaths),
        ]);

        $indexer->runBatch($this->repository, $this->filePaths);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('IndexFileBatchJob: permanently failed', [
            'repository_id' => $this->repository->id,
            'error'         => $exception->getMessage(),
            'files'         => $this->filePaths,
        ]);
    }
}
