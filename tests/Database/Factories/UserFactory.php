<?php

namespace Syriable\Messenger\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\Messenger\Tests\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}
