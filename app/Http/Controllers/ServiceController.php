<?php

namespace App\Http\Controllers;

use App\Services\ServiceManager;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    protected $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function handle(Request $request, $service, $action)
    {
        if (!in_array($action, ['start', 'stop', 'restart'])) {
            return back()->with('error', 'Invalid action.');
        }

        try {
            $this->serviceManager->$action($service);
            return back()->with('success', ucfirst($service) . ' ' . $action . 'ed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
