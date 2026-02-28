<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role'            => fake()->randomElement(['user', 'assistant']),
            'content'         => fake()->paragraph(),
            'metadata'        => null,
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => 'user', 'metadata' => null]);
    }

    public function assistant(): static
    {
        return $this->state([
            'role'     => 'assistant',
            'metadata' => ['sources' => [], 'usage' => ['input_tokens' => 100, 'output_tokens' => 200]],
        ]);
    }
}
