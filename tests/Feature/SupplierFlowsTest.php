<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the supplier-tailored dashboard, Performance, and Settings pages.
 */
class SupplierFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeSupplier(): User
    {
        $company = Company::create([
            'name'                => 'Al-Noor Industries',
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => CompanyType::SUPPLIER,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => 'sup@noor.test',
            'city'                => 'Dubai',
            'country'             => 'UAE',
        ]);

        return User::create([
            'first_name' => 'Mohammed',
            'last_name'  => 'Salem',
            'email'      => 'mohammed.salem@alnoor.test',
            'password'   => 'secret-pass',
            'role'       => UserRole::SUPPLIER,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    public function test_supplier_dashboard_renders_with_supplier_specific_layout(): void
    {
        $supplier = $this->makeSupplier();

        $response = $this->actingAs($supplier)->get(route('dashboard'));

        $response->assertOk();
        // Unified shell shows the user's first name + company in the header.
        $response->assertSee('Mohammed');
        $response->assertSee('Al-Noor Industries');
        // Supplier-specific section titles must appear (built by supplierPayload).
        // Phase 1 / task 1.8 renamed the "New RFQs Available" panel to the
        // ranked "Recommended for you" list — assert against the live key.
        $response->assertSee(__('supplier.recommended_for_you'));
        $response->assertSee(__('supplier.my_active_bids'));
    }

    public function test_supplier_sees_role_badge_in_sidebar(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(strtoupper(__('role.supplier')));
    }

    public function test_buyer_sees_buyer_role_badge_not_supplier_dashboard(): void
    {
        $company = Company::create(['name' => 'Buyer Inc', 'registration_number' => 'B1', 'type' => CompanyType::BUYER, 'status' => CompanyStatus::ACTIVE]);
        $buyer = User::create(['first_name' => 'B', 'last_name' => 'B', 'email' => 'b@b.test', 'password' => 'secret-pass', 'role' => UserRole::BUYER, 'status' => UserStatus::ACTIVE, 'company_id' => $company->id]);

        $response = $this->actingAs($buyer)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee(strtoupper(__('role.buyer')));
        // Buyer should NOT see the supplier-only "Recommended for you"
        // RFQ matcher panel or its category-fit subtitle.
        $response->assertDontSee(__('supplier.recommended_for_you'));
        $response->assertDontSee(__('supplier.recommended_subtitle'));
    }

    // ---------------------------------------------------------------------
    // Performance page
    // ---------------------------------------------------------------------

    public function test_performance_page_renders_for_authenticated_user(): void
    {
        $supplier = $this->makeSupplier();
        // PerformanceController gates on `reports.view` permission. Grant it
        // explicitly so the test mirrors a supplier whose company manager
        // ticked the "view reports" permission box.
        $supplier->update(['permissions' => ['reports.view']]);

        $this->actingAs($supplier)
            ->get(route('performance.index'))
            ->assertOk()
            ->assertSee(__('performance.title'))
            ->assertSee(__('performance.total_bids'))
            ->assertSee(__('performance.monthly'))
            ->assertSee(__('performance.quality'));
    }

    public function test_performance_page_blocks_guests(): void
    {
        $this->get(route('performance.index'))
            ->assertRedirect();
    }

    // ---------------------------------------------------------------------
    // Settings page (tabs)
    // ---------------------------------------------------------------------

    public function test_settings_page_defaults_to_company_tab(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(__('settings.company_profile'))
            ->assertSee('Al-Noor Industries');
    }

    public function test_settings_personal_tab_renders_user_info(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->get(route('settings.index', ['tab' => 'personal']))
            ->assertOk()
            ->assertSee(__('settings.personal_info'))
            ->assertSee('Mohammed');
    }

    public function test_settings_notifications_tab_renders(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->get(route('settings.index', ['tab' => 'notifications']))
            ->assertOk()
            ->assertSee('Notification Preferences');
    }

    public function test_settings_security_tab_renders(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->get(route('settings.index', ['tab' => 'security']))
            ->assertOk()
            ->assertSee(__('profile.change_password'));
    }

    public function test_settings_payment_tab_renders(): void
    {
        $supplier = $this->makeSupplier();

        // The payment tab template branches by role: suppliers see the
        // "Receiving Bank Account" form (where buyers will deposit funds),
        // while buyers see the "Bank Transfer" outgoing-method card.
        $this->actingAs($supplier)
            ->get(route('settings.index', ['tab' => 'payment']))
            ->assertOk()
            ->assertSee('Receiving Bank Account');
    }

    public function test_settings_company_update_persists(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->patch(route('settings.company.update'), [
                'name'        => 'Al-Noor Renamed',
                'description' => 'New description',
                'city'        => 'Sharjah',
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'company']));

        $this->assertEquals('Al-Noor Renamed', $supplier->fresh()->company->name);
    }

    public function test_settings_personal_update_persists(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->patch(route('settings.personal.update'), [
                'first_name' => 'Renamed',
                'last_name'  => 'Salem',
                'email'      => $supplier->email,
                'phone'      => '+971500000099',
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'personal']));

        $this->assertEquals('Renamed', $supplier->fresh()->first_name);
    }

    public function test_settings_notifications_update_persists_in_custom_permissions(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->patch(route('settings.notifications.update'), [
                'rfq_matches'         => '1',
                'bid_updates'         => '1',
                'contract_milestones' => '1',
                'messages'            => '1',
                // marketing intentionally not sent → off
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'notifications']));

        $prefs = $supplier->fresh()->custom_permissions['notifications'] ?? [];
        $this->assertTrue($prefs['rfq_matches'] ?? false);
        $this->assertFalse($prefs['marketing'] ?? true);
    }

    public function test_settings_security_update_changes_password(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($supplier)
            ->patch(route('settings.security.update'), [
                'current_password'      => 'secret-pass',
                'password'              => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'security']));

        // New password works.
        auth()->logout();
        $this->post('/login', ['email' => $supplier->email, 'password' => 'brand-new-pass'])
            ->assertRedirect();
    }
}
