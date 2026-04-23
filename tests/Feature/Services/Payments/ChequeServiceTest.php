<?php

namespace Tests\Feature\Services\Payments;

use App\Enums\ChequeStatus;
use App\Enums\PaymentStatus;
use App\Models\ChequeEvent;
use App\Models\Payment;
use App\Models\PostdatedCheque;
use App\Models\User;
use App\Services\Payments\ChequeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ChequeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChequeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChequeService();
    }

    public function test_register_issues_a_cheque_and_logs_event(): void
    {
        $actor = User::factory()->create();
        $cheque = $this->service->register([
            'cheque_number' => 'CHQ-001',
            'issuer_company_id' => \App\Models\Company::factory()->create()->id,
            'beneficiary_company_id' => \App\Models\Company::factory()->create()->id,
            'drawer_bank_name' => 'Emirates NBD',
            'issue_date' => now()->toDateString(),
            'presentation_date' => now()->addDays(10)->toDateString(),
            'amount' => 10000,
            'currency' => 'AED',
        ], $actor);

        $this->assertSame(ChequeStatus::ISSUED, $cheque->status);
        $this->assertDatabaseHas('cheque_events', [
            'postdated_cheque_id' => $cheque->id,
            'event' => 'issued',
        ]);
    }

    public function test_clear_settles_the_linked_payment(): void
    {
        $payment = Payment::factory()->approved()->create(['currency' => 'AED']);
        $cheque = PostdatedCheque::factory()->deposited()->create([
            'payment_id' => $payment->id,
        ]);
        $actor = User::factory()->create();

        $this->service->clear($cheque, $actor);

        $this->assertSame(PaymentStatus::COMPLETED->value, $payment->fresh()->status->value);
        $this->assertNotNull($payment->fresh()->settled_at);
    }

    public function test_return_flips_linked_payment_to_failed(): void
    {
        $payment = Payment::factory()->approved()->create();
        $cheque = PostdatedCheque::factory()->deposited()->create([
            'payment_id' => $payment->id,
        ]);
        $actor = User::factory()->create();

        $this->service->returnCheque($cheque, $actor, 'Insufficient funds');

        $this->assertSame(PaymentStatus::FAILED->value, $payment->fresh()->status->value);
        $this->assertSame(ChequeStatus::RETURNED, $cheque->fresh()->status);
        $this->assertStringContainsString('Insufficient funds', (string) $payment->fresh()->rejection_reason);
    }

    public function test_cannot_deposit_before_presentation_date(): void
    {
        $cheque = PostdatedCheque::factory()->issued()->create([
            'presentation_date' => now()->addDays(30)->toDateString(),
        ]);
        $actor = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->service->deposit($cheque, $actor);
    }

    public function test_invalid_transition_throws(): void
    {
        $cheque = PostdatedCheque::factory()->cleared()->create();
        $actor = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->service->returnCheque($cheque, $actor, 'too late');
    }
}
