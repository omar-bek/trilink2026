<?php

namespace App\Support;

/**
 * Catalog of fields a platform admin can request from a pending company
 * during the approval review. Each entry maps to either:
 *   - a column on the `companies` table  (kind = 'field')
 *   - a key inside the `documents` JSON  (kind = 'document')
 *
 * The same catalog drives:
 *   - the admin "request more info" checkbox list
 *   - the validation rules on the user-side completion form
 *   - the list of inputs the user-side form actually renders
 *
 * Add a new key here and it instantly appears in both UIs.
 */
class CompanyInfoFields
{
    /**
     * @return array<string, array{label_key: string, kind: string, column?: string, doc_key?: string, rules: array<int, string>, input_type?: string}>
     */
    public static function catalog(): array
    {
        return [
            // ── Identity ────────────────────────────────────────────────
            'name' => [
                'label_key' => 'admin.companies.name',
                'kind'      => 'field',
                'column'    => 'name',
                'rules'     => ['required', 'string', 'max:255'],
                'input_type'=> 'text',
            ],
            'name_ar' => [
                'label_key' => 'admin.companies.name_ar',
                'kind'      => 'field',
                'column'    => 'name_ar',
                'rules'     => ['required', 'string', 'max:255'],
                'input_type'=> 'text',
            ],
            'registration_number' => [
                'label_key' => 'admin.companies.registration_number',
                'kind'      => 'field',
                'column'    => 'registration_number',
                'rules'     => ['required', 'string', 'max:100'],
                'input_type'=> 'text',
            ],
            'tax_number' => [
                'label_key' => 'admin.companies.tax_number',
                'kind'      => 'field',
                'column'    => 'tax_number',
                'rules'     => ['required', 'string', 'max:100'],
                'input_type'=> 'text',
            ],

            // ── Contact ─────────────────────────────────────────────────
            'email' => [
                'label_key' => 'admin.users.email',
                'kind'      => 'field',
                'column'    => 'email',
                'rules'     => ['required', 'email', 'max:255'],
                'input_type'=> 'email',
            ],
            'phone' => [
                'label_key' => 'admin.users.phone',
                'kind'      => 'field',
                'column'    => 'phone',
                'rules'     => ['required', 'string', 'max:30'],
                'input_type'=> 'tel',
            ],
            'website' => [
                'label_key' => 'admin.companies.website',
                'kind'      => 'field',
                'column'    => 'website',
                'rules'     => ['required', 'url', 'max:255'],
                'input_type'=> 'url',
            ],

            // ── Location ────────────────────────────────────────────────
            'address' => [
                'label_key' => 'admin.companies.address',
                'kind'      => 'field',
                'column'    => 'address',
                'rules'     => ['required', 'string', 'max:1000'],
                'input_type'=> 'textarea',
            ],
            'city' => [
                'label_key' => 'admin.companies.city',
                'kind'      => 'field',
                'column'    => 'city',
                'rules'     => ['required', 'string', 'max:100'],
                'input_type'=> 'text',
            ],
            'country' => [
                'label_key' => 'admin.companies.country',
                'kind'      => 'field',
                'column'    => 'country',
                'rules'     => ['required', 'string', 'max:100'],
                'input_type'=> 'text',
            ],

            // ── Profile ─────────────────────────────────────────────────
            'description' => [
                'label_key' => 'admin.companies.description',
                'kind'      => 'field',
                'column'    => 'description',
                'rules'     => ['required', 'string', 'max:5000'],
                'input_type'=> 'textarea',
            ],

            // ── Documents ───────────────────────────────────────────────
            'trade_license_file' => [
                'label_key' => 'admin.companies.doc_trade_license',
                'kind'      => 'document',
                'doc_key'   => 'trade_license_file',
                'rules'     => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ],
            'tax_certificate_file' => [
                'label_key' => 'admin.companies.doc_tax_certificate',
                'kind'      => 'document',
                'doc_key'   => 'tax_certificate_file',
                'rules'     => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ],
            'company_profile_file' => [
                'label_key' => 'admin.companies.doc_company_profile',
                'kind'      => 'document',
                'doc_key'   => 'company_profile_file',
                'rules'     => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allKeys(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * Build the validation rules array for a subset of catalog keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array<int, string>>
     */
    public static function rulesFor(array $keys): array
    {
        $rules = [];
        foreach ($keys as $key) {
            $entry = self::catalog()[$key] ?? null;
            if (!$entry) {
                continue;
            }
            $rules[$key] = $entry['rules'];
        }
        return $rules;
    }
}
