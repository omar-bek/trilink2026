<?php

namespace App\Http\Requests\PurchaseRequest;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'category_id'       => ['nullable', 'integer', 'exists:categories,id'],
            'budget'            => ['nullable', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'required_date'     => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_address'  => ['nullable', 'string', 'max:500'],
            'delivery_city'     => ['nullable', 'string', 'max:100'],
            'items'             => ['nullable', 'array'],
            'items.*.name'      => ['required_with:items', 'string', 'max:255'],
            'items.*.qty'       => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit'      => ['nullable', 'string', 'max:30'],
            'items.*.spec'      => ['nullable', 'string', 'max:1000'],
            'items.*.price'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Build the array we hand to PurchaseRequestService::create.
     */
    public function toModelData(int $buyerId, ?int $companyId): array
    {
        $deliveryLocation = array_filter([
            'address' => $this->input('delivery_address'),
            'city'    => $this->input('delivery_city'),
        ]);

        return [
            'title'             => $this->input('title'),
            'description'       => $this->input('description'),
            'category_id'       => $this->input('category_id'),
            'buyer_id'          => $buyerId,
            'company_id'        => $companyId,
            'status'            => \App\Enums\PurchaseRequestStatus::DRAFT,
            'budget'            => $this->input('budget', 0),
            'currency'          => $this->input('currency', 'AED'),
            'required_date'     => $this->input('required_date'),
            'delivery_location' => $deliveryLocation ?: null,
            'items'             => $this->input('items', []),
        ];
    }
}
