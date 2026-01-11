<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    public function index()
    {
        $this->authorize('view_logs');
        $logs = ApiLog::with('user')->latest()->paginate(20);
        return view('admin.api_logs.index', compact('logs'));
    }
}
