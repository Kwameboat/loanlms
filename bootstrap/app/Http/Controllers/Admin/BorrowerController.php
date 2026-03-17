<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Borrower\StoreBorrowerRequest;
use App\Http\Requests\Borrower\UpdateBorrowerRequest;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\BorrowerDocument;
use App\Models\BorrowerNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BorrowerController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:borrowers.view')->only(['index', 'show']);
        $this->middleware('permission:borrowers.create')->only(['create', 'store']);
        $this->middleware('permission:borrowers.edit')->only(['edit', 'update']);
        $this->middleware('permission:borrowers.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->getBranchId($user, $request);

        $query = Borrower::with(['branch', 'createdBy'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($request->search, fn($q) => $q->search($request->search))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->branch_id && ($user->isSuperAdmin() || $user->hasRole('admin')),
                fn($q) => $q->where('branch_id', $request->branch_id))
            ->latest();

        $borrowers = $query->paginate(20)->withQueryString();
        $branches  = Branch::active()->get();

        return view('admin.borrowers.index', compact('borrowers', 'branches'));
    }

    public function create()
    {
        $branches = Branch::active()->get();
        $user     = Auth::user();
        return view('admin.borrowers.create', compact('branches', 'user'));
    }

    public function store(StoreBorrowerRequest $request)
    {
        DB::transaction(function () use ($request, &$borrower) {
            $data = $request->validated();
            $data['created_by']       = Auth::id();
            $data['borrower_number']  = Borrower::generateBorrowerNumber();

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')
                    ->store('kyc_documents/photos', 'public');
            }

            $borrower = Borrower::create($data);

            // Handle document uploads
            $docTypes = config('bigcash.uploads.paths');
            $uploadableDocTypes = [
                'ghana_card', 'passport_photo', 'payslip', 'bank_statement',
                'business_registration', 'utility_bill', 'guarantor_id',
                'signed_loan_agreement', 'other',
            ];

            foreach ($uploadableDocTypes as $docType) {
                if ($request->hasFile("documents.{$docType}")) {
                    $file = $request->file("documents.{$docType}");
                    $path = $file->store('kyc_documents', 'public');

                    BorrowerDocument::create([
                        'borrower_id'   => $borrower->id,
                        'uploaded_by'   => Auth::id(),
                        'document_type' => $docType,
                        'document_name' => ucwords(str_replace('_', ' ', $docType)),
                        'file_path'     => $path,
                        'file_type'     => $file->getClientOriginalExtension(),
                        'file_size'     => $file->getSize(),
                    ]);
                }
            }

            // Create guarantor if provided
            if ($request->filled('guarantor_name')) {
                $guarantorData = [
                    'borrower_id'  => $borrower->id,
                    'name'         => $request->guarantor_name,
                    'phone'        => $request->guarantor_phone,
                    'email'        => $request->guarantor_email,
                    'ghana_card_number' => $request->guarantor_ghana_card,
                    'relationship' => $request->guarantor_relationship,
                    'address'      => $request->guarantor_address,
                    'occupation'   => $request->guarantor_occupation,
                    'employer'     => $request->guarantor_employer,
                    'monthly_income' => $request->guarantor_monthly_income,
                ];

                if ($request->hasFile('guarantor_id_document')) {
                    $gFile = $request->file('guarantor_id_document');
                    $guarantorData['id_document'] = $gFile->store('kyc_documents/guarantors', 'public');
                }

                $borrower->guarantors()->create($guarantorData);
            }

            // Create borrower user account
            if ($request->boolean('create_portal_account') && $borrower->email) {
                $this->createBorrowerUserAccount($borrower);
            }

            activity('borrower')->causedBy(Auth::user())->performedOn($borrower)
                ->log("Borrower {$borrower->borrower_number} created");
        });

        return redirect()->route('admin.borrowers.show', $borrower)
            ->with('success', "Borrower {$borrower->borrower_number} created successfully.");
    }

    public function show(Borrower $borrower)
    {
        $this->authorizeAccess($borrower);

        $borrower->load([
            'branch', 'createdBy', 'documents', 'guarantors',
            'loans.loanProduct', 'loans.repayments', 'notes.createdBy',
        ]);

        $loanStats = [
            'total_loans'        => $borrower->loans()->whereNotIn('status', ['draft', 'rejected'])->count(),
            'active_loans'       => $borrower->activeLoans()->count(),
            'total_borrowed'     => $borrower->loans()->sum('disbursed_amount'),
            'total_repaid'       => $borrower->repayments()->where('status', 'confirmed')->sum('amount'),
            'total_outstanding'  => $borrower->loans()->whereIn('status', ['active', 'overdue'])->sum('total_outstanding'),
            'overdue_loans'      => $borrower->loans()->where('is_overdue', true)->count(),
            'repayment_rate'     => $borrower->repayment_rate,
        ];

        return view('admin.borrowers.show', compact('borrower', 'loanStats'));
    }

    public function edit(Borrower $borrower)
    {
        $this->authorizeAccess($borrower);
        $branches = Branch::active()->get();
        return view('admin.borrowers.edit', compact('borrower', 'branches'));
    }

    public function update(UpdateBorrowerRequest $request, Borrower $borrower)
    {
        $this->authorizeAccess($borrower);

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            if ($borrower->photo) Storage::disk('public')->delete($borrower->photo);
            $data['photo'] = $request->file('photo')->store('kyc_documents/photos', 'public');
        }

        $borrower->update($data);

        activity('borrower')->causedBy(Auth::user())->performedOn($borrower)
            ->log("Borrower {$borrower->borrower_number} updated");

        return redirect()->route('admin.borrowers.show', $borrower)
            ->with('success', 'Borrower profile updated successfully.');
    }

    public function uploadDocument(Request $request, Borrower $borrower)
    {
        $request->validate([
            'document_type' => 'required|string',
            'document'      => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $file = $request->file('document');
        $path = $file->store('kyc_documents', 'public');

        BorrowerDocument::create([
            'borrower_id'   => $borrower->id,
            'uploaded_by'   => Auth::id(),
            'document_type' => $request->document_type,
            'document_name' => $request->document_name ?? ucwords(str_replace('_', ' ', $request->document_type)),
            'file_path'     => $path,
            'file_type'     => $file->getClientOriginalExtension(),
            'file_size'     => $file->getSize(),
        ]);

        return response()->json(['success' => true, 'message' => 'Document uploaded successfully.']);
    }

    public function addNote(Request $request, Borrower $borrower)
    {
        $request->validate([
            'note'       => 'required|string|max:1000',
            'type'       => 'required|in:general,warning,positive,collection,legal',
            'is_private' => 'boolean',
        ]);

        $borrower->notes()->create([
            'created_by' => Auth::id(),
            'note'       => $request->note,
            'type'       => $request->type,
            'is_private' => $request->boolean('is_private'),
        ]);

        return response()->json(['success' => true, 'message' => 'Note added.']);
    }

    public function search(Request $request)
    {
        $term = $request->q;
        $borrowers = Borrower::search($term)
            ->when(Auth::user()->branch_id && !Auth::user()->isSuperAdmin(),
                fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->take(10)
            ->get(['id', 'first_name', 'last_name', 'borrower_number', 'primary_phone', 'ghana_card_number']);

        return response()->json($borrowers->map(fn($b) => [
            'id'    => $b->id,
            'text'  => "{$b->display_name} ({$b->borrower_number}) - {$b->primary_phone}",
            'number'=> $b->borrower_number,
            'phone' => $b->primary_phone,
        ]));
    }

    public function blacklist(Request $request, Borrower $borrower)
    {
        $this->authorize('edit', $borrower);
        $request->validate(['reason' => 'required|string|max:500']);

        $borrower->update(['status' => 'blacklisted', 'blacklist_reason' => $request->reason]);
        activity('borrower')->causedBy(Auth::user())->performedOn($borrower)->log('Borrower blacklisted');

        return back()->with('success', 'Borrower has been blacklisted.');
    }

    public function destroy(Borrower $borrower)
    {
        if ($borrower->activeLoans()->exists()) {
            return back()->with('error', 'Cannot delete a borrower with active loans.');
        }
        $borrower->delete();
        return redirect()->route('admin.borrowers.index')->with('success', 'Borrower deleted.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    protected function getBranchId($user, $request): ?int
    {
        if ($user->isSuperAdmin() || $user->hasRole('admin')) {
            return $request->branch_id ? (int) $request->branch_id : null;
        }
        return $user->branch_id;
    }

    protected function authorizeAccess(Borrower $borrower): void
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasRole('admin') && $user->branch_id !== $borrower->branch_id) {
            abort(403, 'You do not have access to this borrower.');
        }
    }

    protected function createBorrowerUserAccount(Borrower $borrower): void
    {
        $existing = \App\Models\User::where('email', $borrower->email)->first();
        if ($existing) return;

        $tempPassword = \Illuminate\Support\Str::random(12);
        $user = \App\Models\User::create([
            'branch_id'            => $borrower->branch_id,
            'name'                 => $borrower->display_name,
            'email'                => $borrower->email,
            'phone'                => $borrower->primary_phone,
            'password'             => \Illuminate\Support\Facades\Hash::make($tempPassword),
            'must_change_password' => true,
            'is_active'            => true,
        ]);
        $user->assignRole('borrower');

        // Send welcome email with temp credentials
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->send(new \App\Mail\BorrowerWelcomeMail($borrower, $user, $tempPassword));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Welcome email failed', ['borrower' => $borrower->id]);
        }
    }
}
