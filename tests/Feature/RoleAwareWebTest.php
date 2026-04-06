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
 * Tests for role-aware features:
 *
 * - Login redirects each role to its tailored landing URL.
 * - 403 page renders when web.role middleware blocks access.
 * - Profile / Company users / Admin / Government landing pages.
 * - Forgot / reset password flow basic plumbing.
 * - Sidebar role filtering (asserted via response content checks).
 */
class RoleAwareWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(UserRole $role, ?CompanyType $companyType = null): User
    {
        $type = $companyType ?? match ($role) {
            UserRole::SUPPLIER         => CompanyType::SUPPLIER,
            UserRole::LOGISTICS        => CompanyType::LOGISTICS,
            UserRole::CLEARANCE        => CompanyType::CLEARANCE,
            UserRole::SERVICE_PROVIDER => CompanyType::SERVICE_PROVIDER,
            UserRole::GOVERNMENT       => CompanyType::GOVERNMENT,
            default                    => CompanyType::BUYER,
        };

        $company = Company::create([
            'name'                => ucfirst($role->value) . ' Co',
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => $type,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => $role->value . '@test.test',
            'city'                => 'Dubai',
            'country'             => 'UAE',
        ]);

        return User::create([
            'first_name' => ucfirst($role->value),
            'last_name'  => 'Tester',
            'email'      => $role->value . '-' . uniqid() . '@t.test',
            'password'   => 'secret-pass',
            'role'       => $role,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Login redirects
    // ---------------------------------------------------------------------

    public function test_buyer_login_lands_on_dashboard(): void
    {
        $user = $this->makeUser(UserRole::BUYER);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_admin_login_lands_on_admin_console(): void
    {
        $user = $this->makeUser(UserRole::ADMIN);

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'secret-pass',
        ])->assertRedirect(route('admin.index'));
    }

    public function test_government_login_lands_on_gov_console(): void
    {
        $user = $this->makeUser(UserRole::GOVERNMENT);

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'secret-pass',
        ])->assertRedirect(route('gov.index'));
    }

    // ---------------------------------------------------------------------
    // 403 page
    // ---------------------------------------------------------------------

    public function test_403_view_renders_when_role_blocked(): void
    {
        $supplier = $this->makeUser(UserRole::SUPPLIER);

        $this->actingAs($supplier)
            ->post('/dashboard/purchase-requests', [
                'title'         => 'Forbidden',
                'budget'        => 100,
                'currency'      => 'AED',
                'required_date' => now()->addDays(10)->format('Y-m-d'),
            ])
            ->assertForbidden()
            ->assertSee('403');
    }

    // ---------------------------------------------------------------------
    // New role-specific pages
    // ---------------------------------------------------------------------

    public function test_admin_can_access_admin_console(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee(__('admin.title'));
    }

    public function test_buyer_cannot_access_admin_console(): void
    {
        $buyer = $this->makeUser(UserRole::BUYER);

        $this->actingAs($buyer)
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    public function test_government_can_access_gov_console(): void
    {
        $gov = $this->makeUser(UserRole::GOVERNMENT);

        $this->actingAs($gov)
            ->get(route('gov.index'))
            ->assertOk()
            ->assertSee(__('gov.title'));
    }

    public function test_supplier_cannot_access_gov_console(): void
    {
        $supplier = $this->makeUser(UserRole::SUPPLIER);

        $this->actingAs($supplier)
            ->get(route('gov.index'))
            ->assertForbidden();
    }

    public function test_company_manager_can_access_team_page(): void
    {
        $manager = $this->makeUser(UserRole::COMPANY_MANAGER);

        $this->actingAs($manager)
            ->get(route('company.users'))
            ->assertOk()
            ->assertSee(__('company.users.title'));
    }

    public function test_buyer_cannot_access_team_page(): void
    {
        $buyer = $this->makeUser(UserRole::BUYER);

        $this->actingAs($buyer)
            ->get(route('company.users'))
            ->assertForbidden();
    }

    public function test_company_manager_can_invite_team_member(): void
    {
        $manager = $this->makeUser(UserRole::COMPANY_MANAGER);

        $this->actingAs($manager)
            ->post(route('company.users.store'), [
                'first_name' => 'New',
                'last_name'  => 'Hire',
                'email'      => 'newhire@team.test',
                'role'       => UserRole::SUPPLIER->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email'      => 'newhire@team.test',
            'company_id' => $manager->company_id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Profile
    // ---------------------------------------------------------------------

    public function test_user_can_view_profile_page(): void
    {
        $user = $this->makeUser(UserRole::BUYER);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(__('profile.title'));
    }

    public function test_user_can_update_profile(): void
    {
        $user = $this->makeUser(UserRole::BUYER);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Renamed',
                'last_name'  => 'User',
                'email'      => $user->email,
                'phone'      => '+971500000000',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertEquals('Renamed', $user->fresh()->first_name);
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->makeUser(UserRole::BUYER);

        $this->actingAs($user)
            ->patch(route('profile.password'), [
                'current_password'      => 'secret-pass',
                'password'              => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertRedirect(route('profile.edit'));

        // Old password no longer works.
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertSessionHasErrors('email');

        // New password works.
        $this->post('/login', ['email' => $user->email, 'password' => 'brand-new-pass'])
            ->assertRedirect();
    }

    // ---------------------------------------------------------------------
    // Forgot / reset password
    // ---------------------------------------------------------------------

    public function test_forgot_password_form_renders(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee(__('auth.forgot_password'));
    }

    public function test_forgot_password_post_returns_neutral_message(): void
    {
        $this->post('/forgot-password', ['email' => 'unknown@test.test'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    // ---------------------------------------------------------------------
    // Sidebar role filtering (smoke check via response content)
    // ---------------------------------------------------------------------

    public function test_sidebar_for_supplier_hides_purchase_requests(): void
    {
        $supplier = $this->makeUser(UserRole::SUPPLIER);

        $response = $this->actingAs($supplier)->get(route('dashboard'));
        $response->assertOk();

        // Suppliers should NOT see "Purchase Requests" sidebar item.
        $response->assertDontSee(__('nav.purchase_requests'));
        // But they SHOULD see Bids and Contracts.
        $response->assertSee(__('nav.bids'));
        $response->assertSee(__('nav.contracts'));
    }

    public function test_sidebar_for_buyer_shows_purchase_requests(): void
    {
        $buyer = $this->makeUser(UserRole::BUYER);

        $this->actingAs($buyer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('nav.purchase_requests'));
    }

    public function test_sidebar_for_logistics_shows_shipments_not_payments(): void
    {
        $logistics = $this->makeUser(UserRole::LOGISTICS);

        $response = $this->actingAs($logistics)->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee(__('nav.shipment_tracking'));
        $response->assertDontSee(__('nav.payment_management'));
        // Logistics also doesn't create PRs.
        $response->assertDontSee(__('nav.purchase_requests'));
    }
}
