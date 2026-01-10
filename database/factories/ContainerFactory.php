<?php

namespace Database\Factories;

use App\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContainerFactory extends Factory
{
    protected $model = Container::class;

    public function definition()
    {
        return [
            'name' => $this->faker->slug,
            'user_id' => 1,
            'image_tag' => 'latest',
            'port' => $this->faker->numberBetween(1000, 9000),
            // 'status' => 'running', // REMOVED as column does not exist
            'docker_id' => $this->faker->uuid,
        ];
    }
}
