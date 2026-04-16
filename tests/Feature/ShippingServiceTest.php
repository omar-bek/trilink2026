<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Services\Shipping\CarrierFactory;
use App\Services\Shipping\CarrierInterface;
use App\Services\ShippingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for ShippingService — focused on quoteAll() leaderboard
 * merging + syncTracking() idempotency. Uses an in-memory carrier
 * factory stub instead of hitting real adapters so the tests don't
 * depend on HTTP credentials or fixtures.
 */
class ShippingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Build a ShippingService whose factory exposes exactly the
     * carriers we hand it. Lets each test inject success / failure
     * combinations without monkey-patching globals.
     *
     * @param  array<int, CarrierInterface>  $carriers
     */
    private function service(array $carriers): ShippingService
    {
        $factory = new class($carriers) extends CarrierFactory
        {
            /** @var array<int, CarrierInterface> */
            private array $stub;

            public function __construct(array $stub)
            {
                $this->stub = $stub;
            }

            public function all(): array
            {
                return $this->stub;
            }

            public function make(string $code): CarrierInterface
            {
                foreach ($this->stub as $c) {
                    if ($c->code() === $code) {
                        return $c;
                    }
                }
                throw new \InvalidArgumentException("Unknown carrier: {$code}");
            }
        };

        return new ShippingService($factory);
    }

    /**
     * Build a minimal Shipment row with real Contract + Company FK
     * targets so the foreign-key constraints don't reject the insert
     * on SQLite. Reused by every syncTracking / bookShipment test.
     */
    private function makeShipment(?string $trackingNumber = null): Shipment
    {
        $buyer = Company::create([
            'name' => 'Buyer '.uniqid(),
            'registration_number' => 'TRN-'.uniqid(),
            'type' => CompanyType::BUYER,
            'status' => CompanyStatus::ACTIVE,
            'email' => uniqid().'@s.test',
            'city' => 'Dubai',
            'country' => 'UAE',
        ]);

        $contract = Contract::create([
            'title' => 'C-'.uniqid(),
            'buyer_company_id' => $buyer->id,
            'status' => ContractStatus::ACTIVE,
            'parties' => [
                ['company_id' => $buyer->id, 'role' => 'buyer'],
            ],
            'total_amount' => 1000,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        return Shipment::create([
            'contract_id' => $contract->id,
            'company_id' => $buyer->id,
            'origin' => ['city' => 'Dubai'],
            'destination' => ['city' => 'Riyadh'],
            'tracking_number' => $trackingNumber,
        ]);
    }

    /**
     * Inline carrier stub. Each instance returns whatever its
     * constructor was handed — happy path, failure, empty list, etc.
     */
    private function carrier(string $code, string $name, array $quoteResponse, array $trackResponse = ['success' => false]): CarrierInterface
    {
        return new class($code, $name, $quoteResponse, $trackResponse) implements CarrierInterface
        {
            public function __construct(
                private string $code,
                private string $name,
                private array $quoteResponse,
                private array $trackResponse,
            ) {}

            public function code(): string
            {
                return $this->code;
            }

            public function name(): string
            {
                return $this->name;
            }

            public function quote(array $request): array
            {
                return $this->quoteResponse;
            }

            public function createShipment(array $request): array
            {
                return ['success' => true, 'tracking_number' => 'T-'.$this->code];
            }

            public function track(string $trackingNumber): array
            {
                return $this->trackResponse;
            }
        };
    }

    // ─────────────────────────────────────────────────────────────────
    //  quoteAll
    // ─────────────────────────────────────────────────────────────────

    public function test_quote_all_merges_rates_from_every_carrier_and_sorts_ascending(): void
    {
        $service = $this->service([
            $this->carrier('a', 'A Express', [
                'success' => true,
                'rates' => [
                    ['service' => 'Standard', 'price' => 250.0, 'currency' => 'AED', 'transit_days' => 5],
                    ['service' => 'Express',  'price' => 480.0, 'currency' => 'AED', 'transit_days' => 2],
                ],
            ]),
            $this->carrier('b', 'B Logistics', [
                'success' => true,
                'rates' => [
                    ['service' => 'Ground', 'price' => 175.0, 'currency' => 'AED', 'transit_days' => 7],
                ],
            ]),
        ]);

        $quotes = $service->quoteAll([
            'origin' => ['country' => 'AE'],
            'destination' => ['country' => 'SA'],
            'weight_kg' => 12.5,
            'parcels' => 1,
        ]);

        // 3 rates total, sorted by price ascending.
        $this->assertCount(3, $quotes);
        $this->assertEquals([175.0, 250.0, 480.0], array_column($quotes, 'price'));

        // Each row carries carrier code + name so the UI can render
        // a row badge without re-mapping.
        $this->assertSame('b', $quotes[0]['carrier']);
        $this->assertSame('B Logistics', $quotes[0]['carrier_name']);
        $this->assertSame('a', $quotes[1]['carrier']);
        $this->assertSame('A Express', $quotes[1]['carrier_name']);
    }

    public function test_quote_all_silently_drops_carriers_that_failed(): void
    {
        $service = $this->service([
            $this->carrier('ok', 'OK Co', ['success' => true,  'rates' => [
                ['service' => 'Standard', 'price' => 100.0, 'currency' => 'AED', 'transit_days' => 5],
            ]]),
            // The buyer should still see options from the working
            // carrier — not a hard error wall.
            $this->carrier('down', 'Down Co', ['success' => false, 'error' => 'HTTP 503 from upstream']),
        ]);

        $quotes = $service->quoteAll([]);

        $this->assertCount(1, $quotes);
        $this->assertSame('ok', $quotes[0]['carrier']);
    }

    public function test_quote_all_returns_empty_when_every_carrier_failed(): void
    {
        $service = $this->service([
            $this->carrier('a', 'A', ['success' => false, 'error' => 'down']),
            $this->carrier('b', 'B', ['success' => false, 'error' => 'down']),
        ]);

        $this->assertSame([], $service->quoteAll([]));
    }

    public function test_quote_all_handles_carrier_returning_success_but_empty_rates(): void
    {
        // A carrier that responds 200 OK but with no rates (e.g.
        // because the lane isn't served) should not contribute rows.
        $service = $this->service([
            $this->carrier('empty', 'Empty Co', ['success' => true, 'rates' => []]),
            $this->carrier('full', 'Full Co', ['success' => true, 'rates' => [
                ['service' => 'Standard', 'price' => 50.0, 'currency' => 'AED', 'transit_days' => 3],
            ]]),
        ]);

        $quotes = $service->quoteAll([]);
        $this->assertCount(1, $quotes);
        $this->assertSame('full', $quotes[0]['carrier']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  syncTracking — idempotency + happy path
    // ─────────────────────────────────────────────────────────────────

    public function test_sync_tracking_returns_zero_when_shipment_has_no_tracking_number(): void
    {
        $shipment = $this->makeShipment(trackingNumber: null);

        $service = $this->service([
            $this->carrier('a', 'A', ['success' => true]),
        ]);

        $this->assertSame(0, $service->syncTracking($shipment, 'a'));
        $this->assertSame(0, TrackingEvent::count());
    }

    public function test_sync_tracking_persists_new_events_only(): void
    {
        $shipment = $this->makeShipment(trackingNumber: 'TN-123');

        // First sync — 2 events arrive.
        $serviceFirst = $this->service([
            $this->carrier('a', 'A', ['success' => true], [
                'success' => true,
                'events' => [
                    ['at' => '2026-04-01 10:00:00', 'description' => 'Picked up',  'location' => 'Dubai',  'status' => 'in_transit'],
                    ['at' => '2026-04-02 14:00:00', 'description' => 'In transit', 'location' => 'Riyadh', 'status' => 'in_transit'],
                ],
            ]),
        ]);

        $insertedFirst = $serviceFirst->syncTracking($shipment, 'a');
        $this->assertSame(2, $insertedFirst);
        $this->assertSame(2, TrackingEvent::where('shipment_id', $shipment->id)->count());

        // Second sync — same 2 events + 1 new one. Should only insert
        // the new row, never duplicate.
        $serviceSecond = $this->service([
            $this->carrier('a', 'A', ['success' => true], [
                'success' => true,
                'events' => [
                    ['at' => '2026-04-01 10:00:00', 'description' => 'Picked up',  'location' => 'Dubai',  'status' => 'in_transit'],
                    ['at' => '2026-04-02 14:00:00', 'description' => 'In transit', 'location' => 'Riyadh', 'status' => 'in_transit'],
                    ['at' => '2026-04-03 09:30:00', 'description' => 'Delivered',  'location' => 'Riyadh', 'status' => 'delivered'],
                ],
            ]),
        ]);

        $insertedSecond = $serviceSecond->syncTracking($shipment, 'a');
        $this->assertSame(1, $insertedSecond);
        $this->assertSame(3, TrackingEvent::where('shipment_id', $shipment->id)->count());
    }

    public function test_sync_tracking_returns_zero_when_carrier_call_failed(): void
    {
        $shipment = $this->makeShipment(trackingNumber: 'TN-FAIL');

        $service = $this->service([
            $this->carrier('a', 'A', ['success' => true], ['success' => false, 'error' => 'auth denied']),
        ]);

        $this->assertSame(0, $service->syncTracking($shipment, 'a'));
        $this->assertSame(0, TrackingEvent::where('shipment_id', $shipment->id)->count());
    }

    // ─────────────────────────────────────────────────────────────────
    //  bookShipment — persists tracking number on success
    // ─────────────────────────────────────────────────────────────────

    public function test_book_shipment_writes_tracking_number_back_to_row(): void
    {
        $shipment = $this->makeShipment(trackingNumber: null);

        $service = $this->service([
            $this->carrier('a', 'A', ['success' => true]),
        ]);

        $result = $service->bookShipment($shipment, 'a', []);

        $this->assertTrue($result['success']);
        $this->assertSame('T-a', $result['tracking_number']);
        $this->assertSame('T-a', $shipment->fresh()->tracking_number);
    }
}
