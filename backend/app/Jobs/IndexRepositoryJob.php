<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Services\GitManager;
use App\Services\Indexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class IndexRepositoryJob implements ShouldQueue
{
    use Queueable;

    /** Retry up to 3 times before marking as permanently failed. */
    public int $tries = 3;

    /** Maximum 1 hour for the entire indexing operation. */
    public int $timeout = 3600;

    /**
     * Directories to skip when scanning for source files.
     * These are never useful to index and can be very large.
     */
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

    /**
     * File extensions supported by the indexing pipeline (Batches 3 & 4).
     * Only these files are counted and later parsed.
     */
    private const SUPPORTED_EXTENSIONS = [
        'py', 'js', 'jsx', 'ts', 'tsx', 'java', 'php'
    ];

    /** Maximum file size to process (1 MB). */
    private const MAX_FILE_SIZE_BYTES = 1_048_576;

    public function __construct(
        public readonly Repository $repository,
    ) {}

    /**
     * Execute the full indexing pipeline:
     * 1. Clone the repository
     * 2. Scan and filter eligible source files
     * 3. Record the commit hash and total file count
     * 4. Run AST parsing, embedding, and vector storage via Indexer
     * 5. Mark as completed
     */
    public function handle(GitManager $git, Indexer $indexer): void
    {
        $repoId  = $this->repository->id;
        $clonePath = "/tmp/repos/{$repoId}";

        Log::info("IndexRepositoryJob: starting", ['repository_id' => $repoId]);

        // ── Step 1: Mark as in-progress ─────────────────────────────────────
        $this->repository->update([
            'indexing_status'     => 'in_progress',
            'indexing_started_at' => now(),
            'indexing_error'      => null,
        ]);

        try {
            // ── Step 2: Clone repository ─────────────────────────────────────
            // Decrypt the token on-the-fly; it is never stored in plaintext.
            $token = $this->repository->git_token
                ? decrypt($this->repository->git_token)
                : null;

            // Clean up any leftover directory from a previous attempt.
            $git->cleanup($clonePath);

            $git->clone(
                gitUrl:     $this->repository->git_url,
                branch:     $this->repository->branch,
                token:      $token,
                targetPath: $clonePath,
            );

            // ── Step 3: Record HEAD commit hash ──────────────────────────────
            $commitHash = $git->getCommitHash($clonePath);

            // ── Step 4: Scan and count eligible source files ─────────────────
            $files = $this->scanFiles($clonePath);
            $totalFiles = count($files);

            Log::info("IndexRepositoryJob: scan complete", [
                'repository_id' => $repoId,
                'total_files'   => $totalFiles,
            ]);

            // ── Step 5: Record commit hash and total file count ───────────────
            $this->repository->update([
                'total_files'         => $totalFiles,
                'last_indexed_commit' => $commitHash,
            ]);

            // ── Step 6: Run full indexing pipeline ───────────────────────────
            // Parses files via AST service, generates Gemini embeddings,
            // stores vectors in Qdrant, and persists metadata to PostgreSQL.
            $indexer->run($this->repository, $files);

            // ── Step 7: Mark as completed ─────────────────────────────────────
            $this->repository->update([
                'indexing_status'       => 'completed',
                'indexing_completed_at' => now(),
            ]);

            Log::info("IndexRepositoryJob: completed", [
                'repository_id' => $repoId,
                'commit'        => $commitHash,
                'files_found'   => $totalFiles,
            ]);

        } catch (Throwable $e) {
            Log::error("IndexRepositoryJob: failed", [
                'repository_id' => $repoId,
                'error'         => $e->getMessage(),
            ]);

            // Mark as failed so the UI shows a meaningful error.
            $this->repository->update([
                'indexing_status' => 'failed',
                'indexing_error'  => $e->getMessage(),
            ]);

            // Re-throw so the queue knows the job failed and can retry.
            throw $e;

        } finally {
            // Always clean up temp files, even on failure, to avoid disk bloat.
            $git->cleanup($clonePath);
        }
    }

    /**
     * Called by the queue after all retry attempts are exhausted.
     *
     * Ensures the repository is always left in a terminal state even if
     * the handle() method itself never ran (e.g. job deserialization error).
     */
    public function failed(Throwable $exception): void
    {
        $this->repository->update([
            'indexing_status' => 'failed',
            'indexing_error'  => $exception->getMessage(),
        ]);
    }

    /**
     * Recursively scan a directory for indexable source files.
     *
     * Files are excluded when they:
     *   - Reside in an ignored directory (node_modules, .git, etc.)
     *   - Have an unsupported extension
     *   - Exceed the 1 MB size limit
     *
     * @return string[] List of absolute file paths.
     */
    private function scanFiles(string $rootPath): array
    {
        $results = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $rootPath,
                \RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            // Skip directories; we only collect files.
            if ($file->isDir()) {
                // Check if any part of the path matches an ignored directory name.
                $parts = explode(DIRECTORY_SEPARATOR, $file->getPathname());
                foreach ($parts as $part) {
                    if (in_array($part, self::IGNORED_DIRECTORIES, true)) {
                        $iterator->getInnerIterator()->rewind();
                        // Use RecursiveIteratorIterator to skip the subtree.
                        /** @var \RecursiveIteratorIterator $iterator */
                        $iterator->callHasChildren()
                            && $iterator->getSubIterator()->rewind();
                        break;
                    }
                }
                continue;
            }

            // Check if any ancestor directory is in the ignore list.
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

            // Filter by supported extension.
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            // Skip files that are too large (binary-like or minified).
            if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
                Log::debug("IndexRepositoryJob: skipping large file", [
                    'file' => $file->getPathname(),
                    'size' => $file->getSize(),
                ]);
                continue;
            }

            $results[] = $file->getPathname();
        }

        return $results;
    }
}
