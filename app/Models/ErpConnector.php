<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Phase 7 — customer-managed ERP integration row. Stores the connector
 * type (odoo, netsuite, sap, quickbooks, custom), base URL, and the
 * customer-provided credentials in an encrypted column.
 *
 * The credentials are JSON: connector-specific (Odoo wants db + login +
 * password, NetSuite wants account_id + consumer_key + token_secret,
 * etc.). The connector classes know how to decode their own shape.
 */
class ErpConnector extends Model
{
    use HasFactory;

    public const TYPE_ODOO       = 'odoo';
    public const TYPE_NETSUITE   = 'netsuite';
    public const TYPE_SAP        = 'sap';
    public const TYPE_QUICKBOOKS = 'quickbooks';
    public const TYPE_CUSTOM     = 'custom';

    protected $fillable = [
        'company_id',
        'type',
        'label',
        'base_url',
        'credentials_encrypted',
        'is_active',
        'last_sync_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_sync_at' => 'datetime',
            'metadata'     => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Decrypt + return the credentials as an array. Throws when the
     * encrypted blob can't be decoded — the controller wraps in try/catch
     * and surfaces a "credentials corrupted, please re-enter" message.
     */
    public function credentials(): array
    {
        if (!$this->credentials_encrypted) {
            return [];
        }
        $json = Crypt::decryptString($this->credentials_encrypted);
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encrypt + persist a credentials array. Used by the controller's
     * store/update so we keep encryption out of the request handler.
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = Crypt::encryptString(json_encode($credentials));
    }
}
