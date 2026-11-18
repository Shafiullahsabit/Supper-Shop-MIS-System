<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $isSuperAdmin = auth()->user()->hasRole('Super Admin');
        $roles = Role::with('permissions')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('name', '!=', 'Super Admin'))
            ->withCount('users')->orderBy('name')->get();
        $permissions = Permission::when(! $isSuperAdmin, fn ($q) => $q->where('name', '!=', 'branches.manage'))
            ->orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isSuperAdmin = auth()->user()->hasRole('Super Admin');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name', Rule::notIn(['Super Admin'])],
            'permissions' => ['array'],
            'permissions.*' => [
                'exists:permissions,name',
                ...($isSuperAdmin ? [] : [Rule::notIn(['branches.manage'])]),
            ],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        ActivityLog::record('created', "Created role {$role->name}", $role);

        return back()->with('success', __('Role created.'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $isSuperAdmin = auth()->user()->hasRole('Super Admin');
        abort_unless($role->name !== 'Super Admin', 403);

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [
                'exists:permissions,name',
                ...($isSuperAdmin ? [] : [Rule::notIn(['branches.manage'])]),
            ],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        ActivityLog::record('updated', "Updated permissions for role {$role->name}", $role);

        return back()->with('success', __('Permissions updated for :name.', ['name' => $role->name]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, ['Admin', 'Manager', 'Cashier', 'Super Admin'])) {
            return back()->with('error', __('Core roles cannot be deleted.'));
        }
        $role->delete();

        return back()->with('success', __('Role deleted.'));
    }
}
