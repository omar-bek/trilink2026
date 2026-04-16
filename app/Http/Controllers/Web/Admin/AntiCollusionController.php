<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phase 7 (UAE Compliance Roadmap) — admin triage queue for
 * anti-collusion findings. Each row in `collusion_alerts` is one
 * pattern match from AntiCollusionService. The admin can:
 *
 *   - View the evidence (hashed BO ids, shared IPs, timing, etc.)
 *   - Label as: investigating | false_positive | confirmed
 *   - Attach notes explaining the decision (for the audit trail)
 *
 * Federal Decree-Law 36/2023 Article 13 requires the platform to
 * demonstrate it acted on credible indications of collusion.
 * Leaving every alert in "open" for months would be evidence of
 * negligence in a regulatory inquiry.
 */
class AntiCollusionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $query = DB::table('collusion_alerts')
            ->join('rfqs', 'collusion_alerts.rfq_id', '=', 'rfqs.id')
            ->select(
                'collusion_alerts.*',
                'rfqs.rfq_number',
                'rfqs.title as rfq_title'
            )
            ->orderByRaw("FIELD(collusion_alerts.severity, 'critical', 'high', 'medium')")
            ->orderByDesc('collusion_alerts.id');

        if ($status = $request->query('status')) {
            $query->where('collusion_alerts.status', $status);
        }
        if ($severity = $request->query('severity')) {
            $query->where('collusion_alerts.severity', $severity);
        }

        $alerts = $query->paginate(20)->withQueryString();

        // Decode evidence JSON for display
        $alerts->getCollection()->transform(function ($row) {
            $row->evidence = json_decode($row->evidence, true) ?? [];
            return $row;
        });

        $stats = [
            'open'           => DB::table('collusion_alerts')->where('status', 'open')->count(),
            'investigating'  => DB::table('collusion_alerts')->where('status', 'investigating')->count(),
            'false_positive' => DB::table('collusion_alerts')->where('status', 'false_positive')->count(),
            'confirmed'      => DB::table('collusion_alerts')->where('status', 'confirmed')->count(),
        ];

        return view('dashboard.admin.anti-collusion.index', [
            'alerts'  => $alerts,
            'stats'   => $stats,
            'filters' => [
                'status'   => $request->query('status'),
                'severity' => $request->query('severity'),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'status' => ['required', 'in:investigating,false_positive,confirmed'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        DB::table('collusion_alerts')
            ->where('id', $id)
            ->update([
                'status'     => $data['status'],
                'admin_notes'=> $data['notes'] ?? null,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('status', __('anticollusion.status_updated'));
    }
}
