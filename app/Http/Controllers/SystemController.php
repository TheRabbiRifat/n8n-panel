<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use App\Services\ServiceManager;

class SystemController extends Controller
{
    protected $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function index()
    {
        $this->authorize('view_system');
        $hostname = gethostname();
        $services = ['nginx' => 'Nginx', 'postgresql' => 'PostgreSQL', 'docker' => 'Docker'];
        $serviceStatus = [];
        foreach($services as $key => $name){
            $serviceStatus[$key] = $this->serviceManager->getStatus($key);
        }

        return view('admin.system.index', compact('hostname', 'serviceStatus'));
    }

    public function updateHostname(Request $request)
    {
        $this->authorize('manage_settings');
        $request->validate([
            'hostname' => 'required|string|alpha_dash'
        ]);

        $hostname = $request->hostname;

        // Update hostname via system-manager.sh
        Process::run(['sudo', base_path('scripts/system-manager.sh'), '--action=hostname', "--value={$hostname}"]);

        return back()->with('success', 'Hostname updated. Reboot might be required for full effect.');
    }

    public function reboot()
    {
        $this->authorize('manage_settings');
        // Execute reboot via system-manager.sh
        Process::run(['sudo', base_path('scripts/system-manager.sh'), '--action=reboot']);
        return back()->with('success', 'Server is rebooting...');
    }

    public function restartService(Request $request, $service)
    {
        $this->authorize('manage_settings');
        try {
            $this->serviceManager->restart($service);
            return back()->with('success', ucfirst($service) . ' restarted.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
