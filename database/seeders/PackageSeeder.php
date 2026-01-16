<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;
use App\Models\User;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first(); // Assign to first user (Admin)

        // Instance Packages
        $instances = [
            [
                'name' => 'Starter Instance',
                'type' => 'instance',
                'cpu_limit' => '1',
                'ram_limit' => '1', // 1 GB
                'disk_limit' => '10',
            ],
            [
                'name' => 'Pro Instance',
                'type' => 'instance',
                'cpu_limit' => '2',
                'ram_limit' => '4',
                'disk_limit' => '25',
            ],
        ];

        foreach ($instances as $data) {
            Package::firstOrCreate(
                ['name' => $data['name'], 'type' => 'instance'],
                array_merge($data, ['user_id' => $admin ? $admin->id : 1])
            );
        }

        // Reseller Packages
        $resellers = [
            [
                'name' => 'Bronze Reseller',
                'type' => 'reseller',
                'cpu_limit' => '10', // Total CPU
                'ram_limit' => '16', // Total RAM (GB)
                'disk_limit' => '100', // Total Disk
                'instance_count' => 5,
            ],
            [
                'name' => 'Silver Reseller',
                'type' => 'reseller',
                'cpu_limit' => '20',
                'ram_limit' => '32',
                'disk_limit' => '250',
                'instance_count' => 15,
            ],
            [
                'name' => 'Gold Reseller',
                'type' => 'reseller',
                'cpu_limit' => '50',
                'ram_limit' => '64',
                'disk_limit' => '500',
                'instance_count' => 50,
            ],
        ];

        foreach ($resellers as $data) {
            Package::firstOrCreate(
                ['name' => $data['name'], 'type' => 'reseller'],
                array_merge($data, ['user_id' => $admin ? $admin->id : 1])
            );
        }
    }
}
