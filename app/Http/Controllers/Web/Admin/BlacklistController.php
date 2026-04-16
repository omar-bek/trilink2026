<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlacklistController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $entries = DB::table('blacklisted_companies')
            ->join('companies', 'blacklisted_companies.company_id', '=', 'companies.id')
            ->join('users', 'blacklisted_companies.blacklisted_by', '=', 'users.id')
            ->select(
                'blacklisted_companies.*',
                'companies.name as company_name',
                'users.first_name as admin_first_name',
                'users.last_name as admin_last_name'
            )
            ->orderByDesc('blacklisted_companies.created_at')
            ->paginate(20);

        $companies = Company::where('status', 'active')
            ->whereNotIn('id', DB::table('blacklisted_companies')->where('is_active', true)->pluck('company_id'))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $stats = [
            'active' => DB::table('blacklisted_companies')->where('is_active', true)->count(),
            'total' => DB::table('blacklisted_companies')->count(),
            'expired' => DB::table('blacklisted_companies')->where('is_active', true)->where('expires_at', '<', now())->count(),
        ];

        return view('dashboard.admin.blacklist.index', compact('entries', 'companies', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        DB::table('blacklisted_companies')->insert([
            'company_id' => $data['company_id'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'blacklisted_by' => $request->user()->id,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', __('admin.blacklist.added'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        DB::table('blacklisted_companies')->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return back()->with('status', __('admin.blacklist.removed'));
    }
}
