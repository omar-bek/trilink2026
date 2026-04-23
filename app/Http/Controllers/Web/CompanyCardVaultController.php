<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanyCardVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Thin wrapper around the card vault. PCI-DSS scope is reduced because
 * we never see the PAN — the browser hands the card to the gateway via
 * an iframe-hosted Elements/Frames widget, then posts the resulting
 * opaque token back here. Only metadata (brand/last4/expiry) ever
 * touches our DB.
 */
class CompanyCardVaultController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->authorize($request);

        $cards = $company->cardVault()->orderByDesc('is_default')->latest()->get();

        return view('dashboard.settings.cards.index', compact('cards', 'company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->authorize($request);

        $data = $request->validate([
            'gateway' => ['required', 'in:stripe,checkout,network,telr,magnati'],
            'token' => ['required', 'string', 'max:255'],
            'fingerprint' => ['nullable', 'string', 'max:128'],
            'brand' => ['nullable', 'in:visa,mastercard,amex,unionpay,discover,diners,jcb'],
            'last4' => ['required', 'digits:4'],
            'exp_month' => ['required', 'integer', 'between:1,12'],
            'exp_year' => ['required', 'integer', 'between:2025,2100'],
            'cardholder_name' => ['nullable', 'string', 'max:200'],
            'issuing_country' => ['nullable', 'string', 'size:2'],
            'label' => ['nullable', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($company, $request, $data) {
            $card = $company->cardVault()->create([
                ...$data,
                'saved_by_user_id' => $request->user()->id,
                'is_default' => $request->boolean('is_default'),
                'is_company_card' => true,
            ]);

            if ($card->is_default) {
                $company->cardVault()
                    ->where('id', '!=', $card->id)
                    ->update(['is_default' => false]);
            }
        });

        return redirect()->route('settings.cards.index')
            ->with('status', __('settings.saved'));
    }

    public function setDefault(Request $request, int $id): RedirectResponse
    {
        $company = $this->authorize($request);

        DB::transaction(function () use ($company, $id) {
            $company->cardVault()->where('id', '!=', $id)->update(['is_default' => false]);
            $company->cardVault()->where('id', $id)->update(['is_default' => true]);
        });

        return back()->with('status', __('settings.saved'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $company = $this->authorize($request);
        $card = $company->cardVault()->findOrFail($id);

        $card->update([
            'revoked_at' => now(),
            'revoked_by_user_id' => $request->user()->id,
            'is_default' => false,
        ]);
        $card->delete();

        return back()->with('status', __('settings.deleted'));
    }

    private function authorize(Request $request): \App\Models\Company
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('manageBilling', $company), 403);

        return $company;
    }
}
