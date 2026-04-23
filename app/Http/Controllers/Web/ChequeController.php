<?php

namespace App\Http\Controllers\Web;

use App\Enums\ChequeStatus;
use App\Http\Controllers\Controller;
use App\Models\PostdatedCheque;
use App\Services\Payments\ChequeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * UAE post-dated cheque management. Finance team registers a cheque,
 * deposits it on presentation day, and either marks it cleared (settles
 * the linked Payment) or returned (flips the Payment back so they can
 * chase a replacement).
 *
 * Authorisation: only the beneficiary or issuer company's users with
 * permission `payment.view` can see; `payment.approve` is required to
 * act on cheques (deposit / clear / return / stop / replace).
 */
class ChequeController extends Controller
{
    public function __construct(private readonly ChequeService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('payment.view'), 403);
        $companyId = $request->user()->company_id;

        $cheques = PostdatedCheque::query()
            ->where(function ($q) use ($companyId) {
                $q->where('issuer_company_id', $companyId)
                    ->orWhere('beneficiary_company_id', $companyId);
            })
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->with(['issuer', 'beneficiary', 'payment', 'contract'])
            ->latest('presentation_date')
            ->paginate(20);

        return view('dashboard.cheques.index', [
            'cheques' => $cheques,
            'statuses' => array_map(fn ($s) => $s->value, ChequeStatus::cases()),
        ]);
    }

    public function show(int $id, Request $request): View
    {
        $cheque = PostdatedCheque::with(['events.actor', 'payment', 'contract', 'issuer', 'beneficiary'])->findOrFail($id);
        $this->authorizeCheque($cheque, $request->user());

        return view('dashboard.cheques.show', ['cheque' => $cheque]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payment.approve'), 403);

        $data = $request->validate([
            'cheque_number' => ['required', 'string', 'max:32'],
            'beneficiary_company_id' => ['required', 'integer', 'exists:companies,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'drawer_bank_name' => ['required', 'string', 'max:150'],
            'drawer_bank_swift' => ['nullable', 'string', 'max:16'],
            'drawer_account_iban' => ['nullable', 'string', 'max:34'],
            'issue_date' => ['required', 'date'],
            'presentation_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['issuer_company_id'] = $request->user()->company_id;
        $data['currency'] = strtoupper($data['currency'] ?? 'AED');

        $cheque = $this->service->register($data, $request->user());

        return redirect()
            ->route('dashboard.cheques.show', ['id' => $cheque->id])
            ->with('status', __('cheques.registered'));
    }

    public function deposit(int $id, Request $request): RedirectResponse
    {
        return $this->runTransition($id, $request, fn ($cheque, $user) => $this->service->deposit($cheque, $user), 'cheques.deposited');
    }

    public function clear(int $id, Request $request): RedirectResponse
    {
        return $this->runTransition($id, $request, fn ($cheque, $user) => $this->service->clear($cheque, $user), 'cheques.cleared');
    }

    public function returnCheque(int $id, Request $request): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];

        return $this->runTransition($id, $request, fn ($cheque, $user) => $this->service->returnCheque($cheque, $user, $reason), 'cheques.returned');
    }

    public function stop(int $id, Request $request): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];

        return $this->runTransition($id, $request, fn ($cheque, $user) => $this->service->stop($cheque, $user, $reason), 'cheques.stopped');
    }

    private function runTransition(int $id, Request $request, \Closure $op, string $flashKey): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payment.approve'), 403);
        $cheque = PostdatedCheque::findOrFail($id);
        $this->authorizeCheque($cheque, $request->user());

        try {
            $op($cheque, $request->user());
        } catch (Throwable $e) {
            return back()->withErrors(['cheque' => $e->getMessage()]);
        }

        return redirect()
            ->route('dashboard.cheques.show', ['id' => $cheque->id])
            ->with('status', __($flashKey));
    }

    private function authorizeCheque(PostdatedCheque $cheque, $user): void
    {
        $allowed = $user
            && $user->company_id
            && ($user->company_id === $cheque->issuer_company_id
                || $user->company_id === $cheque->beneficiary_company_id);

        abort_unless($allowed, 403);
    }
}
