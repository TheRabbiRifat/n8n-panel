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
            return $process->command === "tee /etc/nginx/sites-available/$domain" &&
                   $process->input() !== null;
        });

        Process::assertRan(function ($process) use ($domain) {
            return $process->command === "ln -sf /etc/nginx/sites-available/$domain /etc/nginx/sites-enabled/";
        });

        Process::assertRan(function ($process) {
            return $process->command === "systemctl reload nginx";
        });
    }

    public function test_secure_vhost_runs_correct_commands()
    {
        Process::fake();

        $domain = 'example.com';

        $this->nginxService->secureVhost($domain);

        Process::assertRan(function ($process) use ($domain) {
            return $process->command === "certbot --nginx -d $domain --non-interactive --agree-tos --email admin@example.com --redirect";
        });
    }

    public function test_remove_vhost_runs_correct_commands()
    {
        Process::fake();

        $domain = 'example.com';

        $this->nginxService->removeVhost($domain);

        Process::assertRan(function ($process) use ($domain) {
            return $process->command === "rm -f /etc/nginx/sites-available/$domain";
        });

        Process::assertRan(function ($process) use ($domain) {
            return $process->command === "rm -f /etc/nginx/sites-enabled/$domain";
        });

        Process::assertRan(function ($process) use ($domain) {
            return $process->command === "certbot delete --cert-name $domain --non-interactive";
        });

        Process::assertRan(function ($process) {
            return $process->command === "systemctl reload nginx";
        });
    }
}
