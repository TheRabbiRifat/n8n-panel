<?php

namespace Tests\Unit;

use App\Services\NginxService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class NginxServiceTest extends TestCase
{
    protected NginxService $nginxService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nginxService = new NginxService();
    }

    public function test_create_vhost_runs_correct_commands()
    {
        Process::fake();

        $domain = 'example.com';
        $port = 8080;

        $this->nginxService->createVhost($domain, $port);

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) && $process->command[0] === 'tee' && $process->command[1] === "/var/lib/n8n/nginx/$domain.conf";
        });

        Process::assertRan(function ($process) {
            return is_array($process->command) && $process->command === ['systemctl', 'reload', 'nginx'];
        });
    }

    public function test_secure_vhost_runs_correct_commands()
    {
        Process::fake();

        $domain = 'example.com';

        $this->nginxService->secureVhost($domain);

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) && $process->command[0] === 'certbot' && in_array('--nginx', $process->command);
        });
    }

    public function test_remove_vhost_runs_correct_commands()
    {
        Process::fake();

        $domain = 'example.com';

        $this->nginxService->removeVhost($domain);

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) && $process->command === ['rm', '-f', "/var/lib/n8n/nginx/$domain.conf"];
        });

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) && $process->command === ['rm', '-f', "/etc/nginx/sites-available/$domain"];
        });

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) && $process->command === ['rm', '-f', "/etc/nginx/sites-enabled/$domain"];
        });

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) && $process->command[0] === 'certbot' && in_array('delete', $process->command);
        });

        Process::assertRan(function ($process) {
            return is_array($process->command) && $process->command === ['systemctl', 'reload', 'nginx'];
        });
    }
}
