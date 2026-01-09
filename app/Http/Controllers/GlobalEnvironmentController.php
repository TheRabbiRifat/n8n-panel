<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class GlobalEnvironmentController extends Controller
{
    public function index()
    {
        $setting = GlobalSetting::where('key', 'n8n_env')->first();
        $envArray = ($setting && $setting->value) ? json_decode($setting->value, true) : [];

        $smtpKeys = [
            'N8N_EMAIL_MODE',
            'N8N_SMTP_HOST',
            'N8N_SMTP_PORT',
            'N8N_SMTP_USER',
            'N8N_SMTP_PASS',
            'N8N_SMTP_SENDER',
            'N8N_SMTP_SSL'
        ];

        $smtpSettings = array_fill_keys($smtpKeys, '');
        $otherEnvContent = '';

        foreach ($envArray as $key => $value) {
            if (in_array($key, $smtpKeys)) {
                $smtpSettings[$key] = $value;
            } else {
                $otherEnvContent .= "{$key}={$value}\n";
            }
        }

        return view('admin.environment.index', compact('otherEnvContent', 'smtpSettings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'environment' => 'nullable|string',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'smtp_user' => 'nullable|string',
            'smtp_pass' => 'nullable|string',
            'smtp_sender' => 'nullable|email',
            'smtp_ssl' => 'nullable|boolean',
        ]);

        $envArray = [];

        // 1. Parse Textarea
        if ($request->environment) {
            $lines = explode("\n", $request->environment);
            foreach ($lines as $line) {
                if (str_contains($line, '=')) {
                    list($key, $value) = explode('=', trim($line), 2);
                    $envArray[trim($key)] = trim($value);
                }
            }
        }

        // 2. Merge SMTP Settings
        if ($request->filled('smtp_host')) {
            $envArray['N8N_EMAIL_MODE'] = 'smtp';
            $envArray['N8N_SMTP_HOST'] = $request->smtp_host;
            $envArray['N8N_SMTP_PORT'] = $request->smtp_port ?? 587;
            $envArray['N8N_SMTP_USER'] = $request->smtp_user;
            $envArray['N8N_SMTP_PASS'] = $request->smtp_pass;
            $envArray['N8N_SMTP_SENDER'] = $request->smtp_sender;
            $envArray['N8N_SMTP_SSL'] = $request->has('smtp_ssl') ? 'true' : 'false';

            // Update Local Laravel .env
            $this->updateEnvironmentFile([
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => $request->smtp_host,
                'MAIL_PORT' => $request->smtp_port ?? 587,
                'MAIL_USERNAME' => $request->smtp_user,
                'MAIL_PASSWORD' => $request->smtp_pass,
                'MAIL_ENCRYPTION' => $request->has('smtp_ssl') ? 'tls' : 'null',
                'MAIL_FROM_ADDRESS' => $request->smtp_sender,
            ]);

            Artisan::call('config:clear');
        }

        GlobalSetting::updateOrCreate(
            ['key' => 'n8n_env'],
            ['value' => json_encode($envArray)]
        );

        return back()->with('success', 'Global environment and Panel SMTP updated successfully.');
    }

    protected function updateEnvironmentFile(array $data)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            $env = file_get_contents($path);

            foreach ($data as $key => $value) {
                // Quote value if needed (simple check)
                $valStr = (str_contains($value, ' ') || str_contains($value, '#'))
                    ? '"' . str_replace('"', '\"', $value) . '"'
                    : $value;

                // If key exists, replace it
                if (preg_match("/^{$key}=.*/m", $env)) {
                    $env = preg_replace("/^{$key}=.*/m", "{$key}={$valStr}", $env);
                } else {
                    // Append it
                    $env .= "\n{$key}={$valStr}";
                }
            }

            file_put_contents($path, $env);
        }
    }
}
