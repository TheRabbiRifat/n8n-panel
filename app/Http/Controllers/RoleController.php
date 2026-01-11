<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array'
        ]);

        $role = Role::create(['name' => $request->name]);
        if($request->permissions){
            $role->syncPermissions($request->permissions);
        }

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'array'
        ]);

        $role->update(['name' => $request->name]);
        if($request->permissions){
            $role->syncPermissions($request->permissions);
        }

        return back()->with('success', 'Role updated.');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        if($role->name === 'admin' || $role->name === 'reseller' || $role->name === 'client') {
             return back()->with('error', 'Cannot delete system roles.');
        }
        $role->delete();
        return back()->with('success', 'Role deleted.');
    }
}
