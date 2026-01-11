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
        $services = ['nginx' => 'Nginx', 'mysql' => 'MySQL', 'postgresql' => 'PostgreSQL', 'docker' => 'Docker'];
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

        // Update hostname via hostnamectl
        Process::run("sudo hostnamectl set-hostname {$hostname}");

        // Ideally update /etc/hosts too, but minimal req is usually just this.

        return back()->with('success', 'Hostname updated. Reboot might be required for full effect.');
    }

    public function reboot()
    {
        $this->authorize('manage_settings');
        // Execute reboot in background to allow response
        Process::run("(sleep 2 && sudo reboot) &");
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
