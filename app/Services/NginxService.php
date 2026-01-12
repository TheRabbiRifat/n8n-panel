<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class NginxService
{
    public function createVhost(string $domain, int $port)
    {
        // Initial HTTP configuration
        $config = <<<nginx
server {
    listen 80;
    listen [::]:80;
    server_name $domain;

    location / {
        proxy_pass http://127.0.0.1:$port;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # WebSocket Support
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";

        # Long timeout for n8n
        proxy_read_timeout 3600;
    }
}
nginx;

        // Write config file (Assuming write permissions on /etc/nginx/sites-available)
        // Using file_put_contents if possible, or tee without sudo
        // We will stick to shell commands to minimize ownership issues if user is added to a group that has write access
        Process::run("echo '$config' | tee /etc/nginx/sites-available/$domain");
        Process::run("ln -sf /etc/nginx/sites-available/$domain /etc/nginx/sites-enabled/");
        Process::run("systemctl reload nginx");
    }

    public function secureVhost(string $domain)
    {
        // Use Certbot to obtain certificate and modify Nginx config for SSL
        $email = env('MAIL_FROM_ADDRESS', 'admin@example.com');

        // Command to obtain cert and redirect HTTP to HTTPS
        // --nginx: Use Nginx plugin for auth and install
        // --redirect: Automatically redirect HTTP to HTTPS
        $command = "certbot --nginx -d $domain --non-interactive --agree-tos --email $email --redirect";

        $process = Process::run($command);

        if (!$process->successful()) {
            // Log warning but don't fail hard, maybe DNS isn't propagated yet
            // We can return false or throw exception
            // For now, let's just log or return false
            return false;
        }

        return true;
    }

    public function removeVhost(string $domain)
    {
        Process::run("rm -f /etc/nginx/sites-available/$domain");
        Process::run("rm -f /etc/nginx/sites-enabled/$domain");

        // Optionally delete certs?
        // certbot delete --cert-name $domain
        // This prevents clutter.
        Process::run("certbot delete --cert-name $domain --non-interactive");

        Process::run("systemctl reload nginx");
    }
}
