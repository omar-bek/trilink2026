<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\Sanctions\UaeLocalListProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 9 (UAE Compliance Roadmap) — Cross-cutting Hardening.
 *
 * Covers:
 *   - UAE Local Terrorist List provider matching
 *   - Audit chain anchoring command (dry-run)
 *   - Sanctions re-screening command (dry-run)
 *   - TRC column existence
 *   - Audit chain anchors table existence
 */
class CrossCuttingPhase9Test extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────
    //  UAE Local Terrorist List Provider
    // ─────────────────────────────────────────────────────────────────

    public function test_uae_local_list_returns_clean_when_no_fixture(): void
    {
        Storage::fake('local');
        $provider = new UaeLocalListProvider;
        $this->assertSame('clean', $provider->screen('Innocent Company LLC'));
    }

    public function test_uae_local_list_returns_hit_on_exact_id_match(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sanctions/uae-local-list.json', json_encode([
            'updated_at' => '2026-04-01',
            'entries' => [
                ['name' => 'Bad Actor', 'id_number' => 'REG-999', 'nationality' => 'XX'],
            ],
        ]));

        $provider = new UaeLocalListProvider;
        $this->assertSame('hit', $provider->screen('Totally Different Name', 'REG-999'));
    }

    public function test_uae_local_list_returns_review_on_fuzzy_name_match(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sanctions/uae-local-list.json', json_encode([
            'updated_at' => '2026-04-01',
            'entries' => [
                ['name' => 'Mohammed Al-Rashid', 'id_number' => null, 'nationality' => 'AE'],
            ],
        ]));

        $provider = new UaeLocalListProvider;
        // Levenshtein ≤ 3 on normalised name: "mohammad rashid" vs "mohammed rashid" = dist 1
        $this->assertSame('review', $provider->screenPerson('Mohammad Rashid'));
    }

    public function test_uae_local_list_returns_clean_on_no_match(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sanctions/uae-local-list.json', json_encode([
            'updated_at' => '2026-04-01',
            'entries' => [
                ['name' => 'Bad Actor', 'id_number' => 'REG-999'],
            ],
        ]));

        $provider = new UaeLocalListProvider;
        $this->assertSame('clean', $provider->screen('Totally Different Company', 'REG-123'));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Audit chain anchoring
    // ─────────────────────────────────────────────────────────────────

    public function test_anchor_command_persists_to_db_table(): void
    {
        // Seed an audit log row so the chain has something to anchor.
        AuditLog::create([
            'action' => AuditAction::CREATE,
            'resource_type' => 'Test', 'resource_id' => 1,
            'status' => 'success',
        ]);

        $this->artisan('audit:anchor-chain')->assertSuccessful();

        $this->assertDatabaseHas('audit_chain_anchors', [
            'chain_head_id' => 1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Sanctions re-screening command
    // ─────────────────────────────────────────────────────────────────

    public function test_rescreen_command_runs_dry_run(): void
    {
        // Create one active company to be "screened"
        Company::create([
            'name' => 'Rescreen Co',
            'registration_number' => 'REG-'.uniqid(),
            'type' => CompanyType::SUPPLIER,
            'status' => CompanyStatus::ACTIVE,
            'email' => 'rs@t.test', 'city' => 'Dubai', 'country' => 'AE',
        ]);

        $this->artisan('sanctions:rescreen-all', ['--dry-run' => true])
            ->assertSuccessful();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Schema verification
    // ─────────────────────────────────────────────────────────────────

    public function test_trc_columns_exist_on_companies(): void
    {
        $this->assertTrue(Schema::hasColumn('companies', 'trc_path'));
        $this->assertTrue(Schema::hasColumn('companies', 'trc_expires_at'));
    }

    public function test_audit_chain_anchors_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('audit_chain_anchors'));
    }
}
