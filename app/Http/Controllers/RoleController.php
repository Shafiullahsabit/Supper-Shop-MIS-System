<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        ActivityLog::record('created', "Created role {$role->name}", $role);

        return back()->with('success', __('Role created.'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        ActivityLog::record('updated', "Updated permissions for role {$role->name}", $role);

        return back()->with('success', __('Permissions updated for :name.', ['name' => $role->name]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, ['Admin', 'Manager', 'Cashier'])) {
            return back()->with('error', __('Core roles cannot be deleted.'));
        }
        $role->delete();

        return back()->with('success', __('Role deleted.'));
    }
}
