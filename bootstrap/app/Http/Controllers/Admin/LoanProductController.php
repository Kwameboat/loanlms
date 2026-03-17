<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{LoanProduct, Branch};
use App\Http\Requests\Loan\LoanProductRequest;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class LoanProductController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:super_admin|admin|branch_manager']);
    }

    public function index()
    {
        $products = LoanProduct::with('createdBy')
            ->withCount('loans')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $this->authorize('create', LoanProduct::class);
        $branches = Branch::active()->orderBy('name')->get();
        return view('admin.products.create', compact('branches'));
    }

    public function store(LoanProductRequest $request)
    {
        $this->authorize('create', LoanProduct::class);

        $product = LoanProduct::create(array_merge(
            $request->validated(),
            ['created_by' => auth()->id()]
        ));

        // Attach to branches
        if ($request->filled('branch_ids')) {
            $product->branches()->sync($request->branch_ids);
        }

        activity()->causedBy(auth()->user())
            ->performedOn($product)
            ->log('Created loan product: ' . $product->name);

        return redirect()->route('admin.products.index')
            ->with('success', "Loan product '{$product->name}' created successfully.");
    }

    public function show(LoanProduct $product)
    {
        $product->load('branches', 'createdBy');
        $stats = [
            'total_loans'      => $product->loans()->count(),
            'active_loans'     => $product->loans()->whereIn('status', ['active', 'overdue'])->count(),
            'total_disbursed'  => $product->loans()->whereNotNull('disbursed_amount')->sum('disbursed_amount'),
            'total_outstanding'=> $product->loans()->sum('total_outstanding'),
        ];
        return view('admin.products.show', compact('product', 'stats'));
    }

    public function edit(LoanProduct $product)
    {
        $this->authorize('update', $product);
        $branches = Branch::active()->orderBy('name')->get();
        $product->load('branches');
        return view('admin.products.edit', compact('product', 'branches'));
    }

    public function update(LoanProductRequest $request, LoanProduct $product)
    {
        $this->authorize('update', $product);
        $product->update($request->validated());

        if ($request->filled('branch_ids')) {
            $product->branches()->sync($request->branch_ids);
        }

        return redirect()->route('admin.products.show', $product)
            ->with('success', 'Loan product updated successfully.');
    }

    public function destroy(LoanProduct $product)
    {
        $this->authorize('delete', $product);

        if ($product->loans()->whereIn('status', ['active', 'overdue', 'approved'])->exists()) {
            return back()->with('error', 'Cannot delete product with active loans.');
        }

        $product->delete();
        return redirect()->route('admin.products.index')
            ->with('success', 'Loan product deleted.');
    }

    public function toggle(LoanProduct $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        $status = $product->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Product {$status} successfully.");
    }
}
