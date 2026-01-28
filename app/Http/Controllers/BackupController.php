<?php

namespace App\Http\Controllers;

use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Rules\CronExpression; // You might need a custom rule or strict regex

class BackupController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        $setting = BackupSetting::first();
        $backups = [];
        try {
            $backups = $this->backupService->listBackups();
        } catch (\Exception $e) {
            // Config might be invalid
        }

        return view('admin.backups.index', compact('setting', 'backups'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'driver' => 'required|in:local,ftp,s3',
            'host' => 'nullable|required_if:driver,ftp|string|max:255',
            'username' => 'nullable|required_if:driver,ftp,s3|string|max:255',
            'password' => 'nullable|required_if:driver,ftp,s3|string|max:255',
            'bucket' => 'nullable|required_if:driver,s3|string|max:255',
            'region' => 'nullable|required_if:driver,s3|string|max:100',
            'endpoint' => 'nullable|url|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'path' => 'nullable|string|max:255',
            'cron_expression' => 'nullable|string|max:255', // Could add more strict cron regex
        ]);

        // Simple cron validation if provided
        if ($request->filled('cron_expression')) {
            // Very basic check, standard cron usually has 5 parts
            $parts = explode(' ', $request->cron_expression);
            if (count($parts) < 5) {
                return back()->with('error', 'Invalid cron expression format.')->withInput();
            }
        }

        try {
            $this->backupService->testConnection($request->all());
        } catch (\Exception $e) {
            return back()->with('error', 'Connection test failed: ' . $e->getMessage())->withInput();
        }

        $setting = BackupSetting::firstOrNew();
        $setting->fill($request->all());
        $setting->enabled = $request->has('enabled');
        $setting->save();

        return back()->with('success', 'Backup settings saved.');
    }

    public function run()
    {
        try {
            Artisan::call('backup:run');
            $output = Artisan::output();
            return back()->with('success', 'Backup process started. Output: ' . $output);
        } catch (\Exception $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function download(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        return $this->backupService->downloadBackup($request->path);
    }
}
