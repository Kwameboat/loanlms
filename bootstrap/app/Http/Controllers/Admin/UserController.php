<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Branch};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Password};
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:users.view']);
        $this->middleware('permission:users.create')->only(['create', 'store']);
        $this->middleware('permission:users.edit')->only(['edit', 'update', 'resetPassword', 'toggleStatus']);
        $this->middleware('permission:users.delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $query = User::with(['roles', 'branch'])->staff();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('employee_id', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Branch managers can only see their branch staff
        if (auth()->user()->hasRole('branch_manager')) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        $users    = $query->orderBy('name')->paginate(20)->withQueryString();
        $branches = Branch::active()->orderBy('name')->get();
        $roles    = Role::whereNotIn('name', ['borrower'])->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'branches', 'roles'));
    }

    public function create()
    {
        $branches = Branch::active()->orderBy('name')->get();
        $roles    = $this->getAssignableRoles();
        return view('admin.users.create', compact('branches', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'email'       => 'required|email|unique:users,email',
            'phone'       => 'nullable|string|max:20',
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id',
            'branch_id'   => 'required|exists:branches,id',
            'role'        => 'required|exists:roles,name',
            'password'    => 'required|string|min:8|confirmed',
            'notes'       => 'nullable|string',
        ]);

        // Super admin check — only SA can create super admin
        if ($validated['role'] === 'super_admin' && !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can create Super Admin accounts.');
        }

        $user = User::create([
            'name'                => $validated['name'],
            'email'               => $validated['email'],
            'phone'               => $validated['phone'] ?? null,
            'employee_id'         => $validated['employee_id'] ?? null,
            'branch_id'           => $validated['branch_id'],
            'password'            => Hash::make($validated['password']),
            'must_change_password'=> true,
            'is_active'           => true,
            'notes'               => $validated['notes'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->log("Created staff user: {$user->name} with role: {$validated['role']}");

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User '{$user->name}' created successfully. They must change their password on first login.");
    }

    public function show(User $user)
    {
        $user->load(['roles', 'branch', 'loans' => fn($q) => $q->latest()->take(10)]);
        $activityLog = activity()->causedBy($user)->latest()->take(20)->get();

        $stats = [
            'loans_managed'    => $user->loans()->count(),
            'repayments_collected' => $user->repayments()->count(),
            'active_loans'     => $user->loans()->whereIn('status', ['active', 'overdue'])->count(),
        ];

        return view('admin.users.show', compact('user', 'activityLog', 'stats'));
    }

    public function edit(User $user)
    {
        $this->checkEditPermission($user);
        $branches = Branch::active()->orderBy('name')->get();
        $roles    = $this->getAssignableRoles();
        return view('admin.users.edit', compact('user', 'branches', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $this->checkEditPermission($user);

        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'email'       => "required|email|unique:users,email,{$user->id}",
            'phone'       => 'nullable|string|max:20',
            'employee_id' => "nullable|string|max:50|unique:users,employee_id,{$user->id}",
            'branch_id'   => 'required|exists:branches,id',
            'role'        => 'required|exists:roles,name',
            'is_active'   => 'boolean',
            'notes'       => 'nullable|string',
        ]);

        $user->update($validated);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate(['password' => 'required|string|min:8|confirmed']);

        $user->update([
            'password'            => Hash::make($request->password),
            'must_change_password'=> true,
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->log("Password reset for: {$user->name}");

        return back()->with('success', 'Password reset successfully. User must change it on next login.');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';

        activity()->causedBy(auth()->user())
            ->performedOn($user)
            ->log("User account {$status}: {$user->name}");

        return back()->with('success', "User account {$status}.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->loans()->exists() || $user->repayments()->exists()) {
            return back()->with('error', 'Cannot delete user with associated records. Deactivate instead.');
        }

        $user->delete();
        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted.');
    }

    private function getAssignableRoles(): \Illuminate\Database\Eloquent\Collection
    {
        $roles = Role::whereNotIn('name', ['borrower']);
        if (!auth()->user()->isSuperAdmin()) {
            $roles->where('name', '!=', 'super_admin');
        }
        return $roles->orderBy('name')->get();
    }

    private function checkEditPermission(User $user): void
    {
        if ($user->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can edit Super Admin accounts.');
        }
    }
}
