<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RequirementController extends Controller
{
    public function index(Request $request)
    {
        $hostname = $request->getHost();
        $isIp = filter_var($hostname, FILTER_VALIDATE_IP);
        $serverIp = 'Unknown';

        // Try to get public IP
        try {
            $response = Http::timeout(2)->get('https://api.ipify.org');
            if ($response->successful()) {
                $serverIp = trim($response->body());
            } else {
                $serverIp = gethostbyname(gethostname());
            }
        } catch (\Exception $e) {
            $serverIp = gethostbyname(gethostname());
        }

        $checks = [
            'hostname' => $hostname,
            'server_ip' => $serverIp,
            'is_ip' => $isIp,
            'a_records' => [],
            'wildcard_records' => [],
            'nameservers' => [],
            'dns_provider' => 'Unknown',
            'a_record_match' => false,
            'wildcard_match' => false,
        ];

        if (!$isIp) {
            // A Records
            $dnsA = @dns_get_record($hostname, DNS_A);
            if ($dnsA) {
                foreach ($dnsA as $record) {
                    $checks['a_records'][] = $record['ip'];
                }
            }

            // Nameservers
            $dnsNS = @dns_get_record($hostname, DNS_NS);
            if ($dnsNS) {
                foreach ($dnsNS as $record) {
                    $checks['nameservers'][] = $record['target'];
                }
            }

            $checks['dns_provider'] = $this->identifyDnsProvider($checks['nameservers']);

            // Wildcard Check
            $wildcardTest = 'check-wildcard-' . time() . '.' . $hostname;
            $dnsWildcard = @dns_get_record($wildcardTest, DNS_A);
            if ($dnsWildcard) {
                foreach ($dnsWildcard as $record) {
                    $checks['wildcard_records'][] = $record['ip'];
                }
            }

            // Matches
            if (in_array($serverIp, $checks['a_records'])) {
                $checks['a_record_match'] = true;
            }
            if (in_array($serverIp, $checks['wildcard_records'])) {
                $checks['wildcard_match'] = true;
            }
        }

        return view('requirements.index', compact('checks'));
    }

    private function identifyDnsProvider(array $nameservers)
    {
        $providers = [
            'cloudflare.com' => 'Cloudflare',
            'awsdns' => 'AWS Route53',
            'digitalocean.com' => 'DigitalOcean',
            'googledomains.com' => 'Google Domains',
            'godaddy.com' => 'GoDaddy',
            'namecheap.com' => 'Namecheap',
            'azure-dns' => 'Azure DNS',
            'hetzner' => 'Hetzner',
            'linode' => 'Linode',
            'ovh.net' => 'OVH',
            'registrar-servers.com' => 'Namecheap (Basic DNS)',
            'domaincontrol.com' => 'GoDaddy (Basic DNS)',
            'world4you.com' => 'World4You',
            'siteground' => 'SiteGround',
            'bluehost' => 'Bluehost',
            'hostgator' => 'HostGator',
            'dreamhost' => 'DreamHost',
        ];

        foreach ($nameservers as $ns) {
            foreach ($providers as $domain => $name) {
                if (stripos($ns, $domain) !== false) {
                    return $name;
                }
            }
        }

        return 'Unknown Provider';
    }
}
