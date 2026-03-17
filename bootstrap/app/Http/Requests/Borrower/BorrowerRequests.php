<?php

namespace App\Http\Requests\Borrower;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Personal
            'first_name'             => 'required|string|max:80',
            'last_name'              => 'required|string|max:80',
            'other_names'            => 'nullable|string|max:80',
            'gender'                 => 'required|in:male,female,other',
            'date_of_birth'          => 'required|date|before:-18 years',
            'ghana_card_number'      => 'nullable|string|max:50|unique:borrowers,ghana_card_number',
            'voter_id'               => 'nullable|string|max:50',
            'nationality'            => 'nullable|string|max:60',
            'marital_status'         => 'nullable|in:single,married,divorced,widowed',
            'number_of_dependants'   => 'nullable|integer|min:0',

            // Contact
            'primary_phone'          => 'required|string|max:20',
            'secondary_phone'        => 'nullable|string|max:20',
            'email'                  => 'nullable|email|max:120',
            'whatsapp_number'        => 'nullable|string|max:20',

            // Address
            'residential_address'    => 'required|string|max:500',
            'digital_address'        => 'nullable|string|max:30',
            'nearest_landmark'       => 'nullable|string|max:200',
            'region'                 => 'nullable|string|max:60',
            'district'               => 'nullable|string|max:60',
            'town_city'              => 'nullable|string|max:60',

            // Employment
            'employment_status'      => 'required|in:employed,self_employed,business_owner,unemployed,student,retired',
            'occupation'             => 'nullable|string|max:100',
            'employer_name'          => 'nullable|string|max:150',
            'employer_address'       => 'nullable|string|max:300',
            'employer_phone'         => 'nullable|string|max:20',
            'monthly_income'         => 'nullable|numeric|min:0',

            // Business (conditional)
            'business_name'          => 'nullable|string|max:150',
            'business_registration_number' => 'nullable|string|max:60',
            'business_address'       => 'nullable|string|max:300',
            'business_type'          => 'nullable|string|max:100',
            'monthly_business_revenue' => 'nullable|numeric|min:0',

            // Next of kin
            'next_of_kin_name'        => 'nullable|string|max:150',
            'next_of_kin_relationship'=> 'nullable|string|max:60',
            'next_of_kin_phone'       => 'nullable|string|max:20',
            'next_of_kin_address'     => 'nullable|string|max:300',

            // Bank
            'bank_name'              => 'nullable|string|max:100',
            'account_number'         => 'nullable|string|max:30',
            'account_name'           => 'nullable|string|max:150',
            'mobile_money_number'    => 'nullable|string|max:20',
            'mobile_money_provider'  => 'nullable|in:mtn,vodafone,airteltigo',

            // Branch
            'branch_id'              => 'required|exists:branches,id',

            // Files
            'photo'                  => 'nullable|file|image|max:2048',
            'documents.*'            => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',

            // Guarantor
            'guarantor_name'         => 'nullable|string|max:150',
            'guarantor_phone'        => 'nullable|string|max:20',
            'guarantor_relationship' => 'nullable|string|max:60',
            'guarantor_address'      => 'nullable|string|max:300',

            // Portal
            'create_portal_account'  => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Borrower must be at least 18 years old.',
            'ghana_card_number.unique' => 'This Ghana Card number is already registered.',
            'branch_id.required' => 'Please select a branch.',
        ];
    }
}

class UpdateBorrowerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $borrowerId = $this->route('borrower')?->id;
        return [
            'first_name'           => 'required|string|max:80',
            'last_name'            => 'required|string|max:80',
            'gender'               => 'required|in:male,female,other',
            'date_of_birth'        => 'required|date',
            'ghana_card_number'    => 'nullable|string|max:50|unique:borrowers,ghana_card_number,' . $borrowerId,
            'primary_phone'        => 'required|string|max:20',
            'secondary_phone'      => 'nullable|string|max:20',
            'email'                => 'nullable|email|max:120',
            'residential_address'  => 'required|string|max:500',
            'employment_status'    => 'required|in:employed,self_employed,business_owner,unemployed,student,retired',
            'monthly_income'       => 'nullable|numeric|min:0',
            'branch_id'            => 'required|exists:branches,id',
            'status'               => 'required|in:active,blacklisted,deceased,inactive',
            'photo'                => 'nullable|file|image|max:2048',
        ];
    }
}
