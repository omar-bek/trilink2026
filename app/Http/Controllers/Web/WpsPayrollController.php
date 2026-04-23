<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WpsPayrollBatch;
use App\Services\Payment\WpsSifGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WpsPayrollController extends Controller
{
    public function __construct(private readonly WpsSifGenerator $generator) {}

    public function index(Request $request): View
    {
        $companyId = $this->authorize($request);

        $batches = WpsPayrollBatch::where('company_id', $companyId)
            ->with('submitter')
            ->latest('pay_period_end')->paginate(20);

        return view('dashboard.wps.index', compact('batches'));
    }

    public function show(Request $request, int $id): View
    {
        $companyId = $this->authorize($request);
        $batch = WpsPayrollBatch::where('company_id', $companyId)->with('lines')->findOrFail($id);

        return view('dashboard.wps.show', compact('batch'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->authorize($request);

        $data = $request->validate([
            'employer_eid' => ['required', 'string', 'max:32'],
            'agent_id' => ['required', 'string', 'max:16'],
            'pay_period_start' => ['required', 'date'],
            'pay_period_end' => ['required', 'date', 'after_or_equal:pay_period_start'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.employee_lcpn' => ['required', 'string', 'max:32'],
            'lines.*.employee_name' => ['required', 'string', 'max:200'],
            'lines.*.iban' => ['required', 'string', 'max:50'],
            'lines.*.bank_code' => ['nullable', 'string', 'max:16'],
            'lines.*.basic_salary' => ['required', 'numeric', 'min:0'],
            'lines.*.housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'lines.*.other_allowances' => ['nullable', 'numeric', 'min:0'],
            'lines.*.deductions' => ['nullable', 'numeric', 'min:0'],
            'lines.*.leave_days' => ['nullable', 'integer', 'between:0,31'],
            'lines.*.working_days' => ['nullable', 'integer', 'between:0,31'],
        ]);

        // Compute gross / net on the server so a caller can't smuggle
        // inconsistent totals through the form — derived fields always
        // come from basic + allowances − deductions.
        $totalGross = 0;
        $totalNet = 0;

        $batch = WpsPayrollBatch::create([
            'company_id' => $companyId,
            'employer_eid' => $data['employer_eid'],
            'agent_id' => $data['agent_id'],
            'pay_period_start' => $data['pay_period_start'],
            'pay_period_end' => $data['pay_period_end'],
            'employee_count' => count($data['lines']),
            'total_gross_aed' => 0,
            'total_net_aed' => 0,
            'status' => 'draft',
        ]);

        foreach ($data['lines'] as $row) {
            $gross = (float) $row['basic_salary']
                + (float) ($row['housing_allowance'] ?? 0)
                + (float) ($row['other_allowances'] ?? 0);
            $net = $gross - (float) ($row['deductions'] ?? 0);

            $totalGross += $gross;
            $totalNet += $net;

            $batch->lines()->create([
                'employee_lcpn' => $row['employee_lcpn'],
                'employee_name' => $row['employee_name'],
                'iban' => $row['iban'],
                'bank_code' => $row['bank_code'] ?? null,
                'basic_salary' => $row['basic_salary'],
                'housing_allowance' => $row['housing_allowance'] ?? 0,
                'other_allowances' => $row['other_allowances'] ?? 0,
                'deductions' => $row['deductions'] ?? 0,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'leave_days' => $row['leave_days'] ?? 0,
                'working_days' => $row['working_days'] ?? 30,
            ]);
        }

        $batch->update(['total_gross_aed' => $totalGross, 'total_net_aed' => $totalNet]);

        return redirect()->route('dashboard.wps.show', $batch->id)
            ->with('status', __('wps.batch_created'));
    }

    public function generate(Request $request, int $id): RedirectResponse
    {
        $companyId = $this->authorize($request);
        $batch = WpsPayrollBatch::where('company_id', $companyId)->findOrFail($id);

        $this->generator->generate($batch);

        return back()->with('status', __('wps.sif_generated'));
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $companyId = $this->authorize($request);
        $batch = WpsPayrollBatch::where('company_id', $companyId)->findOrFail($id);
        abort_unless($batch->sif_file_path && Storage::disk('local')->exists($batch->sif_file_path), 404);

        return Storage::disk('local')->download(
            $batch->sif_file_path,
            "WPS-{$batch->pay_period_end->format('Y-m')}.sif"
        );
    }

    public function markSubmitted(Request $request, int $id): RedirectResponse
    {
        $companyId = $this->authorize($request);
        $batch = WpsPayrollBatch::where('company_id', $companyId)->findOrFail($id);

        abort_unless($batch->status === 'generated', 422);
        $batch->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $request->user()->id,
        ]);

        return back()->with('status', __('wps.submitted'));
    }

    private function authorize(Request $request): int
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('manageBilling', $company), 403);

        return (int) $company->id;
    }
}
