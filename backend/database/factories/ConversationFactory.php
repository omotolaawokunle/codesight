<?php

namespace Database\Factories;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'repository_id' => Repository::factory(),
            'title'         => fake()->sentence(4),
        ];
    }
}
