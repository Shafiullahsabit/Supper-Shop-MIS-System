<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\HeldSale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $users = User::with('roles', 'branch')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($search) use ($request) {
                $search->where('name', 'like', '%'.$request->q.'%')
                    ->orWhere('email', 'like', '%'.$request->q.'%');
            }))
            ->orderBy('name')->paginate(15)->withQueryString();

        $roles = Role::when(! $isSuperAdmin, fn ($q) => $q->where('name', '!=', 'Super Admin'))
            ->orderBy('name')->get();
        $branches = $isSuperAdmin ? Branch::where('is_active', true)->orderBy('branch_number')->get() : collect();

        return view('users.index', compact('users', 'roles', 'branches', 'isSuperAdmin'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isSuperAdmin = Auth::user()->hasRole('Super Admin');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in($this->availableRoleNames($isSuperAdmin))],
            'is_active' => ['nullable', 'boolean'],
            'branch_id' => [
                $isSuperAdmin ? 'required' : 'prohibited',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
            'branch_id' => $isSuperAdmin ? $data['branch_id'] : Auth::user()->branch_id,
        ]);
        $user->syncRoles($data['role']);

        ActivityLog::record('created', "Created user {$user->name} ({$data['role']})", $user);

        return back()->with('success', __('User created.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $isSuperAdmin = Auth::user()->hasRole('Super Admin');
        $this->ensureUserIsManageable($user, $isSuperAdmin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in($this->availableRoleNames($isSuperAdmin))],
            'is_active' => ['nullable', 'boolean'],
            'branch_id' => [
                $isSuperAdmin ? 'required' : 'prohibited',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
        ]);

        $newBranchId = (int) ($isSuperAdmin ? $data['branch_id'] : Auth::user()->branch_id);
        if ((int) $user->branch_id !== $newBranchId
            && ($user->cashSessions()->where('status', 'open')->exists()
                || HeldSale::where('user_id', $user->id)->exists())) {
            return back()->with('error', __('Close the user’s register and resolve held sales before changing their branch.'));
        }

        if ($user->hasRole('Super Admin') && $data['role'] !== 'Super Admin'
            && User::role('Super Admin')->count() <= 1) {
            return back()->with('error', __('At least one Super Admin account must remain.'));
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'branch_id' => $isSuperAdmin ? $data['branch_id'] : Auth::user()->branch_id,
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->syncRoles($data['role']);

        ActivityLog::record('updated', "Updated user {$user->name}", $user);

        return back()->with('success', __('User updated.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $isSuperAdmin = Auth::user()->hasRole('Super Admin');
        $this->ensureUserIsManageable($user, $isSuperAdmin);

        if ($user->id === Auth::id()) {
            return back()->with('error', __('You cannot delete your own account.'));
        }
        if ($user->hasRole('Super Admin') && User::role('Super Admin')->count() <= 1) {
            return back()->with('error', __('At least one Super Admin account must remain.'));
        }
        $name = $user->name;
        $user->delete();
        ActivityLog::record('deleted', "Deleted user {$name}");

        return back()->with('success', __('User deleted.'));
    }

    private function availableRoleNames(bool $isSuperAdmin): array
    {
        return Role::when(! $isSuperAdmin, fn ($query) => $query->where('name', '!=', 'Super Admin'))
            ->pluck('name')->all();
    }

    private function ensureUserIsManageable(User $target, bool $isSuperAdmin): void
    {
        abort_unless(
            $isSuperAdmin || ((int) $target->branch_id === (int) Auth::user()->branch_id && ! $target->hasRole('Super Admin')),
            403
        );
    }
}
