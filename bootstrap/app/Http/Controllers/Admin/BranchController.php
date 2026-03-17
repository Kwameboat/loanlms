<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:super_admin|admin']);
        $this->middleware('permission:branches.view')->only(['index', 'show']);
        $this->middleware('permission:branches.create')->only(['create', 'store']);
        $this->middleware('permission:branches.edit')->only(['edit', 'update']);
        $this->middleware('permission:branches.delete')->only('destroy');
    }

    public function index()
    {
        $branches = Branch::withCount(['users', 'borrowers', 'loans'])
            ->orderBy('is_head_office', 'desc')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('admin.branches.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'code'           => 'required|string|max:20|unique:branches,code',
            'address'        => 'required|string|max:255',
            'region'         => 'nullable|string|max:60',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:120',
            'manager_name'   => 'nullable|string|max:120',
            'is_head_office' => 'boolean',
            'notes'          => 'nullable|string',
        ]);

        $branch = Branch::create($validated);

        return redirect()->route('admin.branches.show', $branch)
            ->with('success', "Branch '{$branch->name}' created successfully.");
    }

    public function show(Branch $branch)
    {
        $branch->load('users');
        $stats = [
            'total_staff'      => $branch->users()->staff()->count(),
            'total_borrowers'  => $branch->borrowers()->count(),
            'active_loans'     => $branch->loans()->whereIn('status', ['active', 'overdue'])->count(),
            'total_disbursed'  => $branch->loans()->whereNotNull('disbursed_amount')->sum('disbursed_amount'),
            'total_outstanding'=> $branch->loans()->sum('total_outstanding'),
            'overdue_loans'    => $branch->loans()->where('is_overdue', true)->count(),
            'par'              => $branch->portfolio_at_risk,
        ];

        $recentLoans = $branch->loans()->with('borrower', 'loanProduct')
            ->latest()->take(10)->get();

        return view('admin.branches.show', compact('branch', 'stats', 'recentLoans'));
    }

    public function edit(Branch $branch)
    {
        return view('admin.branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'code'           => "required|string|max:20|unique:branches,code,{$branch->id}",
            'address'        => 'required|string|max:255',
            'region'         => 'nullable|string|max:60',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:120',
            'manager_name'   => 'nullable|string|max:120',
            'is_active'      => 'boolean',
            'notes'          => 'nullable|string',
        ]);

        $branch->update($validated);

        return redirect()->route('admin.branches.show', $branch)
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->users()->exists() || $branch->loans()->exists()) {
            return back()->with('error', 'Cannot delete branch with assigned staff or loans.');
        }

        $branch->delete();
        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch deleted successfully.');
    }
}
