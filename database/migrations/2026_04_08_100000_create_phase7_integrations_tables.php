<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — Public API + Integrations. Schema lays down:
 *
 *   - webhook_endpoints: a customer-managed list of HTTPS URLs that
 *     receive event payloads when contracts/payments/shipments change
 *     state. Per-endpoint event filter + signing secret.
 *   - webhook_deliveries: append-only log of every delivery attempt so
 *     the customer can audit retries and failures from the UI.
 *   - sso_configurations: per-company SAML/OIDC config (issuer, ACS URL,
 *     X.509 cert). One row per company; nullable so smaller tenants
 *     stick with email/password.
 *   - scim_users: shadow rows for users provisioned by an external IdP
 *     via SCIM 2.0. Lets us reverse the link back to the Trilink user
 *     when the IdP issues a delete/disable request.
 *   - erp_connectors: customer-managed config for third-party ERPs
 *     (Odoo, NetSuite, etc.). Each row carries the connector type,
 *     base URL, encrypted credentials, and last-sync timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Buyer-friendly label so the customer can revoke
            // "Zapier prod" separately from "internal ETL".
            $table->string('label', 100);
            $table->string('url', 500);
            // Comma-separated event keys: contract.signed,payment.completed,
            // shipment.delivered, etc. Empty = receive all events.
            $table->string('events', 500)->default('');
            // HMAC-SHA256 secret. Generated server-side and shown once.
            $table->string('secret', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_delivered_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            // pending → success | failed. We never delete a row — the
            // dashboard renders the last 100 attempts per endpoint.
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'created_at']);
            $table->index(['event', 'status']);
        });

        Schema::create('sso_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // 'saml' | 'oidc' — controls which fields below are required.
            $table->string('protocol', 10);
            $table->string('issuer', 500);
            // SAML only.
            $table->string('sso_url', 500)->nullable();
            $table->text('x509_cert')->nullable();
            // OIDC only.
            $table->string('client_id', 200)->nullable();
            $table->text('client_secret')->nullable();
            $table->string('discovery_url', 500)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // One IdP per company. Switching IdPs replaces the row in place.
            $table->unique('company_id');
        });

        Schema::create('scim_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // External SCIM id assigned by the IdP. Idempotency key for
            // PUT/PATCH requests so re-provisioning doesn't dupe rows.
            $table->string('external_id', 200);
            // Active vs deactivated by the IdP. Distinct from
            // users.is_active because an IdP can soft-deactivate a row
            // without us touching the platform user directly.
            $table->boolean('is_active')->default(true);
            $table->json('scim_payload')->nullable();
            $table->timestamps();

            $table->unique('external_id');
        });

        Schema::create('erp_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // 'odoo' | 'netsuite' | 'sap' | 'quickbooks' | 'custom'
            $table->string('type', 30);
            $table->string('label', 100);
            $table->string('base_url', 500);
            // Encrypted via Laravel's Crypt facade — never returned to
            // the client raw. The wrapper Service handles decryption.
            $table->text('credentials_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_connectors');
        Schema::dropIfExists('scim_users');
        Schema::dropIfExists('sso_configurations');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
