<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanyDocumentNumbering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyDocumentNumberingController extends Controller
{
    private const TYPES = ['invoice', 'credit_note', 'purchase_order', 'rfq', 'contract', 'quote', 'receipt'];

    public function edit(Request $request): View
    {
        $company = $this->authorize($request);

        $existing = $company->documentNumberings()->get()->keyBy('document_type');

        return view('dashboard.settings.numbering.edit', [
            'types' => self::TYPES,
            'existing' => $existing,
            'company' => $company,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->authorize($request);

        $rows = $request->input('series', []);
        foreach (self::TYPES as $type) {
            $row = $rows[$type] ?? [];
            if (empty($row['prefix'])) {
                continue;
            }

            CompanyDocumentNumbering::updateOrCreate(
                ['company_id' => $company->id, 'document_type' => $type],
                [
                    'prefix' => substr((string) $row['prefix'], 0, 32),
                    'format_template' => substr((string) ($row['format_template'] ?? '{PREFIX}-{YEAR}-{SEQ:6}'), 0, 64),
                    // The manager can seed a starting sequence (useful
                    // when migrating from another system mid-year) but
                    // only when the series is being created — once it
                    // has issued numbers, rewinding is forbidden.
                    'current_sequence' => isset($row['current_sequence']) ? (int) $row['current_sequence'] : 0,
                ]
            );
        }

        return redirect()->route('settings.numbering.edit')
            ->with('status', __('settings.saved'));
    }

    private function authorize(Request $request): \App\Models\Company
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('manageDefaults', $company), 403);

        return $company;
    }
}
