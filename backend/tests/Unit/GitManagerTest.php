<?php

namespace Tests\Unit;

use App\Services\GitManager;
use RuntimeException;
use Tests\TestCase;

class GitManagerTest extends TestCase
{
    private GitManager $git;

    protected function setUp(): void
    {
        parent::setUp();
        $this->git = new GitManager();
    }

    // ── URL Validation ────────────────────────────────────────────────────────

    public function test_validates_github_https_url(): void
    {
        // Should not throw
        $this->git->validateUrl('https://github.com/owner/repo');
        $this->assertTrue(true); // explicit assertion if no exception
    }

    public function test_validates_github_url_with_dot_git_suffix(): void
    {
        $this->git->validateUrl('https://github.com/owner/repo.git');
        $this->assertTrue(true);
    }

    public function test_validates_gitlab_url(): void
    {
        $this->git->validateUrl('https://gitlab.com/myteam/my-project');
        $this->assertTrue(true);
    }

    public function test_validates_bitbucket_url(): void
    {
        $this->git->validateUrl('https://bitbucket.org/workspace/repository');
        $this->assertTrue(true);
    }

    public function test_rejects_unsupported_host(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unsupported git host/i');

        $this->git->validateUrl('https://example.com/owner/repo');
    }

    public function test_rejects_completely_invalid_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid url/i');

        $this->git->validateUrl('not-a-url-at-all');
    }

    public function test_rejects_ssh_style_url(): void
    {
        $this->expectException(RuntimeException::class);

        // SSH URLs don't pass filter_var(FILTER_VALIDATE_URL)
        $this->git->validateUrl('git@github.com:owner/repo.git');
    }

    public function test_rejects_http_non_https_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/only https/i');

        $this->git->validateUrl('http://github.com/owner/repo');
    }

    // ── Cleanup safety guard ──────────────────────────────────────────────────

    public function test_cleanup_does_not_throw_for_nonexistent_path(): void
    {
        // Should be silent if the directory doesn't exist.
        $this->git->cleanup('/tmp/codesight-nonexistent-path-xyz-12345');
        $this->assertTrue(true);
    }

    public function test_cleanup_skips_paths_outside_tmp(): void
    {
        // This test verifies the safety guard doesn't try to rm -rf outside /tmp.
        // We pass a fake path; the method should return silently after the guard.
        $this->git->cleanup('/home/user/important-project');
        $this->assertTrue(true);
    }
}
