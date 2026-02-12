<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PackageController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
             $packages = Package::all();
        } else {
             $packages = Package::where('user_id', $user->id)->get();
        }

        return view('packages.index', compact('packages'));
    }

    public function create()
    {
        if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('reseller')) {
            abort(403, 'Unauthorized');
        }
        return view('packages.create');
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('reseller')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:instance,reseller',
            'cpu_limit' => 'required|numeric|min:0.1|max:100', // Reasonable max?
            'ram_limit' => 'required|numeric|min:0.1|max:1024', // GB
            'disk_limit' => 'nullable|numeric|min:0.1|max:10000', // GB
            'instance_count' => 'nullable|integer|min:1|max:1000000',
        ]);

        // Validate specific fields based on type
        if ($request->type === 'instance' && !$request->disk_limit) {
             return back()->withErrors(['disk_limit' => 'Disk limit is required for instance packages.'])->withInput();
        }
        if ($request->type === 'reseller' && !$request->instance_count) {
             return back()->withErrors(['instance_count' => 'Instance count is required for reseller packages.'])->withInput();
        }

        Package::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'type' => $request->type,
            'cpu_limit' => $request->cpu_limit,
            'ram_limit' => $request->ram_limit,
            'disk_limit' => $request->disk_limit,
            'instance_count' => $request->instance_count,
        ]);

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function edit(Package $package)
    {
        if (Auth::user()->id !== $package->user_id && !Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        return view('packages.edit', compact('package'));
    }

    public function update(Request $request, Package $package)
    {
        if (Auth::user()->id !== $package->user_id && !Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:instance,reseller',
            'cpu_limit' => 'required|numeric|min:0.1|max:100',
            'ram_limit' => 'required|numeric|min:0.1|max:1024',
            'disk_limit' => 'nullable|numeric|min:0.1|max:10000',
            'instance_count' => 'nullable|integer|min:1|max:1000000',
        ]);

        // Validate specific fields based on type
        if ($request->type === 'instance' && !$request->disk_limit) {
             return back()->withErrors(['disk_limit' => 'Disk limit is required for instance packages.'])->withInput();
        }
        if ($request->type === 'reseller' && !$request->instance_count) {
             return back()->withErrors(['instance_count' => 'Instance count is required for reseller packages.'])->withInput();
        }

        $package->update([
            'name' => $request->name,
            'type' => $request->type,
            'cpu_limit' => $request->cpu_limit,
            'ram_limit' => $request->ram_limit,
            'disk_limit' => $request->disk_limit,
            'instance_count' => $request->instance_count,
        ]);

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        if (Auth::user()->id !== $package->user_id && !Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $package->delete();
        return back()->with('success', 'Package deleted successfully.');
    }
}
