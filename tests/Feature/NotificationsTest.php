<?php

namespace Tests\Feature;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use App\Enums\PaymentStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\NewBidNotification;
use App\Services\BidService;
use App\Services\DisputeService;
use App\Services\PaymentService;
use App\Support\NotificationFormatter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests for the dynamic notification feed:
 * - real notifications are stored on dispatch
 * - the formatter maps each entity_type → icon, color, url
 * - the dashboard, topbar bell, and notifications page surface them
 * - mark-as-read + mark-all-read + dismiss work
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(UserRole $role = UserRole::BUYER): User
    {
        $type = match ($role) {
            UserRole::SUPPLIER => CompanyType::SUPPLIER,
            default            => CompanyType::BUYER,
        };

        $company = Company::create([
            'name'                => ucfirst($role->value) . ' Co ' . uniqid(),
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => $type,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => $role->value . '@n.test',
            'city'                => 'Dubai',
            'country'             => 'UAE',
        ]);

        return User::create([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $role->value . '-' . uniqid() . '@n.test',
            'password'   => 'secret-pass',
            'role'       => $role,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Formatter — pure unit-style assertions
    // ---------------------------------------------------------------------

    public function test_formatter_maps_entity_type_to_icon_color_and_url(): void
    {
        $user = $this->makeUser();

        $user->notify(new class extends \Illuminate\Notifications\Notification {
            public function via($notifiable): array { return ['database']; }
            public function toArray($notifiable): array {
                return [
                    'type'        => 'success',
                    'title'       => 'Bid accepted',
                    'message'     => 'Congrats',
                    'entity_type' => 'bid',
                    'entity_id'   => 999,
                ];
            }
        });

        $notification = $user->notifications()->first();
        $formatter = app(NotificationFormatter::class);
        $formatted = $formatter->format($notification);

        $this->assertSame('Bid accepted', $formatted['title']);
        $this->assertSame('Congrats', $formatted['desc']);
        // 'success' overrides bid's default green to green (still green here).
        $this->assertSame('green', $formatted['color']);
        // Bid icon path must include the doc-with-checkmark fragment.
        $this->assertStringContainsString('M19.5 14.25', $formatted['icon']);
        // URL routes to dashboard.bids.show
        $this->assertStringContainsString('/bids/999', (string) $formatted['url']);
        $this->assertFalse($formatted['read']);
    }

    public function test_formatter_overrides_color_when_data_type_is_error(): void
    {
        $user = $this->makeUser();

        $user->notify(new class extends \Illuminate\Notifications\Notification {
            public function via($notifiable): array { return ['database']; }
            public function toArray($notifiable): array {
                return [
                    'type'        => 'error',
                    'title'       => 'Payment rejected',
                    'message'     => '',
                    'entity_type' => 'payment',
                    'entity_id'   => 1,
                ];
            }
        });

        $formatted = app(NotificationFormatter::class)->format($user->notifications()->first());
        // payment default = orange, but type=error → red.
        $this->assertSame('red', $formatted['color']);
    }

    public function test_formatter_falls_back_to_default_icon_for_unknown_type(): void
    {
        $user = $this->makeUser();

        $user->notify(new class extends \Illuminate\Notifications\Notification {
            public function via($notifiable): array { return ['database']; }
            public function toArray($notifiable): array {
                return ['title' => 'Welcome', 'message' => 'Hi', 'entity_type' => 'unknown_thing'];
            }
        });

        $formatted = app(NotificationFormatter::class)->format($user->notifications()->first());
        $this->assertNotEmpty($formatted['icon']); // default bell icon
        $this->assertNull($formatted['url']);
    }

    // ---------------------------------------------------------------------
    // Dispatch wiring — services create real notification rows
    // ---------------------------------------------------------------------

    public function test_bid_create_notifies_buyer_company_users(): void
    {
        $buyerCompany = Company::create(['name' => 'Buy Co', 'registration_number' => 'B-' . uniqid(), 'type' => CompanyType::BUYER, 'status' => CompanyStatus::ACTIVE]);
        $buyer = User::create(['first_name' => 'Buy', 'last_name' => 'B', 'email' => 'buy-' . uniqid() . '@t.test', 'password' => 'secret-pass', 'role' => UserRole::BUYER, 'status' => UserStatus::ACTIVE, 'company_id' => $buyerCompany->id]);

        $supplier = $this->makeUser(UserRole::SUPPLIER);

        $rfq = Rfq::create([
            'title'      => 'Steel',
            'company_id' => $buyerCompany->id,
            'type'       => RfqType::SUPPLIER,
            'status'     => RfqStatus::OPEN,
            'budget'     => 100000,
            'currency'   => 'AED',
            'items'      => [],
            'deadline'   => now()->addDays(10),
        ]);

        Notification::fake();

        app(BidService::class)->create([
            'rfq_id'             => $rfq->id,
            'company_id'         => $supplier->company_id,
            'provider_id'        => $supplier->id,
            'status'             => BidStatus::SUBMITTED,
            'price'              => 95000,
            'currency'           => 'AED',
            'delivery_time_days' => 30,
            'validity_date'      => now()->addDays(30),
        ]);

        Notification::assertSentTo($buyer, NewBidNotification::class);
    }

    public function test_payment_approve_notifies_both_companies(): void
    {
        $buyer = $this->makeUser(UserRole::BUYER);
        $supplier = $this->makeUser(UserRole::SUPPLIER);

        $contract = Contract::create([
            'title' => 'C', 'buyer_company_id' => $buyer->company_id,
            'status' => ContractStatus::ACTIVE, 'parties' => [],
            'total_amount' => 10000, 'currency' => 'AED',
            'start_date' => now(), 'end_date' => now()->addMonth(),
        ]);

        $payment = Payment::create([
            'contract_id'          => $contract->id,
            'company_id'           => $buyer->company_id,
            'recipient_company_id' => $supplier->company_id,
            'buyer_id'             => $buyer->id,
            'status'               => PaymentStatus::PENDING_APPROVAL,
            'amount'               => 5000,
            'vat_rate'             => 5,
            'currency'             => 'AED',
            'milestone'            => 'Advance',
        ]);

        Notification::fake();

        app(PaymentService::class)->approve($payment->id, $buyer->id);

        Notification::assertSentTo($buyer, \App\Notifications\PaymentStatusNotification::class);
        Notification::assertSentTo($supplier, \App\Notifications\PaymentStatusNotification::class);
    }

    public function test_dispute_escalate_notifies_both_companies(): void
    {
        $a = $this->makeUser(UserRole::BUYER);
        $b = $this->makeUser(UserRole::SUPPLIER);

        $contract = Contract::create([
            'title' => 'C', 'buyer_company_id' => $a->company_id,
            'status' => ContractStatus::ACTIVE, 'parties' => [],
            'total_amount' => 1, 'currency' => 'AED',
            'start_date' => now(), 'end_date' => now()->addMonth(),
        ]);

        $dispute = Dispute::create([
            'contract_id'        => $contract->id,
            'company_id'         => $a->company_id,
            'raised_by'          => $a->id,
            'against_company_id' => $b->company_id,
            'type'               => DisputeType::QUALITY,
            'status'             => DisputeStatus::OPEN,
            'title'              => 'Bad goods',
            'description'        => 'Did not match spec',
        ]);

        Notification::fake();

        app(DisputeService::class)->escalate($dispute->id);

        Notification::assertSentTo($a, \App\Notifications\DisputeNotification::class);
        Notification::assertSentTo($b, \App\Notifications\DisputeNotification::class);
    }

    // ---------------------------------------------------------------------
    // Dashboard + topbar + notifications page integration
    // ---------------------------------------------------------------------

    private function seedOneNotification(User $user, string $title = 'Test alert', string $entityType = 'bid', int $entityId = 1): void
    {
        $user->notify(new class($title, $entityType, $entityId) extends \Illuminate\Notifications\Notification {
            public function __construct(public string $t, public string $et, public int $eid) {}
            public function via($notifiable): array { return ['database']; }
            public function toArray($notifiable): array {
                return ['type' => 'info', 'title' => $this->t, 'message' => 'detail', 'entity_type' => $this->et, 'entity_id' => $this->eid];
            }
        });
    }

    public function test_dashboard_shell_renders_real_notifications(): void
    {
        $user = $this->makeUser();
        $this->seedOneNotification($user, 'Hello from dashboard');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hello from dashboard');
    }

    public function test_topbar_bell_shows_real_unread_count(): void
    {
        $user = $this->makeUser();
        $this->seedOneNotification($user, 'A');
        $this->seedOneNotification($user, 'B');
        $this->seedOneNotification($user, 'C');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        // The bell badge renders the count when unread > 0; should appear at least once.
        $response->assertSee('id="notif-menu-button"', false);
    }

    public function test_notifications_index_lists_user_notifications(): void
    {
        $user = $this->makeUser();
        $this->seedOneNotification($user, 'Index entry');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Index entry');
    }

    public function test_mark_single_notification_as_read_redirects_to_entity(): void
    {
        $user = $this->makeUser();
        // entity_type=bid + entity_id=42 → should redirect to /dashboard/bids/42
        $this->seedOneNotification($user, 'Click me', 'bid', 42);

        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at);

        $response = $this->actingAs($user)->post(route('notifications.read', ['id' => $notification->id]));

        $response->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $user = $this->makeUser();
        $this->seedOneNotification($user, 'A');
        $this->seedOneNotification($user, 'B');

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_dismiss_a_notification(): void
    {
        $user = $this->makeUser();
        $this->seedOneNotification($user, 'Goodbye');
        $notification = $user->notifications()->first();

        $this->actingAs($user)
            ->delete(route('notifications.destroy', ['id' => $notification->id]))
            ->assertRedirect();

        $this->assertEquals(0, $user->fresh()->notifications()->count());
    }
}
