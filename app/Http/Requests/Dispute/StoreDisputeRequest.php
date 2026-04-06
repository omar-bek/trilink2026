<?php

namespace App\Http\Requests\Dispute;

use App\Enums\DisputeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'contract_id'        => ['required', 'integer', 'exists:contracts,id'],
            'against_company_id' => ['required', 'integer', 'exists:companies,id'],
            'type'               => ['required', new Enum(DisputeType::class)],
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['required', 'string', 'max:5000'],
        ];
    }
}
