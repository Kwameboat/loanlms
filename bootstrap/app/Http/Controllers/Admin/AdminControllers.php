<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

// ─── Settings Controller ──────────────────────────────────────────────────────

class SettingsController extends Controller
{
    public function __construct() { $this->middleware('permission:settings.view|settings.edit'); }

    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $this->middleware('permission:settings.edit');
        $data = $request->except(['_token', '_method']);

        foreach ($data as $key => $value) {
            if ($request->hasFile($key)) {
                $file  = $request->file($key);
                $path  = $file->store('company', 'public');
                Setting::set($key, $path, 'company');
            } else {
                $group = str_contains($key, 'paystack') ? 'paystack'
                    : (str_contains($key, 'mail') ? 'mail'
                    : (str_contains($key, 'sms') ? 'sms' : 'general'));
                Setting::set($key, $value, $group);
            }
        }

        activity('settings')->causedBy(Auth::user())->log('Settings updated');
        return back()->with('success', 'Settings saved successfully.');
    }

    public function testEmail(Request $request)
    {
        $request->validate(['test_email' => 'required|email']);
        try {
            \Mail::raw('This is a test email from Big Cash LMS.', fn($m) => $m->to($request->test_email)->subject('Big Cash Test Email'));
            return back()->with('success', 'Test email sent to ' . $request->test_email);
        } catch (\Exception $e) {
            return back()->with('error', 'Email failed: ' . $e->getMessage());
        }
    }
}

// ─── Loan Product Controller ──────────────────────────────────────────────────

class LoanProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:products.view')->only(['index', 'show']);
        $this->middleware('permission:products.create')->only(['create', 'store']);
        $this->middleware('permission:products.edit')->only(['edit', 'update', 'toggle']);
        $this->middleware('permission:products.delete')->only(['destroy']);
    }

    public function index()
    {
        $products = LoanProduct::with('createdBy')->withCount('loans')->latest()->paginate(20);
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $branches = Branch::active()->get();
        return view('admin.products.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:100',
            'code'                    => 'required|string|max:20|unique:loan_products',
            'description'             => 'nullable|string',
            'product_type'            => 'required|in:salary_loan,personal_loan,business_loan,emergency_loan,group_loan,microloan,other',
            'min_amount'              => 'required|numeric|min:1',
            'max_amount'              => 'required|numeric|gt:min_amount',
            'min_term'                => 'required|integer|min:1',
            'max_term'                => 'required|integer|gte:min_term',
            'interest_type'           => 'required|in:flat,reducing',
            'interest_rate'           => 'required|numeric|min:0',
            'interest_period'         => 'required|in:per_annum,per_month,per_week',
            'processing_fee'          => 'nullable|numeric|min:0',
            'insurance_fee'           => 'nullable|numeric|min:0',
            'admin_fee'               => 'nullable|numeric|min:0',
            'grace_period_days'       => 'nullable|integer|min:0',
            'repayment_frequency'     => 'required|in:daily,weekly,biweekly,monthly,custom',
            'penalty_enabled'         => 'boolean',
            'penalty_type'            => 'nullable|in:percentage,fixed',
            'penalty_rate'            => 'nullable|numeric|min:0',
            'penalty_grace_days'      => 'nullable|integer|min:0',
            'min_age'                 => 'nullable|integer|min:18',
            'max_age'                 => 'nullable|integer',
            'requires_guarantor'      => 'boolean',
            'requires_approval_chain' => 'boolean',
            'approval_levels'         => 'nullable|integer|in:1,2',
            'branch_ids'              => 'nullable|array',
            'branch_ids.*'            => 'exists:branches,id',
        ]);

        $data['created_by'] = Auth::id();
        $data['is_active']  = true;

        $product = LoanProduct::create($data);

        if (! empty($request->branch_ids)) {
            $product->branches()->sync($request->branch_ids);
        } else {
            // Attach to all branches by default
            $product->branches()->sync(Branch::pluck('id'));
        }

        return redirect()->route('admin.products.index')->with('success', "Loan product '{$product->name}' created.");
    }

    public function show(LoanProduct $product)
    {
        $product->load('branches', 'createdBy');
        return view('admin.products.show', compact('product'));
    }

    public function edit(LoanProduct $product)
    {
        $branches = Branch::active()->get();
        $product->load('branches');
        return view('admin.products.edit', compact('product', 'branches'));
    }

    public function update(Request $request, LoanProduct $product)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string',
            'min_amount'   => 'required|numeric|min:1',
            'max_amount'   => 'required|numeric|gt:min_amount',
            'interest_rate'=> 'required|numeric|min:0',
            'branch_ids'   => 'nullable|array',
        ]);

        $product->update($data);

        if ($request->has('branch_ids')) {
            $product->branches()->sync($request->branch_ids ?? []);
        }

        return redirect()->route('admin.products.show', $product)->with('success', 'Product updated.');
    }

    public function toggle(LoanProduct $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        $status = $product->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Product {$status}.");
    }

    public function destroy(LoanProduct $product)
    {
        if ($product->loans()->exists()) {
            return back()->with('error', 'Cannot delete a product that has loans.');
        }
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function calculator(Request $request)
    {
        $products = LoanProduct::active()->get();
        $result   = null;

        if ($request->filled('amount')) {
            $request->validate([
                'product_id' => 'required|exists:loan_products,id',
                'amount'     => 'required|numeric|min:1',
                'term'       => 'required|integer|min:1',
            ]);

            $product  = LoanProduct::findOrFail($request->product_id);
            $schedule = app(\App\Services\Loan\LoanScheduleService::class)->generate(
                principal:          (float) $request->amount,
                annualRatePercent:  (float) $product->interest_rate,
                termMonths:         (int) $request->term,
                interestType:       $product->interest_type,
                frequency:          $product->repayment_frequency,
                firstRepaymentDate: now()->addMonth(),
                processingFee:      $product->calculateProcessingFee($request->amount),
                insuranceFee:       $product->calculateInsuranceFee($request->amount),
                adminFee:           $product->calculateAdminFee($request->amount),
            );

            $result = [
                'schedule'        => $schedule,
                'total_interest'  => $schedule->sum('interest_due'),
                'total_fees'      => $schedule->sum('fees_due'),
                'total_repayable' => $schedule->sum('total_due'),
                'installment'     => $schedule->first()['total_due'] ?? 0,
                'product'         => $product,
            ];
        }

        return view('admin.products.calculator', compact('products', 'result'));
    }
}

// ─── Branch Controller ────────────────────────────────────────────────────────

class BranchController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:branches.view')->only(['index', 'show']);
        $this->middleware('permission:branches.create')->only(['create', 'store']);
        $this->middleware('permission:branches.edit')->only(['edit', 'update']);
        $this->middleware('permission:branches.delete')->only(['destroy']);
    }

    public function index()
    {
        $branches = Branch::withCount(['users', 'borrowers', 'loans'])->latest()->paginate(20);
        return view('admin.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('admin.branches.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'code'          => 'required|string|max:20|unique:branches',
            'address'       => 'required|string',
            'region'        => 'nullable|string',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email',
            'manager_name'  => 'nullable|string',
            'is_head_office'=> 'boolean',
        ]);

        $branch = Branch::create($data);
        return redirect()->route('admin.branches.show', $branch)->with('success', "Branch '{$branch->name}' created.");
    }

    public function show(Branch $branch)
    {
        $branch->load('users');
        $stats = [
            'active_loans'      => $branch->loans()->whereIn('status', ['active', 'overdue'])->count(),
            'total_outstanding' => $branch->loans()->whereIn('status', ['active', 'overdue'])->sum('outstanding_principal'),
            'total_borrowers'   => $branch->borrowers()->count(),
            'par30'             => $branch->portfolio_at_risk,
        ];
        return view('admin.branches.show', compact('branch', 'stats'));
    }

    public function edit(Branch $branch)
    {
        return view('admin.branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'address'      => 'required|string',
            'region'       => 'nullable|string',
            'phone'        => 'nullable|string',
            'email'        => 'nullable|email',
            'manager_name' => 'nullable|string',
        ]);

        $branch->update($data);
        return redirect()->route('admin.branches.show', $branch)->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->users()->exists() || $branch->loans()->whereIn('status', ['active', 'overdue'])->exists()) {
            return back()->with('error', 'Cannot delete a branch with active users or loans.');
        }
        $branch->delete();
        return redirect()->route('admin.branches.index')->with('success', 'Branch deleted.');
    }
}

// ─── User Controller ──────────────────────────────────────────────────────────

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users.view')->only(['index', 'show']);
        $this->middleware('permission:users.create')->only(['create', 'store']);
        $this->middleware('permission:users.edit')->only(['edit', 'update', 'resetPassword', 'toggleStatus']);
        $this->middleware('permission:users.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = User::with(['branch', 'roles'])
            ->staff()
            ->when($request->role, fn($q) => $q->role($request->role))
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"))
            ->latest();

        $users   = $query->paginate(20)->withQueryString();
        $roles   = \Spatie\Permission\Models\Role::all();
        $branches= Branch::active()->get();
        return view('admin.users.index', compact('users', 'roles', 'branches'));
    }

    public function create()
    {
        $roles   = \Spatie\Permission\Models\Role::where('name', '!=', 'borrower')->get();
        $branches= Branch::active()->get();
        return view('admin.users.create', compact('roles', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users',
            'phone'       => 'nullable|string|max:20',
            'employee_id' => 'nullable|string|max:50|unique:users',
            'branch_id'   => 'nullable|exists:branches,id',
            'role'        => 'required|string|exists:roles,name',
            'password'    => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'phone'                => $data['phone'] ?? null,
            'employee_id'          => $data['employee_id'] ?? null,
            'branch_id'            => $data['branch_id'] ?? null,
            'password'             => Hash::make($data['password']),
            'must_change_password' => true,
            'is_active'            => true,
        ]);

        $user->assignRole($data['role']);

        activity('users')->causedBy(Auth::user())->performedOn($user)->log('User created');
        return redirect()->route('admin.users.show', $user)->with('success', "User {$user->name} created.");
    }

    public function show(User $user)
    {
        $user->load(['branch', 'roles', 'loans' => fn($q) => $q->latest()->take(10)]);
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles   = \Spatie\Permission\Models\Role::where('name', '!=', 'borrower')->get();
        $branches= Branch::active()->get();
        return view('admin.users.edit', compact('user', 'roles', 'branches'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'branch_id' => 'nullable|exists:branches,id',
            'role'      => 'nullable|string|exists:roles,name',
        ]);

        $user->update($data);

        if ($request->filled('role') && !$user->isSuperAdmin()) {
            $user->syncRoles([$request->role]);
        }

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->update(['password' => Hash::make($request->password), 'must_change_password' => true]);
        activity('users')->causedBy(Auth::user())->performedOn($user)->log('Password reset by admin');
        return back()->with('success', 'Password reset. User will be prompted to change on next login.');
    }

    public function toggleStatus(User $user)
    {
        if ($user->isSuperAdmin()) return back()->with('error', 'Cannot deactivate Super Admin.');
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User {$status}.");
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) return back()->with('error', 'Cannot delete your own account.');
        if ($user->isSuperAdmin()) return back()->with('error', 'Cannot delete Super Admin.');
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}

// ─── AI Controller ────────────────────────────────────────────────────────────

class AIController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ai.use');
    }

    public function chat(Request $request)
    {
        $request->validate(['message' => 'required|string|max:500']);
        $history = $request->session()->get('ai_chat_history', []);
        $response = app(\App\Services\AI\AIService::class)->chatAssistant($request->message, $history);

        // Update history
        $history[] = ['role' => 'user',      'content' => $request->message];
        $history[] = ['role' => 'assistant', 'content' => $response];
        $request->session()->put('ai_chat_history', array_slice($history, -20));

        return response()->json(['response' => $response]);
    }

    public function clearChat(Request $request)
    {
        $request->session()->forget('ai_chat_history');
        return response()->json(['success' => true]);
    }

    public function assessLoan(Request $request, \App\Models\Loan $loan)
    {
        $this->middleware('permission:ai.credit_analysis');
        $result = app(\App\Services\AI\AIService::class)->assessCreditRisk($loan);
        return response()->json($result);
    }

    public function generateMessage(Request $request, \App\Models\Loan $loan)
    {
        $request->validate(['tone' => 'required|in:friendly,firm_professional,urgent']);
        $message = app(\App\Services\AI\AIService::class)->generateCollectionMessage($loan, $request->tone);
        return response()->json(['message' => $message]);
    }
}
