<?php

namespace Syriable\Messenger\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\Messenger\Tests\Models\Agent;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}
