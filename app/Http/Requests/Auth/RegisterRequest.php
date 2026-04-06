<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'role' => ['nullable', new Enum(UserRole::class)],
            'company_id' => ['nullable', 'exists:companies,id'],
        ];
    }
}
