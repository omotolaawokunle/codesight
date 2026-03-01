<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Services\GitManager;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloneRepositoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    private const IGNORED_DIRECTORIES = [
        'node_modules',
        '.git',
        '__pycache__',
        'venv',
        '.venv',
        'dist',
        'build',
        'vendor',
        '.idea',
        '.vscode',
    ];

    private const SUPPORTED_EXTENSIONS = [
        'py', 'js', 'jsx', 'ts', 'tsx', 'java', 'php',
    ];

    private const MAX_FILE_SIZE_BYTES = 1_048_576;

    /** How many file paths to include per indexing job. */
    private const FILES_PER_BATCH = 10;

    public function __construct(
        public readonly Repository $repository,
    ) {}

    public function handle(GitManager $git): void
    {
        $repoId    = $this->repository->id;
        $clonePath = "/tmp/repos/{$repoId}";

        Log::info('CloneRepositoryJob: starting', ['repository_id' => $repoId]);

        $this->repository->update([
            'indexing_status'     => 'in_progress',
            'indexing_started_at' => now(),
            'indexing_error'      => null,
        ]);

        try {
            $token = $this->repository->git_token
                ? decrypt($this->repository->git_token)
                : null;

            $git->cleanup($clonePath);

            $git->clone(
                gitUrl:     $this->repository->git_url,
                branch:     $this->repository->branch,
                token:      $token,
                targetPath: $clonePath,
            );

            $commitHash = $git->getCommitHash($clonePath);

            // Scan files lazily and emit batch jobs without holding all paths in memory.
            $indexJobs  = [];
            $batch      = [];
            $totalFiles = 0;

            foreach ($this->scanFiles($clonePath) as $filePath) {
                $batch[] = $filePath;
                $totalFiles++;

                if (count($batch) >= self::FILES_PER_BATCH) {
                    $indexJobs[] = new IndexFileBatchJob($this->repository, $batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $indexJobs[] = new IndexFileBatchJob($this->repository, $batch);
            }

            $this->repository->update([
                'total_files'         => $totalFiles,
                'last_indexed_commit' => $commitHash,
            ]);

            Log::info('CloneRepositoryJob: scan complete', [
                'repository_id' => $repoId,
                'total_files'   => $totalFiles,
                'batch_jobs'    => count($indexJobs),
            ]);

            if (empty($indexJobs)) {
                $this->repository->update([
                    'indexing_status'       => 'completed',
                    'indexing_completed_at' => now(),
                ]);
                return;
            }

            $repositoryId = $this->repository->id;

            Bus::batch([...$indexJobs])
                ->then(function (Batch $batch) use ($repositoryId) {
                    FinalizeIndexingJob::dispatch($repositoryId);
                })
                ->catch(function (Batch $batch, Throwable $e) use ($repositoryId) {
                    Log::error('CloneRepositoryJob: batch failed', [
                        'repository_id' => $repositoryId,
                        'error'         => $e->getMessage(),
                    ]);

                    Repository::find($repositoryId)?->update([
                        'indexing_status' => 'failed',
                        'indexing_error'  => $e->getMessage(),
                    ]);
                })
                ->allowFailures()
                ->dispatch();

        } catch (Throwable $e) {
            Log::error('CloneRepositoryJob: failed', [
                'repository_id' => $repoId,
                'error'         => $e->getMessage(),
            ]);

            $this->repository->update([
                'indexing_status' => 'failed',
                'indexing_error'  => $e->getMessage(),
            ]);

            $git->cleanup($clonePath);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->repository->update([
            'indexing_status' => 'failed',
            'indexing_error'  => $exception->getMessage(),
        ]);
    }

    /**
     * Yield file paths one at a time to avoid loading all paths into memory.
     *
     * @return \Generator<string>
     */
    private function scanFiles(string $rootPath): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $rootPath,
                \RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $pathParts    = explode(DIRECTORY_SEPARATOR, $relativePath);

            $shouldIgnore = false;
            foreach ($pathParts as $part) {
                if (in_array($part, self::IGNORED_DIRECTORIES, true)) {
                    $shouldIgnore = true;
                    break;
                }
            }

            if ($shouldIgnore) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
                Log::debug('CloneRepositoryJob: skipping large file', [
                    'file' => $file->getPathname(),
                    'size' => $file->getSize(),
                ]);
                continue;
            }

            yield $file->getPathname();
        }
    }
}
