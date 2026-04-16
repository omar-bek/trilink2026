<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD for the user's saved searches. Phase 1 / task 1.5.
 *
 * Each saved search is private to its owner — no company-level sharing
 * yet. Add/Remove only; editing the filters means deleting and re-saving
 * from the live page.
 */
class SavedSearchController extends Controller
{
    /**
     * Persist the current page's filters as a saved search. Called from
     * a small "Save this search" button injected into the index pages.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'resource_type' => ['required', 'string', 'in:rfqs,suppliers,products'],
            'filters' => ['nullable', 'array'],
        ]);

        SavedSearch::create([
            'user_id' => $user->id,
            'label' => $data['label'],
            'resource_type' => $data['resource_type'],
            'filters' => $data['filters'] ?? [],
            'is_active' => true,
        ]);

        return back()->with('status', __('saved_searches.saved'));
    }

    /**
     * Toggle a saved search's `is_active` flag — controls whether the
     * daily digest will surface it.
     */
    public function toggle(int $id): RedirectResponse
    {
        $user = auth()->user();
        $search = SavedSearch::where('user_id', $user->id)->findOrFail($id);

        $search->update(['is_active' => ! $search->is_active]);

        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        SavedSearch::where('user_id', $user->id)->findOrFail($id)->delete();

        return back()->with('status', __('saved_searches.deleted'));
    }
}
