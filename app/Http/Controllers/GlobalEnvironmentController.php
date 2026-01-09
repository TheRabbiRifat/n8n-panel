<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use Illuminate\Http\Request;

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
            'smtp_ssl' => 'nullable|boolean', // checkbox
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
        // Only if Host is provided do we enable SMTP mode?
        // Or if the user explicitly wants SMTP.
        // Let's assume if host is set, we set variables.
        if ($request->filled('smtp_host')) {
            $envArray['N8N_EMAIL_MODE'] = 'smtp';
            $envArray['N8N_SMTP_HOST'] = $request->smtp_host;
            $envArray['N8N_SMTP_PORT'] = $request->smtp_port ?? 587;
            $envArray['N8N_SMTP_USER'] = $request->smtp_user;
            $envArray['N8N_SMTP_PASS'] = $request->smtp_pass;
            $envArray['N8N_SMTP_SENDER'] = $request->smtp_sender;
            $envArray['N8N_SMTP_SSL'] = $request->has('smtp_ssl') ? 'true' : 'false';
        } else {
            // If cleared, maybe remove? Or keep existing logic if not in request?
            // Since we override $envArray, if they are not in textarea, they are gone unless added here.
            // So if fields are empty, we don't set them.
        }

        GlobalSetting::updateOrCreate(
            ['key' => 'n8n_env'],
            ['value' => json_encode($envArray)]
        );

        return back()->with('success', 'Global environment updated successfully. Future instances will use these settings.');
    }
}
