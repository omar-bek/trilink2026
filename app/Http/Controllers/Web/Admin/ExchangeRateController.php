<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $rates = ExchangeRate::orderByDesc('as_of')->orderBy('from_currency')->paginate(30);

        $currencies = ExchangeRate::select('from_currency')
            ->distinct()
            ->union(ExchangeRate::select('to_currency')->distinct())
            ->pluck('from_currency')
            ->unique()
            ->sort()
            ->values();

        return view('dashboard.admin.exchange-rates.index', compact('rates', 'currencies'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3', 'different:from_currency'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'as_of' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        ExchangeRate::create($data);

        return back()->with('status', __('admin.exchange_rates.created'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        ExchangeRate::where('id', $id)->delete();

        return back()->with('status', __('admin.exchange_rates.deleted'));
    }
}
