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
            $packages = Package::with('user')->get();
        } else {
            // Reseller sees their own packages
            $packages = Package::where('user_id', $user->id)->get();
        }

        return view('packages.index', compact('packages'));
    }

    public function create()
    {
        return view('packages.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cpu_limit' => 'nullable|numeric|min:0.1',
            'ram_limit' => 'nullable|numeric|min:0.1', // GB
            'disk_limit' => 'nullable|numeric|min:0.1', // GB
        ]);

        Package::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'cpu_limit' => $request->cpu_limit,
            'ram_limit' => $request->ram_limit,
            'disk_limit' => $request->disk_limit,
        ]);

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function edit(Package $package)
    {
        // Admin can edit any, Reseller only own
        if (!Auth::user()->hasRole('admin') && $package->user_id !== Auth::id()) {
            abort(403);
        }

        return view('packages.edit', compact('package'));
    }

    public function update(Request $request, Package $package)
    {
        // Admin can edit any, Reseller only own
        if (!Auth::user()->hasRole('admin') && $package->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'cpu_limit' => 'nullable|numeric|min:0.1',
            'ram_limit' => 'nullable|numeric|min:0.1',
            'disk_limit' => 'nullable|numeric|min:0.1',
        ]);

        $package->update([
            'name' => $request->name,
            'cpu_limit' => $request->cpu_limit,
            'ram_limit' => $request->ram_limit,
            'disk_limit' => $request->disk_limit,
        ]);

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        // Admin can delete any, Reseller only own
        if (!Auth::user()->hasRole('admin') && $package->user_id !== Auth::id()) {
            abort(403);
        }

        $package->delete();
        return back()->with('success', 'Package deleted successfully.');
    }
}
