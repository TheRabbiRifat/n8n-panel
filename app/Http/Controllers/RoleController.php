<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $this->authorize('manage_roles');
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage_roles');
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name]);
        if($request->permissions){
            $role->syncPermissions($request->permissions);
        }

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, $id)
    {
        $this->authorize('manage_roles');
        $role = Role::findOrFail($id);

        // Prevent editing admin role name or permissions critical to system integrity if we wanted
        if ($role->name === 'admin') {
             // In many ACL systems, admin is immutable.
             // Requirement: "can CRUD any ACL expect admin"
             return back()->with('error', 'Cannot edit the Admin role.');
        }

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => $request->name]);

        // Handle permissions sync
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        } else {
            // If no permissions sent (checkboxes unchecked), clear them
            $role->syncPermissions([]);
        }

        return back()->with('success', 'Role updated.');
    }

    public function destroy($id)
    {
        $this->authorize('manage_roles');
        $role = Role::findOrFail($id);

        // "can CRUD any ACL expect admin"
        // System roles might need protection, but definitely admin.
        if ($role->name === 'admin') {
             return back()->with('error', 'Cannot delete the Admin role.');
        }

        $role->delete();
        return back()->with('success', 'Role deleted.');
    }
}
