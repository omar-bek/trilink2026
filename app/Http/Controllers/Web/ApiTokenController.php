<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Self-service API token management for company managers. Tokens are used
 * to call the public REST API documented at /api/docs and live as long as
 * the manager doesn't revoke them.
 *
 * Each token has a label (so the manager can revoke "Zapier integration"
 * separately from "Internal ETL") and an abilities list (read-only by
 * default — write tokens require explicit opt-in).
 */
class ApiTokenController extends Controller
{
    private const ALLOWED_ABILITIES = [
        'read:companies',
        'read:rfqs',
        'read:bids',
        'read:contracts',
        'read:payments',
        'read:products',
        'read:catalog',
        'write:rfqs',
        'write:bids',
        'write:products',
    ];

    public function index(Request $request): View
    {
        $tokens = $request->user()->tokens()->latest()->get();
        $abilities = self::ALLOWED_ABILITIES;

        return view('dashboard.api-tokens.index', compact('tokens', 'abilities'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:64'],
            'abilities'   => ['nullable', 'array'],
            'abilities.*' => ['string', 'in:' . implode(',', self::ALLOWED_ABILITIES)],
        ]);

        $abilities = !empty($data['abilities']) ? $data['abilities'] : ['read:rfqs', 'read:bids', 'read:contracts'];

        $token = $request->user()->createToken($data['name'], $abilities);

        // The plain text only exists once — flash it so the user can copy
        // it. After this redirect we only have the hashed token in the DB.
        return redirect()
            ->route('dashboard.api-tokens.index')
            ->with('plain_token', $token->plainTextToken)
            ->with('status', __('api.token_created'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $token = $request->user()->tokens()->findOrFail($id);
        $token->delete();

        return redirect()
            ->route('dashboard.api-tokens.index')
            ->with('status', __('api.token_revoked'));
    }
}
