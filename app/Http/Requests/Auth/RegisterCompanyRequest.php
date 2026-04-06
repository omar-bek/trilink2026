<?php

namespace App\Http\Requests\Auth;

use App\Enums\CompanyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_name_ar' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'unique:companies,registration_number'],
            'company_type' => ['required', new Enum(CompanyType::class)],
            'company_email' => ['nullable', 'email'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }
}
