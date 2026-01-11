<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $this->authorize('manage_roles');
        $permissions = Permission::all();
        return view('admin.permissions.index', compact('permissions'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage_roles');
        $request->validate(['name' => 'required|string|unique:permissions,name']);
        Permission::create(['name' => $request->name]);
        return back()->with('success', 'Permission created.');
    }

    public function destroy($id)
    {
        $this->authorize('manage_roles');
        Permission::findOrFail($id)->delete();
        return back()->with('success', 'Permission deleted.');
    }
}
