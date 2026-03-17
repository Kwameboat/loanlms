<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Settings
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name'             => env('COMPANY_NAME', 'Big Cash Finance'),
        'currency'         => env('COMPANY_CURRENCY', 'GHS'),
        'currency_symbol'  => env('COMPANY_CURRENCY_SYMBOL', '₵'),
        'timezone'         => env('COMPANY_TIMEZONE', 'Africa/Accra'),
        'date_format'      => env('COMPANY_DATE_FORMAT', 'd/m/Y'),
        'phone'            => env('COMPANY_PHONE', ''),
        'email'            => env('COMPANY_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Loan Settings
    |--------------------------------------------------------------------------
    */
    'loan' => [
        'statuses' => [
            'draft'            => 'Draft',
            'submitted'        => 'Submitted',
            'under_review'     => 'Under Review',
            'pending_documents'=> 'Pending Documents',
            'recommended'      => 'Recommended',
            'approved'         => 'Approved',
            'rejected'         => 'Rejected',
            'disbursed'        => 'Disbursed',
            'active'           => 'Active',
            'overdue'          => 'Overdue',
            'completed'        => 'Completed',
            'defaulted'        => 'Defaulted',
            'written_off'      => 'Written Off',
            'rescheduled'      => 'Rescheduled',
        ],
        'status_colors' => [
            'draft'            => 'secondary',
            'submitted'        => 'info',
            'under_review'     => 'primary',
            'pending_documents'=> 'warning',
            'recommended'      => 'info',
            'approved'         => 'success',
            'rejected'         => 'danger',
            'disbursed'        => 'primary',
            'active'           => 'success',
            'overdue'          => 'danger',
            'completed'        => 'dark',
            'defaulted'        => 'danger',
            'written_off'      => 'secondary',
            'rescheduled'      => 'warning',
        ],
        'interest_types' => [
            'flat'             => 'Flat Rate',
            'reducing'         => 'Reducing Balance',
        ],
        'repayment_frequencies' => [
            'daily'    => 'Daily',
            'weekly'   => 'Weekly',
            'biweekly' => 'Bi-Weekly',
            'monthly'  => 'Monthly',
            'custom'   => 'Custom',
        ],
        'penalty_allocation_order' => ['penalty', 'fees', 'interest', 'principal'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ghana Public Holidays (current year - update annually via seeder)
    |--------------------------------------------------------------------------
    */
    'ghana_holidays' => [
        '01-01', // New Year's Day
        '01-07', // Constitution Day
        '03-06', // Independence Day
        '05-01', // Workers Day
        '07-01', // Republic Day
        '08-04', // Founders Day
        '09-21', // Kwame Nkrumah Memorial Day
        '12-25', // Christmas Day
        '12-26', // Boxing Day
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'provider'  => env('SMS_PROVIDER', 'log'),
        'api_key'   => env('SMS_API_KEY', ''),
        'api_url'   => env('SMS_API_URL', ''),
        'sender_id' => env('SMS_SENDER_ID', 'Big Cash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'enabled' => env('AI_ENABLED', false),
        'model'   => env('AI_MODEL', 'gpt-4o-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paystack
    |--------------------------------------------------------------------------
    */
    'paystack' => [
        'mode'           => env('PAYSTACK_MODE', 'test'),
        'public_key'     => env('PAYSTACK_PUBLIC_KEY', ''),
        'secret_key'     => env('PAYSTACK_SECRET_KEY', ''),
        'payment_url'    => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
        'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'max_size'     => env('MAX_FILE_SIZE', 5120),
        'allowed_types'=> explode(',', env('ALLOWED_FILE_TYPES', 'pdf,jpg,jpeg,png,doc,docx')),
        'disk'         => 'public',
        'paths' => [
            'kyc'           => 'kyc_documents',
            'loan'          => 'loan_documents',
            'receipts'      => 'receipts',
            'company'       => 'company',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'super_admin'    => 'Super Admin',
        'admin'          => 'Admin',
        'branch_manager' => 'Branch Manager',
        'loan_officer'   => 'Loan Officer',
        'accountant'     => 'Accountant',
        'collector'      => 'Collector',
        'borrower'       => 'Borrower',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions list
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        // Borrowers
        'borrowers.view', 'borrowers.create', 'borrowers.edit', 'borrowers.delete',
        'borrowers.view_all', 'borrowers.export',
        // Loans
        'loans.view', 'loans.create', 'loans.edit', 'loans.delete',
        'loans.view_all', 'loans.approve', 'loans.reject', 'loans.disburse',
        'loans.recommend', 'loans.write_off', 'loans.reschedule', 'loans.export',
        // Repayments
        'repayments.view', 'repayments.create', 'repayments.edit',
        'repayments.reverse', 'repayments.bulk_upload', 'repayments.export',
        // Loan Products
        'products.view', 'products.create', 'products.edit', 'products.delete',
        // Branches
        'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
        // Users
        'users.view', 'users.create', 'users.edit', 'users.delete',
        // Reports
        'reports.view', 'reports.export',
        // Settings
        'settings.view', 'settings.edit',
        // AI
        'ai.use', 'ai.credit_analysis',
        // Accounting
        'accounting.view', 'accounting.export',
        // Notifications
        'notifications.send', 'notifications.manage',
    ],
];
