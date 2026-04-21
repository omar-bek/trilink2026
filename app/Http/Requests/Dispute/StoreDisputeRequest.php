<?php

namespace App\Http\Requests\Dispute;

use App\Enums\DisputeRemedy;
use App\Enums\DisputeSeverity;
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
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'against_company_id' => ['required', 'integer', 'exists:companies,id'],
            'type' => ['required', new Enum(DisputeType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            // Structured claim — the amount + remedy aren't required
            // for every dispute (e.g. pure quality complaint without a
            // monetary ask) but if present they must be well-formed.
            'claim_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'claim_currency' => ['nullable', 'string', 'size:3'],
            'requested_remedy' => ['nullable', new Enum(DisputeRemedy::class)],
            'severity' => ['nullable', new Enum(DisputeSeverity::class)],
        ];
    }
}
