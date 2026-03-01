<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * GitManager handles all Git operations for repository cloning and management.
 *
 * All operations shell out to the system `git` binary so no additional
 * PHP packages are required. Tokens are injected into HTTPS URLs at
 * call-time and never stored in plaintext after CloneTime.
 */
class GitManager
{
    /**
     * Supported Git hosting domains for URL validation.
     */
    private const SUPPORTED_HOSTS = [
        'github.com',
        'gitlab.com',
        'bitbucket.org',
    ];

    /**
     * Clone a Git repository to the given target path.
     *
     * For private repositories, the token is injected into the HTTPS URL
     * using the OAuth2 scheme recognised by GitHub, GitLab, and Bitbucket:
     *   https://oauth2:{token}@github.com/owner/repo.git
     *
     * @param  string       $gitUrl     Public HTTPS repository URL.
     * @param  string       $branch     Branch / ref to check out.
     * @param  string|null  $token      Plaintext personal-access / OAuth token.
     * @param  string       $targetPath Absolute path to clone into.
     *
     * @throws RuntimeException When the clone command fails.
     */
    public function clone(
        string $gitUrl,
        string $branch,
        ?string $token,
        string $targetPath,
    ): void {
        $this->validateUrl($gitUrl);

        // Inject the token into the URL for authenticated clones.
        $cloneUrl = $token ? $this->buildAuthenticatedUrl($gitUrl, $token) : $gitUrl;

        // --depth 1 keeps the clone fast by skipping history.
        $command = sprintf(
            'git clone --branch %s --depth 1 -- %s %s 2>&1',
            escapeshellarg($branch),
            escapeshellarg($cloneUrl),
            escapeshellarg($targetPath),
        );

        Log::info('GitManager: cloning repository', [
            'url'    => $gitUrl, // Log public URL, never the authenticated one.
            'branch' => $branch,
            'target' => $targetPath,
        ]);

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $errorOutput = implode("\n", $output);
            Log::error('GitManager: clone failed', [
                'url'      => $gitUrl,
                'exitCode' => $exitCode,
                'output'   => $errorOutput,
            ]);

            throw new RuntimeException(
                "Failed to clone repository '{$gitUrl}': {$errorOutput}"
            );
        }

        Log::info('GitManager: clone successful', ['target' => $targetPath]);
    }

    /**
     * Retrieve the HEAD commit hash of an already-cloned repository.
     *
     * @param  string $repositoryPath Absolute path to the cloned repository root.
     * @return string The full 40-character SHA-1 hash of HEAD.
     *
     * @throws RuntimeException When the git command fails.
     */
    public function getCommitHash(string $repositoryPath): string
    {
        $command = sprintf(
            'git -C %s rev-parse HEAD 2>&1',
            escapeshellarg($repositoryPath),
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Could not get commit hash for '{$repositoryPath}': " . implode("\n", $output)
            );
        }

        return trim($output[0] ?? '');
    }

    /**
     * Recursively delete a cloned repository directory.
     *
     * This is safe to call even if the path does not exist (idempotent).
     *
     * @param  string $repositoryPath Absolute path to the directory to remove.
     */
    public function cleanup(string $repositoryPath): void
    {
        if (!is_dir($repositoryPath)) {
            return;
        }

        // Ensure the path starts with /tmp/ as a safety guard against
        // accidentally deleting unintended directories.
        if (!str_starts_with(realpath($repositoryPath) ?: '', realpath('/tmp/'))) {
            Log::warning('GitManager: cleanup skipped — path is outside /tmp/', [
                'path' => $repositoryPath,
            ]);
            return;
        }

        $command = sprintf('rm -rf %s', escapeshellarg($repositoryPath));
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::warning('GitManager: cleanup failed', [
                'path'   => $repositoryPath,
                'output' => implode("\n", $output),
            ]);
        } else {
            Log::info('GitManager: cleanup complete', ['path' => $repositoryPath]);
        }
    }

    /**
     * Validate that a Git URL refers to a supported hosting provider.
     *
     * Accepted formats:
     *   https://github.com/owner/repo
     *   https://github.com/owner/repo.git
     *
     * @throws RuntimeException For unsupported or malformed URLs.
     */
    public function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Invalid URL: '{$url}'");
        }

        $parsed = parse_url($url);
        $host   = strtolower($parsed['host'] ?? '');

        if (!in_array($host, self::SUPPORTED_HOSTS, true)) {
            $supported = implode(', ', self::SUPPORTED_HOSTS);
            throw new RuntimeException(
                "Unsupported Git host '{$host}'. Supported hosts: {$supported}"
            );
        }

        if (($parsed['scheme'] ?? '') !== 'https') {
            throw new RuntimeException(
                "Only HTTPS URLs are supported. Got: '{$url}'"
            );
        }
    }

    /**
     * Inject an OAuth token into an HTTPS Git URL.
     *
     * The resulting URL is used only for the clone command and is never
     * persisted or logged.
     */
    private function buildAuthenticatedUrl(string $url, string $token): string
    {
        $parsed = parse_url($url);

        return sprintf(
            '%s://oauth2:%s@%s%s',
            $parsed['scheme'],
            urlencode($token),
            $parsed['host'],
            $parsed['path'] ?? '',
        );
    }
}
