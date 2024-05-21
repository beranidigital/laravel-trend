<?php

namespace Flowframe\Trend\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class SimpleModelFactory extends Factory
{
    protected $model = SimpleModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'created_at' => $this->faker->dateTimeThisYear->format('Y-m-d H:i:s'),
            'updated_at' => $this->faker->dateTimeThisYear->format('Y-m-d H:i:s'),
        ];
    }
}
