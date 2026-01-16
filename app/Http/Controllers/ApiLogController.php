<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view_logs');

        $query = ApiLog::with('user')->latest();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('method', 'like', "%{$search}%")
                  ->orWhere('endpoint', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('response_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->paginate(20)->withQueryString();
        return view('admin.api_logs.index', compact('logs'));
    }

    public function destroy()
    {
        $this->authorize('view_logs'); // Reuse same permission or stricter if exists
        ApiLog::truncate();
        return back()->with('success', 'All API logs have been purged.');
    }
}
