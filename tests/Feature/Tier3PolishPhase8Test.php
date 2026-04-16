<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Http\Middleware\AdminIpAllowlist;
use App\Http\Middleware\SecurityHeaders;
use App\Models\AuditLog;
use App\Models\CertificateUpload;
use App\Models\Company;
use App\Services\Customs\DutyCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Tier3PolishPhase8Test extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────
    //  Security Headers
    // ─────────────────────────────────────────────────────────────────

    public function test_security_headers_present_on_web_responses(): void
    {
        // SecurityHeaders is registered globally in bootstrap/app.php.
        // Hit a public web route to exercise the full middleware stack.
        $response = $this->get(route('public.privacy'));
        $response->assertOk();

        // The middleware sets these headers on every web response.
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $this->assertNotNull($response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('Referrer-Policy'));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Admin IP Allowlist
    // ─────────────────────────────────────────────────────────────────

    public function test_admin_ip_allowlist_allows_listed_ip(): void
    {
        config()->set('security.admin_ip_allowlist', '10.0.0.0/8,203.0.113.42');

        $middleware = new AdminIpAllowlist;
        $request = Request::create('/admin');
        $request->server->set('REMOTE_ADDR', '10.0.1.5');

        $response = $middleware->handle($request, fn () => new Response('ok'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_admin_ip_allowlist_blocks_unlisted_ip(): void
    {
        config()->set('security.admin_ip_allowlist', '10.0.0.0/8');

        $middleware = new AdminIpAllowlist;
        $request = Request::create('/admin');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => new Response('ok'));
    }

    public function test_admin_ip_allowlist_passes_when_no_list_configured(): void
    {
        config()->set('security.admin_ip_allowlist', '');

        $middleware = new AdminIpAllowlist;
        $request = Request::create('/admin');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        $response = $middleware->handle($request, fn () => new Response('ok'));
        $this->assertSame(200, $response->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────────
    //  Customs Duty Calculator
    // ─────────────────────────────────────────────────────────────────

    public function test_gcc_origin_gets_zero_duty(): void
    {
        $service = new DutyCalculatorService;

        $result = $service->calculate('SA', '8471.30'); // computers from Saudi
        $this->assertSame(0.0, $result['rate']);
        $this->assertStringContainsString('intra-GCC', $result['basis']);
    }

    public function test_non_gcc_standard_goods_get_five_percent(): void
    {
        $service = new DutyCalculatorService;

        $result = $service->calculate('CN', '8471.30'); // computers from China
        $this->assertSame(5.0, $result['rate']);
        $this->assertStringContainsString('5%', $result['basis']);
    }

    public function test_exempt_hs_code_gets_zero_duty(): void
    {
        $service = new DutyCalculatorService;

        $result = $service->calculate('IN', '3004.90'); // medicines from India
        $this->assertSame(0.0, $result['rate']);
        $this->assertStringContainsString('exempt', $result['basis']);
    }

    public function test_tobacco_hs_code_gets_hundred_percent(): void
    {
        $service = new DutyCalculatorService;

        $result = $service->calculate('US', '2402.10'); // cigars from USA
        $this->assertSame(100.0, $result['rate']);
        $this->assertStringContainsString('protective', $result['basis']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Certificate Upload Model
    // ─────────────────────────────────────────────────────────────────

    public function test_certificate_upload_model_persists_and_reads(): void
    {
        $company = Company::create([
            'name' => 'Cert Co', 'registration_number' => 'REG-'.uniqid(),
            'type' => CompanyType::SUPPLIER,
            'status' => CompanyStatus::ACTIVE,
            'email' => 'cert@t.test', 'city' => 'Dubai', 'country' => 'AE',
        ]);

        $cert = CertificateUpload::create([
            'company_id' => $company->id,
            'certificate_type' => CertificateUpload::TYPE_COO,
            'certificate_number' => 'COO-2026-001',
            'issuer' => 'Dubai Chamber of Commerce',
            'issued_date' => now()->subMonth(),
            'expires_date' => now()->addYear(),
            'status' => CertificateUpload::STATUS_VERIFIED,
        ]);

        $this->assertTrue($cert->fresh()->isActive());
        $this->assertSame('coo', $cert->certificate_type);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Audit chain anchoring command
    // ─────────────────────────────────────────────────────────────────

    public function test_audit_anchor_command_runs_dry_run(): void
    {
        // Seed at least one audit log
        AuditLog::create([
            'action' => AuditAction::CREATE,
            'resource_type' => 'Test', 'resource_id' => 1,
            'status' => 'success',
        ]);

        $this->artisan('audit:anchor-chain', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN');
    }
}
