<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'status' => 'todo',
            'source' => 'ai',
            'chat_message_id' => null,
            'done_at' => null,
        ];
    }
}
