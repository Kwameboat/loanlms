<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\Repayment;
use App\Models\LoanStatusHistory;
use App\Models\LedgerEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\Loan\LoanScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermissionsSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            LoanProductSeeder::class,
            SettingsSeeder::class,
            BorrowerSeeder::class,
            LoanSeeder::class,
        ]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = config('bigcash.permissions', []);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'super_admin' => $permissions, // All permissions
            'admin' => array_filter($permissions, fn($p) => !in_array($p, ['settings.edit'])),
            'branch_manager' => [
                'borrowers.view', 'borrowers.create', 'borrowers.edit', 'borrowers.export',
                'loans.view', 'loans.create', 'loans.edit', 'loans.approve', 'loans.recommend',
                'loans.disburse', 'loans.export', 'loans.reschedule',
                'repayments.view', 'repayments.create', 'repayments.edit', 'repayments.export',
                'products.view', 'reports.view', 'reports.export',
                'users.view', 'accounting.view', 'accounting.export',
                'ai.use', 'ai.credit_analysis',
            ],
            'loan_officer' => [
                'borrowers.view', 'borrowers.create', 'borrowers.edit',
                'loans.view', 'loans.create', 'loans.edit', 'loans.recommend',
                'repayments.view', 'repayments.create',
                'products.view', 'reports.view',
                'ai.use', 'ai.credit_analysis',
            ],
            'accountant' => [
                'borrowers.view', 'loans.view', 'loans.disburse',
                'repayments.view', 'repayments.create', 'repayments.edit', 'repayments.export',
                'reports.view', 'reports.export',
                'accounting.view', 'accounting.export',
            ],
            'collector' => [
                'borrowers.view', 'loans.view',
                'repayments.view', 'repayments.create',
                'reports.view',
            ],
            'borrower' => [],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        $this->command->info('Roles & Permissions seeded.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['name' => 'Head Office - Accra', 'code' => 'HO-ACC', 'address' => 'No. 12 Independence Avenue, Accra', 'region' => 'Greater Accra', 'phone' => '+233302000001', 'email' => 'headoffice@bigcash.com', 'is_head_office' => true],
            ['name' => 'Kumasi Branch',        'code' => 'KSI',    'address' => 'Adum, Kumasi, Ashanti Region',    'region' => 'Ashanti',       'phone' => '+233322000002', 'email' => 'kumasi@bigcash.com'],
            ['name' => 'Takoradi Branch',      'code' => 'TKD',    'address' => 'Market Circle, Takoradi',          'region' => 'Western',       'phone' => '+233312000003', 'email' => 'takoradi@bigcash.com'],
            ['name' => 'Tamale Branch',        'code' => 'TML',    'address' => 'Central Market Area, Tamale',      'region' => 'Northern',      'phone' => '+233372000004', 'email' => 'tamale@bigcash.com'],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(['code' => $branch['code']], array_merge($branch, ['is_active' => true]));
        }
        $this->command->info('Branches seeded.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $hq      = Branch::where('code', 'HO-ACC')->first();
        $kumasi  = Branch::where('code', 'KSI')->first();
        $takoradi= Branch::where('code', 'TKD')->first();

        $users = [
            // Super Admin
            ['name' => 'Kwame Mensah', 'email' => 'admin@bigcash.com', 'role' => 'super_admin', 'branch_id' => $hq->id, 'employee_id' => 'SA001'],
            // Admins
            ['name' => 'Ama Owusu',    'email' => 'admin2@bigcash.com', 'role' => 'admin', 'branch_id' => $hq->id, 'employee_id' => 'AD001'],
            // Branch Managers
            ['name' => 'Kofi Boateng', 'email' => 'manager.kumasi@bigcash.com', 'role' => 'branch_manager', 'branch_id' => $kumasi->id, 'employee_id' => 'BM001'],
            ['name' => 'Akosua Darko', 'email' => 'manager.takoradi@bigcash.com', 'role' => 'branch_manager', 'branch_id' => $takoradi->id, 'employee_id' => 'BM002'],
            // Loan Officers
            ['name' => 'Yaw Asante',   'email' => 'officer1@bigcash.com', 'role' => 'loan_officer', 'branch_id' => $hq->id, 'employee_id' => 'LO001'],
            ['name' => 'Abena Sarpong','email' => 'officer2@bigcash.com', 'role' => 'loan_officer', 'branch_id' => $kumasi->id, 'employee_id' => 'LO002'],
            ['name' => 'Kwesi Acheampong', 'email' => 'officer3@bigcash.com', 'role' => 'loan_officer', 'branch_id' => $takoradi->id, 'employee_id' => 'LO003'],
            // Accountants
            ['name' => 'Efua Appiah',  'email' => 'accountant@bigcash.com', 'role' => 'accountant', 'branch_id' => $hq->id, 'employee_id' => 'AC001'],
            // Collectors
            ['name' => 'Nana Bediako', 'email' => 'collector1@bigcash.com', 'role' => 'collector', 'branch_id' => $hq->id, 'employee_id' => 'CO001'],
            ['name' => 'Adjoa Frimpong','email' => 'collector2@bigcash.com', 'role' => 'collector', 'branch_id' => $kumasi->id, 'employee_id' => 'CO002'],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password'  => Hash::make('Password@123'),
                    'is_active' => true,
                ])
            );
            $user->syncRoles([$role]);
        }

        $this->command->info('Users seeded. Default password: Password@123');
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@bigcash.com')->first();

        $products = [
            [
                'name' => 'Salary Backed Loan', 'code' => 'SAL001', 'product_type' => 'salary_loan',
                'description' => 'Loan for salaried workers backed by employment.',
                'min_amount' => 500, 'max_amount' => 50000, 'min_term' => 1, 'max_term' => 24,
                'interest_type' => 'flat', 'interest_rate' => 36, 'interest_period' => 'per_annum',
                'processing_fee' => 2, 'insurance_fee' => 1, 'admin_fee' => 0.5,
                'repayment_frequency' => 'monthly', 'grace_period_days' => 3,
                'penalty_enabled' => true, 'penalty_type' => 'percentage', 'penalty_rate' => 5,
                'penalty_grace_days' => 5, 'requires_guarantor' => false,
                'approval_levels' => 1, 'min_age' => 21, 'max_age' => 60,
                'min_monthly_income' => 500,
            ],
            [
                'name' => 'Business Loan', 'code' => 'BIZ001', 'product_type' => 'business_loan',
                'description' => 'For business owners and entrepreneurs.',
                'min_amount' => 1000, 'max_amount' => 200000, 'min_term' => 3, 'max_term' => 36,
                'interest_type' => 'reducing', 'interest_rate' => 30, 'interest_period' => 'per_annum',
                'processing_fee' => 2.5, 'insurance_fee' => 1.5, 'admin_fee' => 1,
                'repayment_frequency' => 'monthly', 'grace_period_days' => 5,
                'penalty_enabled' => true, 'penalty_type' => 'percentage', 'penalty_rate' => 3,
                'penalty_grace_days' => 7, 'requires_guarantor' => true,
                'approval_levels' => 2, 'min_age' => 21, 'max_age' => 65,
            ],
            [
                'name' => 'Emergency Loan', 'code' => 'EMG001', 'product_type' => 'emergency_loan',
                'description' => 'Quick short-term emergency financing.',
                'min_amount' => 100, 'max_amount' => 5000, 'min_term' => 1, 'max_term' => 6,
                'interest_type' => 'flat', 'interest_rate' => 48, 'interest_period' => 'per_annum',
                'processing_fee' => 3, 'insurance_fee' => 0, 'admin_fee' => 0,
                'repayment_frequency' => 'monthly', 'grace_period_days' => 0,
                'penalty_enabled' => true, 'penalty_type' => 'percentage', 'penalty_rate' => 10,
                'penalty_grace_days' => 0, 'requires_guarantor' => false,
                'approval_levels' => 1, 'min_age' => 18, 'max_age' => 70,
            ],
            [
                'name' => 'Personal Loan', 'code' => 'PER001', 'product_type' => 'personal_loan',
                'description' => 'General purpose personal loan.',
                'min_amount' => 500, 'max_amount' => 30000, 'min_term' => 3, 'max_term' => 24,
                'interest_type' => 'flat', 'interest_rate' => 42, 'interest_period' => 'per_annum',
                'processing_fee' => 2, 'insurance_fee' => 1, 'admin_fee' => 0,
                'repayment_frequency' => 'monthly', 'grace_period_days' => 3,
                'penalty_enabled' => true, 'penalty_type' => 'percentage', 'penalty_rate' => 5,
                'penalty_grace_days' => 3, 'requires_guarantor' => false,
                'approval_levels' => 1, 'min_age' => 21, 'max_age' => 65,
            ],
            [
                'name' => 'Susu Micro Loan', 'code' => 'MCR001', 'product_type' => 'microloan',
                'description' => 'Small weekly/biweekly loans for market traders.',
                'min_amount' => 50, 'max_amount' => 2000, 'min_term' => 1, 'max_term' => 4,
                'interest_type' => 'flat', 'interest_rate' => 60, 'interest_period' => 'per_annum',
                'processing_fee' => 2, 'insurance_fee' => 0, 'admin_fee' => 0,
                'repayment_frequency' => 'weekly', 'grace_period_days' => 0,
                'penalty_enabled' => true, 'penalty_type' => 'fixed', 'penalty_fixed_amount' => 5,
                'penalty_grace_days' => 0, 'requires_guarantor' => false,
                'approval_levels' => 1, 'min_age' => 18, 'max_age' => 70,
            ],
        ];

        $branches = Branch::pluck('id')->toArray();

        foreach ($products as $productData) {
            $product = LoanProduct::firstOrCreate(
                ['code' => $productData['code']],
                array_merge($productData, ['is_active' => true, 'created_by' => $adminUser->id])
            );
            $product->branches()->sync($branches);
        }

        $this->command->info('Loan products seeded.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Company
            ['key' => 'company_name',    'value' => 'Big Cash Finance Ltd',        'group' => 'company', 'label' => 'Company Name',    'is_public' => true],
            ['key' => 'company_address', 'value' => 'No. 12 Independence Avenue, Accra, Ghana', 'group' => 'company', 'label' => 'Address'],
            ['key' => 'company_phone',   'value' => '+233302000001',               'group' => 'company', 'label' => 'Phone'],
            ['key' => 'company_email',   'value' => 'info@bigcash.com',           'group' => 'company', 'label' => 'Email'],
            ['key' => 'company_logo',    'value' => '',                            'group' => 'company', 'label' => 'Logo Path'],
            ['key' => 'company_currency','value' => 'GHS',                         'group' => 'company', 'label' => 'Currency', 'is_public' => true],
            ['key' => 'currency_symbol', 'value' => '₵',                          'group' => 'company', 'label' => 'Currency Symbol', 'is_public' => true],
            ['key' => 'receipt_footer',  'value' => 'Thank you for choosing Big Cash Finance. Please keep this receipt for your records.', 'group' => 'company', 'label' => 'Receipt Footer'],
            // Loan
            ['key' => 'max_active_loans_per_borrower', 'value' => '2', 'group' => 'loans', 'label' => 'Max Active Loans Per Borrower'],
            ['key' => 'require_guarantor_above',       'value' => '5000', 'group' => 'loans', 'label' => 'Require Guarantor Above Amount'],
            // Notifications
            ['key' => 'send_sms_on_approval',   'value' => '1', 'group' => 'notifications', 'label' => 'SMS on Approval'],
            ['key' => 'send_sms_on_repayment',  'value' => '1', 'group' => 'notifications', 'label' => 'SMS on Repayment'],
            ['key' => 'reminder_days_before',   'value' => '3', 'group' => 'notifications', 'label' => 'Reminder Days Before Due'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        $this->command->info('Settings seeded.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class BorrowerSeeder extends Seeder
{
    public function run(): void
    {
        $branches  = Branch::all();
        $officerHQ = User::where('email', 'officer1@bigcash.com')->first();
        $officerKSI= User::where('email', 'officer2@bigcash.com')->first();

        $borrowers = [
            [
                'branch_id' => $branches[0]->id, 'created_by' => $officerHQ->id,
                'borrower_number' => 'BRW-2024-00001',
                'first_name' => 'Kwabena', 'last_name' => 'Asante', 'gender' => 'male',
                'date_of_birth' => '1988-04-15', 'ghana_card_number' => 'GHA-123456789-1',
                'primary_phone' => '0244123456', 'email' => 'kwabena@example.com',
                'employment_status' => 'employed', 'occupation' => 'Teacher',
                'employer_name' => 'Ghana Education Service', 'monthly_income' => 2500,
                'residential_address' => 'East Legon, Accra', 'region' => 'Greater Accra',
                'district' => 'La Nkwantanang-Madina', 'town_city' => 'Accra',
                'next_of_kin_name' => 'Akua Asante', 'next_of_kin_relationship' => 'Spouse',
                'next_of_kin_phone' => '0244654321', 'status' => 'active',
                'bank_name' => 'GCB Bank', 'account_number' => '1234567890',
                'mobile_money_number' => '0244123456', 'mobile_money_provider' => 'mtn',
            ],
            [
                'branch_id' => $branches[0]->id, 'created_by' => $officerHQ->id,
                'borrower_number' => 'BRW-2024-00002',
                'first_name' => 'Ama', 'last_name' => 'Boateng', 'gender' => 'female',
                'date_of_birth' => '1992-08-20', 'ghana_card_number' => 'GHA-987654321-2',
                'primary_phone' => '0551987654', 'email' => 'ama@example.com',
                'employment_status' => 'self_employed', 'occupation' => 'Trader',
                'business_name' => 'Ama Fashion Hub', 'monthly_business_revenue' => 4000,
                'monthly_income' => 3000,
                'residential_address' => 'Madina, Accra', 'region' => 'Greater Accra',
                'district' => 'La Nkwantanang-Madina', 'town_city' => 'Accra',
                'next_of_kin_name' => 'Yaw Boateng', 'next_of_kin_relationship' => 'Brother',
                'next_of_kin_phone' => '0200112233', 'status' => 'active',
                'mobile_money_number' => '0551987654', 'mobile_money_provider' => 'vodafone',
            ],
            [
                'branch_id' => $branches[1]->id, 'created_by' => $officerKSI->id,
                'borrower_number' => 'BRW-2024-00003',
                'first_name' => 'Kofi', 'last_name' => 'Oppong', 'gender' => 'male',
                'date_of_birth' => '1985-12-05', 'ghana_card_number' => 'GHA-456789123-3',
                'primary_phone' => '0270445566', 'email' => 'kofi.oppong@example.com',
                'employment_status' => 'employed', 'occupation' => 'Accountant',
                'employer_name' => 'Kumasi Hive Ltd', 'monthly_income' => 4500,
                'residential_address' => 'Bantama, Kumasi', 'region' => 'Ashanti',
                'district' => 'Kumasi Metropolitan', 'town_city' => 'Kumasi',
                'next_of_kin_name' => 'Abena Oppong', 'next_of_kin_relationship' => 'Wife',
                'next_of_kin_phone' => '0270998877', 'status' => 'active',
                'bank_name' => 'Ecobank', 'account_number' => '9876543210',
                'mobile_money_number' => '0270445566', 'mobile_money_provider' => 'airteltigo',
            ],
            [
                'branch_id' => $branches[0]->id, 'created_by' => $officerHQ->id,
                'borrower_number' => 'BRW-2024-00004',
                'first_name' => 'Efua', 'last_name' => 'Mensah', 'gender' => 'female',
                'date_of_birth' => '1995-03-22', 'ghana_card_number' => 'GHA-321654987-4',
                'primary_phone' => '0246778899', 'email' => 'efua.mensah@example.com',
                'employment_status' => 'business_owner', 'occupation' => 'Restaurant Owner',
                'business_name' => "Efua's Kitchen", 'monthly_business_revenue' => 8000,
                'monthly_income' => 5000,
                'residential_address' => 'Airport Residential Area, Accra', 'region' => 'Greater Accra',
                'district' => 'Ayawaso East', 'town_city' => 'Accra',
                'next_of_kin_name' => 'Kwame Mensah', 'next_of_kin_relationship' => 'Father',
                'next_of_kin_phone' => '0244111222', 'status' => 'active',
                'mobile_money_number' => '0246778899', 'mobile_money_provider' => 'mtn',
            ],
        ];

        foreach ($borrowers as $borrowerData) {
            Borrower::firstOrCreate(
                ['borrower_number' => $borrowerData['borrower_number']],
                $borrowerData
            );
        }

        // Create borrower user accounts for portal access
        foreach (Borrower::all() as $borrower) {
            if ($borrower->email && !User::where('email', $borrower->email)->exists()) {
                $user = User::create([
                    'branch_id'    => $borrower->branch_id,
                    'name'         => $borrower->display_name,
                    'email'        => $borrower->email,
                    'phone'        => $borrower->primary_phone,
                    'password'     => Hash::make('Borrower@123'),
                    'is_active'    => true,
                ]);
                $user->assignRole('borrower');
            }
        }

        $this->command->info('Borrowers seeded. Borrower portal password: Borrower@123');
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $scheduleService = app(LoanScheduleService::class);
        $officer  = User::where('email', 'officer1@bigcash.com')->first();
        $approver = User::where('email', 'admin@bigcash.com')->first();
        $disbUser = User::where('email', 'accountant@bigcash.com')->first();
        $salaryProduct  = LoanProduct::where('code', 'SAL001')->first();
        $bizProduct     = LoanProduct::where('code', 'BIZ001')->first();
        $emerProduct    = LoanProduct::where('code', 'EMG001')->first();

        $borrower1 = Borrower::where('borrower_number', 'BRW-2024-00001')->first();
        $borrower2 = Borrower::where('borrower_number', 'BRW-2024-00002')->first();
        $borrower3 = Borrower::where('borrower_number', 'BRW-2024-00003')->first();

        // ── Active Loan (Borrower 1 - salary) ────────────────────────────────
        if (!Loan::where('loan_number', 'LN-2024-00001')->exists()) {
            $loan = Loan::create([
                'loan_number'            => 'LN-2024-00001',
                'branch_id'              => $borrower1->branch_id,
                'borrower_id'            => $borrower1->id,
                'loan_product_id'        => $salaryProduct->id,
                'loan_officer_id'        => $officer->id,
                'created_by'             => $officer->id,
                'requested_amount'       => 5000,
                'approved_amount'        => 5000,
                'disbursed_amount'       => 5000,
                'term_months'            => 12,
                'repayment_frequency'    => 'monthly',
                'interest_type'          => 'flat',
                'interest_rate'          => 36,
                'processing_fee_amount'  => 100, // 2%
                'insurance_fee_amount'   => 50,  // 1%
                'admin_fee_amount'       => 25,
                'loan_purpose'           => 'Home renovation and furniture purchase',
                'application_date'       => now()->subMonths(4),
                'disbursement_date'      => now()->subMonths(4),
                'first_repayment_date'   => now()->subMonths(3),
                'disbursement_method'    => 'mobile_money',
                'disbursement_account'   => '0244123456',
                'disbursed_by'           => $disbUser->id,
                'approved_by'            => $approver->id,
                'approved_at'            => now()->subMonths(4)->addDays(1),
                'status'                 => 'active',
                'outstanding_principal'  => 5000,
                'debt_to_income_ratio'   => 22.5,
            ]);

            $scheduleService->persistSchedule($loan);

            // Record 3 repayments
            $schedules = $loan->schedule()->orderBy('installment_number')->take(3)->get();
            $collector = User::where('email', 'collector1@bigcash.com')->first();

            foreach ($schedules as $idx => $schedule) {
                $repayment = Repayment::create([
                    'receipt_number'    => 'RCT-20240' . ($idx + 1) . '-' . str_pad($idx + 1, 5, '0', STR_PAD_LEFT),
                    'loan_id'           => $loan->id,
                    'borrower_id'       => $borrower1->id,
                    'branch_id'         => $loan->branch_id,
                    'collected_by'      => $collector->id,
                    'amount'            => $schedule->total_due,
                    'payment_method'    => 'mobile_money',
                    'mobile_money_provider' => 'mtn',
                    'mobile_money_number'   => '0244123456',
                    'payment_date'      => $schedule->due_date,
                    'status'            => 'confirmed',
                    'notes'             => 'MoMo payment',
                    'repayment_schedule_id' => $schedule->id,
                ]);

                // Update schedule
                $schedule->update([
                    'principal_paid' => $schedule->principal_due,
                    'interest_paid'  => $schedule->interest_due,
                    'fees_paid'      => $schedule->fees_due,
                    'total_paid'     => $schedule->total_due,
                    'status'         => 'paid',
                    'paid_date'      => $schedule->due_date,
                ]);

                LedgerEntry::create([
                    'branch_id'    => $loan->branch_id,
                    'loan_id'      => $loan->id,
                    'repayment_id' => $repayment->id,
                    'created_by'   => $collector->id,
                    'entry_type'   => 'repayment_received',
                    'debit_credit' => 'credit',
                    'amount'       => $schedule->total_due,
                    'description'  => "Repayment for {$loan->loan_number}",
                    'entry_date'   => $schedule->due_date,
                    'reference'    => $repayment->receipt_number,
                ]);
            }

            $scheduleService->recalculateLoanBalances($loan);

            LoanStatusHistory::create([
                'loan_id'    => $loan->id,
                'changed_by' => $officer->id,
                'from_status'=> null,
                'to_status'  => 'active',
                'note'       => 'Loan disbursed and activated (seed data)',
            ]);
        }

        // ── Overdue Loan (Borrower 2) ─────────────────────────────────────────
        if (!Loan::where('loan_number', 'LN-2024-00002')->exists()) {
            $officerKSI = User::where('email', 'officer2@bigcash.com')->first();
            $loan2 = Loan::create([
                'loan_number'           => 'LN-2024-00002',
                'branch_id'             => $borrower3->branch_id,
                'borrower_id'           => $borrower3->id,
                'loan_product_id'       => $bizProduct->id,
                'loan_officer_id'       => $officerKSI->id,
                'created_by'            => $officerKSI->id,
                'requested_amount'      => 15000,
                'approved_amount'       => 12000,
                'disbursed_amount'      => 12000,
                'term_months'           => 18,
                'repayment_frequency'   => 'monthly',
                'interest_type'         => 'reducing',
                'interest_rate'         => 30,
                'processing_fee_amount' => 300,
                'insurance_fee_amount'  => 180,
                'admin_fee_amount'      => 120,
                'loan_purpose'          => 'Business expansion - purchase of stock and equipment',
                'application_date'      => now()->subMonths(6),
                'disbursement_date'     => now()->subMonths(6),
                'first_repayment_date'  => now()->subMonths(5),
                'disbursement_method'   => 'bank_transfer',
                'disbursement_account'  => '9876543210',
                'disbursed_by'          => $disbUser->id,
                'approved_by'           => $approver->id,
                'approved_at'           => now()->subMonths(6)->addDays(2),
                'status'                => 'overdue',
                'is_overdue'            => true,
                'days_past_due'         => 45,
                'outstanding_principal' => 12000,
            ]);

            $scheduleService->persistSchedule($loan2);

            // Mark overdue
            $loan2->schedule()->where('installment_number', '<=', 3)->update(['status' => 'overdue', 'is_overdue' => true]);
            $scheduleService->recalculateLoanBalances($loan2);
        }

        // ── Pending Application (Borrower 2) ─────────────────────────────────
        if (!Loan::where('loan_number', 'LN-2024-00003')->exists()) {
            Loan::create([
                'loan_number'           => 'LN-2024-00003',
                'branch_id'             => $borrower2->branch_id,
                'borrower_id'           => $borrower2->id,
                'loan_product_id'       => $emerProduct->id,
                'loan_officer_id'       => $officer->id,
                'created_by'            => $officer->id,
                'requested_amount'      => 3000,
                'term_months'           => 3,
                'repayment_frequency'   => 'monthly',
                'interest_type'         => 'flat',
                'interest_rate'         => 48,
                'loan_purpose'          => 'Medical emergency expenses',
                'application_date'      => now()->subDays(3),
                'status'                => 'under_review',
                'outstanding_principal' => 0,
            ]);
        }

        $this->command->info('Demo loans seeded.');
    }
}
