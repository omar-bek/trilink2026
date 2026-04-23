<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyBrandingController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $this->authorize($request);
        $branding = $company->branding();

        return view('dashboard.settings.branding.edit', compact('branding', 'company'));
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->authorize($request);
        $branding = $company->branding();

        $data = $request->validate([
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'email_from_name' => ['nullable', 'string', 'max:200'],
            'email_from_address' => ['nullable', 'email', 'max:200'],
            'invoice_footer_text' => ['nullable', 'string', 'max:2000'],
            'contract_footer_text' => ['nullable', 'string', 'max:2000'],
            'po_footer_text' => ['nullable', 'string', 'max:2000'],
            'invoice_logo' => ['nullable', 'image', 'max:2048'],
            'email_logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('invoice_logo')) {
            $data['invoice_logo_path'] = $request->file('invoice_logo')
                ->store("branding/{$company->id}", 'public');
        }
        if ($request->hasFile('email_logo')) {
            $data['email_logo_path'] = $request->file('email_logo')
                ->store("branding/{$company->id}", 'public');
        }

        // Change of sender email invalidates the domain-verification
        // flag — the mail service must re-verify before we stamp it on
        // outbound messages. Otherwise tenants could spoof any domain
        // by typing it into the form.
        if (($data['email_from_address'] ?? null) !== $branding->email_from_address) {
            $data['email_sender_verified'] = false;
        }

        unset($data['invoice_logo'], $data['email_logo']);
        $branding->fill($data)->save();

        return redirect()->route('settings.branding.edit')
            ->with('status', __('settings.saved'));
    }

    private function authorize(Request $request): \App\Models\Company
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('manageBranding', $company), 403);

        return $company;
    }
}
