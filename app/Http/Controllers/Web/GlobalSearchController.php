<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SearchHistory;
use App\Services\GlobalSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Federated search results page (Phase 1 / task 1.12).
 *
 * Lives at /dashboard/search and is reachable from the topbar's search
 * input. The actual fan-out lives in App\Services\GlobalSearchService;
 * this controller's job is to call it, persist the search to the user's
 * history (task 1.13), and render the page.
 */
class GlobalSearchController extends Controller
{
    public function __construct(private readonly GlobalSearchService $search) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $user = $request->user();

        $results = $this->search->search($term, $user?->company_id);

        // Phase 1 / task 1.13 — write to the user's recent search history.
        // Skips empty searches and dedupes against the user's most-recent
        // entry so the same query isn't logged on every page refresh.
        if ($term !== '' && $user) {
            SearchHistory::record($user->id, $term, $results['total']);
        }

        $recentSearches = $user
            ? SearchHistory::recentFor($user->id, 10)
            : collect();

        return view('dashboard.search.index', [
            'q' => $term,
            'results' => $results,
            'recent_searches' => $recentSearches,
        ]);
    }
}
