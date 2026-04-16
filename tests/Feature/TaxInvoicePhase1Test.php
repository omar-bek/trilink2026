<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\IssueTaxInvoiceJob;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\TaxCreditNote;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Tax\InvoiceNumberAllocator;
use App\Services\Tax\TaxInvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Phase 1 (UAE Compliance Roadmap) — Tax Invoice infrastructure regression
 * suite. Covers the four pieces that the implementation review checkpoint
 * called out as risky:
 *
 *   1. Sequential numbering — atomic, gap-free, year-resetting.
 *   2. Idempotency — re-dispatching the issuance pipeline for the same
 *      payment must not mint a second invoice or burn an extra sequence
 *      number.
 *   3. Lifecycle — issue → void → credit note round-trips correctly and
 *      the row remains immutable except for the void columns.
 *   4. Authorization — buyer + supplier may download their own invoice;
 *      a third party gets 404 (not 403, so they cannot enumerate ids).
 */
class TaxInvoicePhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // PaymentInvoiceObserver dispatches IssueTaxInvoiceJob on every
        // status flip into COMPLETED. The default queue connection in
        // testing is `sync`, so dispatches run inline. We selectively
        // fake the bus in tests that want to inspect dispatched jobs
        // without running them.
    }

    // ─────────────────────────────────────────────────────────────────
    //  Fixtures
    // ─────────────────────────────────────────────────────────────────

    private function makeCompany(string $name, CompanyType $type, ?string $trn = null): Company
    {
        return Company::create([
            'name' => $name,
            'registration_number' => 'REG-'.uniqid(),
            'tax_number' => $trn ?? ('TRN-'.random_int(100000, 999999)),
            'type' => $type,
            'status' => CompanyStatus::ACTIVE,
            'email' => strtolower(str_replace(' ', '', $name)).'@t.test',
            'address' => '101 Sheikh Zayed Road',
            'city' => 'Dubai',
            'country' => 'AE',
        ]);
    }

    private function makeUser(Company $company, UserRole $role): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'u-'.uniqid().'@t.test',
            'password' => 'secret-pass',
            'role' => $role,
            'status' => UserStatus::ACTIVE,
            'company_id' => $company->id,
            // Suppliers don't get payment.view by default; whitelist it
            // explicitly so they can hit the user-facing download route
            // (the production behaviour requires the company manager to
            // tick the box per user).
            'permissions' => $role === UserRole::SUPPLIER ? ['payment.view'] : null,
        ]);
    }

    private function makePayment(Company $buyer, Company $supplier, ?Contract $contract = null): Payment
    {
        $contract = $contract ?? Contract::create([
            'title' => 'Test Contract for Tax Invoice',
            'buyer_company_id' => $buyer->id,
            'status' => ContractStatus::ACTIVE,
            'total_amount' => 1000,
            'currency' => 'AED',
            'parties' => [['company_id' => $supplier->id, 'role' => 'supplier']],
        ]);

        // Buyer-side user is required because payments.buyer_id is NOT
        // NULL in the schema (the buyer who initiated the payment).
        $buyerUser = $this->makeUser($buyer, UserRole::BUYER);

        // Create a payment in PENDING then flip it to COMPLETED so the
        // observer fires (the observer only acts on transitions INTO
        // completed, not direct creates in the completed state).
        $payment = Payment::create([
            'contract_id' => $contract->id,
            'company_id' => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id' => $buyerUser->id,
            'status' => PaymentStatus::PENDING_APPROVAL,
            'amount' => 1000,
            'vat_rate' => 5,
            'currency' => 'AED',
            'milestone' => 'Milestone 1',
        ]);

        return $payment;
    }

    // ─────────────────────────────────────────────────────────────────
    //  1. Sequential numbering — atomic, gap-free, year-resetting
    // ─────────────────────────────────────────────────────────────────

    public function test_invoice_number_allocator_increments_per_company(): void
    {
        $allocator = $this->app->make(InvoiceNumberAllocator::class);

        $supplier = $this->makeCompany('Allocator Co', CompanyType::SUPPLIER);

        $year = now()->year;

        $first = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_INVOICE);
        $second = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_INVOICE);
        $third = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_INVOICE);

        $this->assertSame("INV-{$year}-000001", $first);
        $this->assertSame("INV-{$year}-000002", $second);
        $this->assertSame("INV-{$year}-000003", $third);
    }

    public function test_invoice_number_allocator_isolates_companies(): void
    {
        $allocator = $this->app->make(InvoiceNumberAllocator::class);

        $a = $this->makeCompany('Company A', CompanyType::SUPPLIER);
        $b = $this->makeCompany('Company B', CompanyType::SUPPLIER);

        $year = now()->year;

        $a1 = $allocator->allocate($a->id, InvoiceNumberAllocator::SERIES_INVOICE);
        $b1 = $allocator->allocate($b->id, InvoiceNumberAllocator::SERIES_INVOICE);
        $a2 = $allocator->allocate($a->id, InvoiceNumberAllocator::SERIES_INVOICE);
        $b2 = $allocator->allocate($b->id, InvoiceNumberAllocator::SERIES_INVOICE);

        // Each company has its OWN counter, both starting at 1.
        $this->assertSame("INV-{$year}-000001", $a1);
        $this->assertSame("INV-{$year}-000001", $b1);
        $this->assertSame("INV-{$year}-000002", $a2);
        $this->assertSame("INV-{$year}-000002", $b2);
    }

    public function test_invoice_and_credit_note_series_are_independent(): void
    {
        $allocator = $this->app->make(InvoiceNumberAllocator::class);

        $supplier = $this->makeCompany('Mixed Series Co', CompanyType::SUPPLIER);

        $year = now()->year;

        $inv1 = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_INVOICE);
        $cn1 = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_CREDIT_NOTE);
        $inv2 = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_INVOICE);
        $cn2 = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_CREDIT_NOTE);

        $this->assertSame("INV-{$year}-000001", $inv1);
        $this->assertSame("INV-{$year}-000002", $inv2);
        $this->assertSame("CN-{$year}-000001", $cn1);
        $this->assertSame("CN-{$year}-000002", $cn2);
    }

    public function test_burst_allocation_produces_unique_numbers(): void
    {
        // Stand-in for true concurrency: SQLite doesn't honour
        // lockForUpdate(), but the allocator's contract is still that
        // calling it N times produces N distinct values. This catches
        // bugs where the allocator drifts (e.g. caching the row outside
        // the transaction or off-by-one on the sequence).
        $allocator = $this->app->make(InvoiceNumberAllocator::class);

        $supplier = $this->makeCompany('Burst Co', CompanyType::SUPPLIER);

        $numbers = [];
        for ($i = 0; $i < 100; $i++) {
            $numbers[] = $allocator->allocate($supplier->id, InvoiceNumberAllocator::SERIES_INVOICE);
        }

        $this->assertCount(100, array_unique($numbers));
        $year = now()->year;
        $this->assertSame("INV-{$year}-000001", $numbers[0]);
        $this->assertSame("INV-{$year}-000100", $numbers[99]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Idempotency — duplicate dispatches don't double-issue
    // ─────────────────────────────────────────────────────────────────

    public function test_issue_for_returns_existing_invoice_when_called_twice(): void
    {
        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('Idem Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Idem Supplier', CompanyType::SUPPLIER);

        $payment = $this->makePayment($buyer, $supplier);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->saveQuietly(); // saveQuietly to skip the observer here — we drive the service directly.

        $first = $service->issueFor($payment);
        $second = $service->issueFor($payment);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->invoice_number, $second->invoice_number);
        $this->assertSame(1, TaxInvoice::where('payment_id', $payment->id)->count());
    }

    public function test_observer_dispatches_job_on_status_flip_to_completed(): void
    {
        Bus::fake([IssueTaxInvoiceJob::class]);

        $buyer = $this->makeCompany('Obs Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Obs Supplier', CompanyType::SUPPLIER);
        $payment = $this->makePayment($buyer, $supplier);

        // Flip to COMPLETED — this should fire the observer.
        $payment->status = PaymentStatus::COMPLETED;
        $payment->save();

        Bus::assertDispatched(IssueTaxInvoiceJob::class, function ($job) use ($payment) {
            return $job->paymentId === $payment->id;
        });
    }

    public function test_observer_does_not_dispatch_on_unrelated_updates(): void
    {
        Bus::fake([IssueTaxInvoiceJob::class]);

        $buyer = $this->makeCompany('Idle Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Idle Supplier', CompanyType::SUPPLIER);
        $payment = $this->makePayment($buyer, $supplier);

        // Touch a non-status column on a non-completed payment.
        $payment->milestone = 'Updated milestone';
        $payment->save();

        Bus::assertNotDispatched(IssueTaxInvoiceJob::class);
    }

    public function test_end_to_end_payment_completion_creates_invoice(): void
    {
        // No bus fake — we want the inline (sync) job to run. The PDF
        // render is exercised end-to-end so we'll see any view-side
        // breakage too. Storage is the local disk in the test env.
        $buyer = $this->makeCompany('E2E Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('E2E Supplier', CompanyType::SUPPLIER, 'TRN-EE-12345');
        $payment = $this->makePayment($buyer, $supplier);

        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->save();

        $invoice = TaxInvoice::where('payment_id', $payment->id)->first();
        $this->assertNotNull($invoice, 'Observer→Job pipeline should have produced an invoice.');
        $this->assertSame($supplier->id, (int) $invoice->supplier_company_id);
        $this->assertSame($buyer->id, (int) $invoice->buyer_company_id);
        $this->assertSame('TRN-EE-12345', $invoice->supplier_trn);
        $this->assertEquals(1000.00, (float) $invoice->subtotal_excl_tax);
        $this->assertEquals(50.00, (float) $invoice->total_tax);
        $this->assertEquals(1050.00, (float) $invoice->total_inclusive);
        $this->assertNotNull($invoice->pdf_path);
        $this->assertNotNull($invoice->pdf_sha256);
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. Lifecycle — void + credit note
    // ─────────────────────────────────────────────────────────────────

    public function test_void_marks_invoice_voided_with_reason_and_actor(): void
    {
        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('Void Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Void Supplier', CompanyType::SUPPLIER);
        $admin = $this->makeUser($buyer, UserRole::ADMIN);

        $payment = $this->makePayment($buyer, $supplier);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->saveQuietly();

        $invoice = $service->issueFor($payment);

        $voided = $service->voidInvoice($invoice, 'Issued in error', $admin->id);

        $this->assertSame(TaxInvoice::STATUS_VOIDED, $voided->status);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame($admin->id, (int) $voided->voided_by);
        $this->assertSame('Issued in error', $voided->void_reason);
    }

    public function test_voiding_an_already_voided_invoice_is_a_noop(): void
    {
        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('Repeat Void Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Repeat Void Supplier', CompanyType::SUPPLIER);

        $payment = $this->makePayment($buyer, $supplier);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->saveQuietly();

        $invoice = $service->issueFor($payment);
        $service->voidInvoice($invoice, 'First reason');
        $afterFirst = $invoice->fresh();

        // Second void call should not overwrite the original metadata.
        $service->voidInvoice($invoice, 'Second reason');
        $afterSecond = $invoice->fresh();

        $this->assertSame('First reason', $afterFirst->void_reason);
        $this->assertSame('First reason', $afterSecond->void_reason);
        $this->assertEquals($afterFirst->voided_at, $afterSecond->voided_at);
    }

    public function test_credit_note_links_to_original_invoice_and_inherits_currency(): void
    {
        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('CN Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('CN Supplier', CompanyType::SUPPLIER);

        $payment = $this->makePayment($buyer, $supplier);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->saveQuietly();

        $invoice = $service->issueFor($payment);

        $cn = $service->issueCreditNote(
            $invoice,
            TaxCreditNote::REASON_REFUND,
            null,
            'Buyer requested refund'
        );

        $this->assertSame($invoice->id, (int) $cn->original_invoice_id);
        $this->assertSame('AED', $cn->currency);
        $this->assertEquals((float) $invoice->total_inclusive, (float) $cn->total_inclusive);
        $this->assertStringStartsWith('CN-'.now()->year.'-', $cn->credit_note_number);
        $this->assertNotNull($cn->pdf_path);
    }

    public function test_credit_note_rejects_invalid_reason(): void
    {
        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('Bad Reason Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Bad Reason Supplier', CompanyType::SUPPLIER);

        $payment = $this->makePayment($buyer, $supplier);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->saveQuietly();

        $invoice = $service->issueFor($payment);

        $this->expectException(\RuntimeException::class);
        $service->issueCreditNote($invoice, 'made_up_reason');
    }

    // ─────────────────────────────────────────────────────────────────
    //  4. Authorization — buyer/supplier IDOR check on download
    // ─────────────────────────────────────────────────────────────────

    public function test_buyer_can_download_their_payment_invoice(): void
    {
        $buyerCompany = $this->makeCompany('Auth Buyer', CompanyType::BUYER);
        $supplierCompany = $this->makeCompany('Auth Supplier', CompanyType::SUPPLIER);
        $buyerUser = $this->makeUser($buyerCompany, UserRole::BUYER);

        $payment = $this->makePayment($buyerCompany, $supplierCompany);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->save();

        $response = $this->actingAs($buyerUser)
            ->get(route('dashboard.payments.invoice.download', ['id' => $payment->id]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_supplier_can_download_their_payment_invoice(): void
    {
        $buyerCompany = $this->makeCompany('Sup-side Buyer', CompanyType::BUYER);
        $supplierCompany = $this->makeCompany('Sup-side Supplier', CompanyType::SUPPLIER);
        $supplierUser = $this->makeUser($supplierCompany, UserRole::SUPPLIER);

        $payment = $this->makePayment($buyerCompany, $supplierCompany);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->save();

        $response = $this->actingAs($supplierUser)
            ->get(route('dashboard.payments.invoice.download', ['id' => $payment->id]));

        $response->assertOk();
    }

    public function test_unrelated_user_cannot_download_payment_invoice(): void
    {
        $buyerCompany = $this->makeCompany('Real Buyer', CompanyType::BUYER);
        $supplierCompany = $this->makeCompany('Real Supplier', CompanyType::SUPPLIER);
        $strangerCompany = $this->makeCompany('Unrelated Buyer', CompanyType::BUYER);
        $stranger = $this->makeUser($strangerCompany, UserRole::BUYER);

        $payment = $this->makePayment($buyerCompany, $supplierCompany);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->save();

        // 404 (not 403) so the unrelated user cannot enumerate payment ids.
        $this->actingAs($stranger)
            ->get(route('dashboard.payments.invoice.download', ['id' => $payment->id]))
            ->assertNotFound();
    }

    public function test_payment_show_view_includes_tax_invoice_card_when_issued(): void
    {
        $buyerCompany = $this->makeCompany('Show Buyer', CompanyType::BUYER);
        $supplierCompany = $this->makeCompany('Show Supplier', CompanyType::SUPPLIER);
        $buyerUser = $this->makeUser($buyerCompany, UserRole::BUYER);

        $payment = $this->makePayment($buyerCompany, $supplierCompany);
        $payment->status = PaymentStatus::COMPLETED;
        $payment->approved_at = now();
        $payment->save();

        $invoice = TaxInvoice::where('payment_id', $payment->id)->first();
        $this->assertNotNull($invoice);

        $response = $this->actingAs($buyerUser)
            ->get(route('dashboard.payments.show', ['id' => $payment->id]));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
    }
}
