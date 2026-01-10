<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition()
    {
        return [
            'name' => 'Test Package',
            'user_id' => 1,
            'cpu_limit' => 1.5,
            'ram_limit' => 2.5,
            'disk_limit' => 20.5,
        ];
    }
}
