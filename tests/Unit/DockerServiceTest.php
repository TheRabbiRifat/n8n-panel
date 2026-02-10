<?php

namespace Tests\Unit;

use App\Services\DockerService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DockerServiceTest extends TestCase
{
    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_get_name_by_id()
    {
        Process::fake([
            '*' => Process::result('test-container'),
        ]);

        $service = new DockerService();
        $maliciousId = '; echo injected';

        // We call restartContainer to trigger getNameById
        $service->restartContainer($maliciousId);

        // Assert that the SAFE command was run.
        Process::assertRan(function ($process) use ($maliciousId) {
            return is_array($process->command) &&
                   $process->command === ['docker', 'inspect', '--format', '{{.Name}}', $maliciousId];
        });
    }

    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_stop_container_fallback()
    {
        Process::fake([
            'docker inspect*' => Process::result('', 1), // Fail to trigger fallback
            '*' => Process::result(''),
        ]);

        $service = new DockerService();
        $maliciousId = '; echo injected';

        $service->stopContainer($maliciousId);

        Process::assertRan(function ($process) use ($maliciousId) {
            return is_array($process->command) &&
                   $process->command === ['docker', 'stop', $maliciousId];
        });
    }

    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_start_container_fallback()
    {
        Process::fake([
            'docker inspect*' => Process::result('', 1),
            '*' => Process::result(''),
        ]);

        $service = new DockerService();
        $maliciousId = '; echo injected';

        $service->startContainer($maliciousId);

        Process::assertRan(function ($process) use ($maliciousId) {
            return is_array($process->command) &&
                   $process->command === ['docker', 'start', $maliciousId];
        });
    }

    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_restart_container_fallback()
    {
        Process::fake([
            'docker inspect*' => Process::result('', 1),
            '*' => Process::result(''),
        ]);

        $service = new DockerService();
        $maliciousId = '; echo injected';

        $service->restartContainer($maliciousId);

        Process::assertRan(function ($process) use ($maliciousId) {
            return is_array($process->command) &&
                   $process->command === ['docker', 'restart', $maliciousId];
        });
    }

    /**
     * @test
     */
    public function it_uses_safe_array_arguments_for_remove_container_fallback()
    {
        Process::fake([
            'docker inspect*' => Process::result('', 1),
            '*' => Process::result(''),
        ]);

        $service = new DockerService();
        $maliciousId = '; echo injected';

        $service->removeContainer($maliciousId);

        Process::assertRan(function ($process) use ($maliciousId) {
            return is_array($process->command) &&
                   $process->command === ['docker', 'rm', '-f', $maliciousId];
        });
    }
}
