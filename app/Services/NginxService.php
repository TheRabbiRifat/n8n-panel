<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class NginxService
{
    public function createVhost(string $domain, int $port)
    {
        // Simple Nginx Template with HTTP/3 and WebSocket support
        // Assuming SSL is handled via wildcard cert or similar for now.
        // For local dev without certs, we might fall back to HTTP.
        // But user asked for HTTP/3 which implies HTTPS.
        // We will assume a wildcard cert path exists or create a self-signed one is out of scope.
        // I will output a config that listens on 80 and 443 (if certs exist).

        // Placeholder paths for certs (Use Snakeoil by default to prevent syntax errors)
        $sslCert = '/etc/ssl/certs/ssl-cert-snakeoil.pem';
        $sslKey = '/etc/ssl/private/ssl-cert-snakeoil.key';

        $config = <<<nginx
server {
    listen 80;
    listen [::]:80;
    server_name $domain;

    # Redirect all HTTP traffic to HTTPS
    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    # HTTP/3 QUIC
    listen 443 quic reuseport;
    listen [::]:443 quic reuseport;

    server_name $domain;

    # SSL Config
    ssl_certificate $sslCert;
    ssl_certificate_key $sslKey;

    add_header Alt-Svc 'h3=":443"; ma=86400';

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

        # Increase timeouts for long-running n8n workflows
        proxy_read_timeout 3600;
    }
}
nginx;

        // Write to tmp then move? Or tee.
        // We use tee to write as sudo
        Process::run("echo '$config' | sudo tee /etc/nginx/sites-available/$domain");
    }

    public function enableVhost(string $domain)
    {
        Process::run("sudo ln -sf /etc/nginx/sites-available/$domain /etc/nginx/sites-enabled/");
    }

    public function removeVhost(string $domain)
    {
        Process::run("sudo rm -f /etc/nginx/sites-available/$domain");
        Process::run("sudo rm -f /etc/nginx/sites-enabled/$domain");
    }

    public function reloadNginx()
    {
        Process::run("sudo systemctl reload nginx");
    }
}
