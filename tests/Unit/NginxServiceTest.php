<?php

namespace Tests\Unit;

use App\Services\NginxService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class NginxServiceTest extends TestCase
{
    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_create_vhost()
    {
        Process::fake();

        $service = new NginxService();
        $domain = "example.com; echo injected";
        $port = 8080;

        $service->createVhost($domain, $port);

        // Assert that the commands were run with array arguments
        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) &&
                   $process->command[0] === 'tee' &&
                   $process->command[1] === "/etc/nginx/sites-available/$domain";
        });

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) &&
                   $process->command === ['ln', '-sf', "/etc/nginx/sites-available/$domain", "/etc/nginx/sites-enabled/"];
        });

        Process::assertRan(function ($process) {
            return is_array($process->command) &&
                   $process->command === ['systemctl', 'reload', 'nginx'];
        });
    }

    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_secure_vhost()
    {
        Process::fake();

        $service = new NginxService();
        $domain = "example.com; echo injected";

        $service->secureVhost($domain);

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) &&
                   $process->command[0] === 'certbot' &&
                   in_array('-d', $process->command) &&
                   in_array($domain, $process->command);
        });
    }

    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_remove_vhost()
    {
        Process::fake();

        $service = new NginxService();
        $domain = "example.com; echo injected";

        $service->removeVhost($domain);

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) &&
                   $process->command === ['rm', '-f', "/etc/nginx/sites-available/$domain"];
        });

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) &&
                   $process->command === ['rm', '-f', "/etc/nginx/sites-enabled/$domain"];
        });

        Process::assertRan(function ($process) use ($domain) {
            return is_array($process->command) &&
                   $process->command[0] === 'certbot' &&
                   $process->command[1] === 'delete' &&
                   in_array('--cert-name', $process->command) &&
                   in_array($domain, $process->command);
        });

        Process::assertRan(function ($process) {
            return is_array($process->command) &&
                   $process->command === ['systemctl', 'reload', 'nginx'];
        });
    }
}
