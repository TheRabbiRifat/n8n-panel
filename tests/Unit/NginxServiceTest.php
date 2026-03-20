<?php

namespace Tests\Unit;

use App\Services\NginxService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class NginxServiceTest extends TestCase
{
    public function test_create_vhost_runs_correct_commands()
    {
        Process::fake();

        $service = new NginxService();
        $service->createVhost('example.com', 8080);

        // Verification of commands using array arguments
        Process::assertRan(function ($process) {
            return is_array($process->command) &&
                   $process->command[0] === 'tee' &&
                   $process->command[1] === '/etc/nginx/sites-available/example.com';
        });

        Process::assertRan(function ($process) {
            return $process->command === ['ln', '-sf', '/etc/nginx/sites-available/example.com', '/etc/nginx/sites-enabled/'];
        });

        Process::assertRan(function ($process) {
            return $process->command === ['systemctl', 'reload', 'nginx'];
        });
    }

    public function test_secure_vhost_runs_correct_commands()
    {
        Process::fake();

        $service = new NginxService();
        $service->secureVhost('example.com');

        Process::assertRan(function ($process) {
            return is_array($process->command) &&
                   $process->command[0] === 'certbot' &&
                   in_array('-d', $process->command) &&
                   in_array('example.com', $process->command);
        });
    }

    public function test_remove_vhost_runs_correct_commands()
    {
        Process::fake();

        $service = new NginxService();
        $service->removeVhost('example.com');

        Process::assertRan(function ($process) {
            return $process->command === ['rm', '-f', '/etc/nginx/sites-available/example.com'];
        });

        Process::assertRan(function ($process) {
            return $process->command === ['rm', '-f', '/etc/nginx/sites-enabled/example.com'];
        });

        Process::assertRan(function ($process) {
            return $process->command === ['certbot', 'delete', '--cert-name', 'example.com', '--non-interactive'];
        });

        Process::assertRan(function ($process) {
            return $process->command === ['systemctl', 'reload', 'nginx'];
        });
    }
}
