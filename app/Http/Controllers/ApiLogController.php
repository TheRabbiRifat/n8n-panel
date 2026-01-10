<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    public function index()
    {
        $logs = ApiLog::with('user')->latest()->paginate(20);
        return view('admin.api_logs.index', compact('logs'));
    }
}
