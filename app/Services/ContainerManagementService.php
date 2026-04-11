<?php

namespace App\Services;

use App\Models\Container;
use App\Models\GlobalSetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ContainerManagementService
{
    protected $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    /**
     * Shared logic to destroy and recreate a container with current DB settings.
     */
    public function recreateContainer(Container $container)
    {
        // 1. Prepare Configuration
        // Global Env
        $globalEnv = GlobalSetting::where('key', 'n8n_env')->first();
        $envArray = $globalEnv ? json_decode($globalEnv->value, true) : [];

        // Fixed & Dynamic Envs
        $fixedAndDynamic = [
            'N8N_HOST' => $container->domain,
            'N8N_PORT' => 5678,
            'N8N_PROTOCOL' => 'https',
            'WEBHOOK_URL' => "https://{$container->domain}/",
            'N8N_SECURE_COOKIE' => 'false',
            'N8N_VERSION_NOTIFICATIONS_ENABLED' => 'false',
            'N8N_TELEMETRY_ENABLED' => 'false',
            'EXECUTIONS_PROCESS' => 'main',
            'N8N_BLOCK_ENV_ACCESS_IN_NODE' => 'true',
        ];

        $envArray = array_merge($envArray, $fixedAndDynamic);

        // User Configurable Envs (from DB)
        $userEnv = $container->environment ? json_decode($container->environment, true) : [];
        $envArray = array_merge($envArray, $userEnv);

        // Remove SMTP keys if present (only injected in recovery mode)
        // We remove them after merging global and user envs to ensure they are cleared
        // unless explicitly re-added by the recovery block below.
        foreach (Container::SMTP_ENV_KEYS as $key) {
            unset($envArray[$key]);
        }

        // Recovery Mode - Inject SMTP
        if ($container->is_recovery_mode) {
            $smtpEnv = [
                'N8N_EMAIL_MODE' => 'smtp',
                'N8N_SMTP_HOST' => config('mail.mailers.smtp.host'),
                'N8N_SMTP_PORT' => config('mail.mailers.smtp.port'),
                'N8N_SMTP_USER' => config('mail.mailers.smtp.username'),
                'N8N_SMTP_PASS' => config('mail.mailers.smtp.password'),
                'N8N_SMTP_SENDER' => config('mail.from.address'),
                'N8N_SMTP_SSL' => (config('mail.mailers.smtp.scheme') === 'tls') ? 'true' : 'false',
            ];
            // Filter out nulls just in case
            $smtpEnv = array_filter($smtpEnv, fn($v) => !is_null($v));
            $envArray = array_merge($envArray, $smtpEnv);
        }

        // Volume Path
        $volumeHostPath = "/var/lib/n8n/instances/{$container->name}";
        $volumes = [$volumeHostPath => '/home/node/.n8n'];

        // Retrieve or Generate DB Credentials
        $dbConfig = [];
        if ($container->db_database && $container->db_username) {
            $dbConfig = [
                'host' => $container->db_host,
                'port' => $container->db_port,
                'database' => $container->db_database,
                'username' => $container->db_username,
                'password' => $container->db_password,
            ];
        } else {
             // Generate if missing (Legacy support)
            $safeName = preg_replace('/[^a-z0-9]/', '', strtolower($container->name)) . '_' . Str::random(4);
            $dbConfig = [
                'host' => $this->dockerService->getDockerGatewayIp(),
                'port' => 5432,
                'database' => "n8n_{$safeName}",
                'username' => "n8n_{$safeName}",
                'password' => Str::random(16),
            ];
        }

        // 2. Stop and Remove old container
        try {
            $this->dockerService->removeContainer($container->docker_id);
        } catch (\Exception $e) {
            // Ignore if already gone
        }

        // 3. Create New Container
        $image = 'n8nio/n8n:' . ($container->image_tag ?? 'latest');
        $email = Auth::user()->email ?? env('MAIL_FROM_ADDRESS', 'admin@example.com');
        $panelDbUser = config('database.connections.pgsql.username');
        $package = $container->package; // Relations should be loaded or lazy loaded

        $instance = $this->dockerService->createContainer(
            $image,
            $container->name,
            $container->port,
            5678,
            $package->cpu_limit ?? 1,
            $package->ram_limit ?? 1,
            $envArray,
            $volumes,
            [], // labels
            $container->domain,
            $email,
            $container->id,
            $dbConfig,
            $panelDbUser
        );

        // 4. Update DB details (Docker ID and potentially DB creds if generated)
        $container->update([
            'docker_id' => $instance->getShortDockerIdentifier(),
            'db_host' => $dbConfig['host'],
            'db_port' => $dbConfig['port'],
            'db_database' => $dbConfig['database'],
            'db_username' => $dbConfig['username'],
            'db_password' => $dbConfig['password'],
        ]);
    }
}
