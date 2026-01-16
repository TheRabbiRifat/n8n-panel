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

        // Resellers can only use the packages, no update, or delete.
        // Assuming this means they can VIEW all available packages (or packages assigned to them?)
        // The prompt says "resellers can only use the packages".
        // Let's assume they can see all packages created by Admin.

        if ($user->hasRole('admin')) {
             $packages = Package::all();
        } else {
             // Reseller: View all packages (to "use" them for creating instances)
             // Previously it was "own packages". Now they cannot create/update/delete.
             // So they must use Admin packages.
             $packages = Package::all();
        }

        return view('packages.index', compact('packages'));
    }

    public function create()
    {
        // Resellers cannot create packages
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        return view('packages.create');
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:instance,reseller',
            'cpu_limit' => 'required|numeric|min:0.1',
            'ram_limit' => 'required|numeric|min:0.1', // GB
            'disk_limit' => 'nullable|numeric|min:0.1', // GB
            'instance_count' => 'nullable|integer|min:1',
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
        // Resellers cannot update packages
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        return view('packages.edit', compact('package'));
    }

    public function update(Request $request, Package $package)
    {
        // Resellers cannot update packages
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:instance,reseller',
            'cpu_limit' => 'required|numeric|min:0.1',
            'ram_limit' => 'required|numeric|min:0.1',
            'disk_limit' => 'nullable|numeric|min:0.1',
            'instance_count' => 'nullable|integer|min:1',
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
        // Resellers cannot delete packages
        if (!Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        $package->delete();
        return back()->with('success', 'Package deleted successfully.');
    }
}
