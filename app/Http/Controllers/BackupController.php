<?php

namespace App\Http\Controllers;

use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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
            'host' => 'nullable|required_if:driver,ftp',
            'username' => 'nullable|required_if:driver,ftp,s3',
            'password' => 'nullable|required_if:driver,ftp,s3',
            'bucket' => 'nullable|required_if:driver,s3',
            'region' => 'nullable|required_if:driver,s3',
        ]);

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
