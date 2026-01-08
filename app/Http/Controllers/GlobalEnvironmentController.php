<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use Illuminate\Http\Request;

class GlobalEnvironmentController extends Controller
{
    public function index()
    {
        $setting = GlobalSetting::where('key', 'n8n_env')->first();
        $envContent = '';

        if ($setting && $setting->value) {
            $envArray = json_decode($setting->value, true);
            foreach ($envArray as $key => $value) {
                $envContent .= "{$key}={$value}\n";
            }
        }

        return view('admin.environment.index', compact('envContent'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'environment' => 'nullable|string',
        ]);

        $envArray = [];
        if ($request->environment) {
            $lines = explode("\n", $request->environment);
            foreach ($lines as $line) {
                if (str_contains($line, '=')) {
                    list($key, $value) = explode('=', trim($line), 2);
                    $envArray[trim($key)] = trim($value);
                }
            }
        }

        GlobalSetting::updateOrCreate(
            ['key' => 'n8n_env'],
            ['value' => json_encode($envArray)]
        );

        return back()->with('success', 'Global environment updated successfully. Future instances will use these settings.');
    }
}
