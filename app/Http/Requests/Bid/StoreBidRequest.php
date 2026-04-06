<?php

namespace App\Http\Requests\Bid;

use Illuminate\Foundation\Http\FormRequest;

class StoreBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'price'              => ['required', 'numeric', 'min:0'],
            'currency'           => ['nullable', 'string', 'size:3'],
            'delivery_time_days' => ['required', 'integer', 'min:1'],
            'payment_terms'      => ['nullable', 'string', 'max:255'],
            'validity_date'      => ['required', 'date', 'after:today'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'items'              => ['nullable', 'array'],
            'items.*.name'       => ['required_with:items', 'string', 'max:255'],
            'items.*.qty'        => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
