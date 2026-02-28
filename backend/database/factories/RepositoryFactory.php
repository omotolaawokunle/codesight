<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepositoryFactory extends Factory
{
    public function definition(): array
    {
        $hosts = ['github.com', 'gitlab.com', 'bitbucket.org'];
        $host  = $this->faker->randomElement($hosts);

        return [
            'user_id'         => User::factory(),
            'name'            => $this->faker->unique()->words(3, true),
            'git_url'         => "https://{$host}/{$this->faker->userName}/{$this->faker->slug}",
            'branch'          => 'main',
            'git_token'       => null,
            'indexing_status' => 'pending',
            'total_files'     => null,
            'indexed_files'   => null,
            'total_chunks'    => null,
        ];
    }

    /** Mark the repository as having completed indexing. */
    public function completed(): static
    {
        return $this->state([
            'indexing_status'       => 'completed',
            'total_files'           => 42,
            'indexed_files'         => 42,
            'total_chunks'          => 120,
            'last_indexed_commit'   => 'abc1234def5678901234567890abcdef12345678',
            'indexing_started_at'   => now()->subMinutes(2),
            'indexing_completed_at' => now(),
        ]);
    }

    /** Mark the repository as currently indexing. */
    public function inProgress(): static
    {
        return $this->state([
            'indexing_status'     => 'in_progress',
            'indexing_started_at' => now()->subSeconds(30),
            'total_files'         => 100,
            'indexed_files'       => 30,
        ]);
    }

    /** Mark the repository as failed. */
    public function failed(): static
    {
        return $this->state([
            'indexing_status' => 'failed',
            'indexing_error'  => 'Failed to clone repository: authentication required.',
        ]);
    }
}
