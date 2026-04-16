<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AuditLog extends Model
{
    use Searchable;

    protected $fillable = [
        'user_id',
        'company_id',
        'action',
        'resource_type',
        'resource_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'status',
        'hash',
        'previous_hash',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            // Phase 2.5 (UAE Compliance Roadmap — post-implementation
            // hardening). Audit log rows contain personal data — IP
            // addresses, user agents, before/after JSON snapshots that
            // routinely include emails / names / TRNs / addresses — so
            // they must be encrypted at rest under PDPL Article 20.
            //
            // The hash chain is computed against the RAW DB bytes via
            // $log->getAttributes(), which for encrypted casts returns
            // the ciphertext as stored on disk. Since the ciphertext is
            // stable in the DB (we never re-encrypt at read time), the
            // chain stays internally consistent — see the canonicalize()
            // and verify-chain command for the recipe.
            'before' => 'encrypted:array',
            'after' => 'encrypted:array',
            'ip_address' => 'encrypted',
            'user_agent' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Build the deterministic, canonical input that gets hashed.
     *
     * Public so the verify-chain command can use the *same* recipe — if
     * either side computes a different canonical bytes, the chain looks
     * tampered when it isn't. Keeping this in one place is critical.
     *
     * Excludes: id (auto-increment, not part of payload), hash and
     * previous_hash (we're computing them), updated_at (audit logs are
     * write-once so it equals created_at), deleted_at (no soft deletes).
     *
     * Includes created_at: the row's own timestamp is part of its identity.
     * It must be set BEFORE we hash, which is why the booted hook below
     * pins it explicitly instead of letting Eloquent set it implicitly.
     */
    public static function canonicalize(array $row): string
    {
        // The action enum stores as a string when serialized; normalise so
        // both at-write-time and at-verify-time we hash the same thing.
        $action = $row['action'] ?? null;
        if ($action instanceof AuditAction) {
            $action = $action->value;
        }

        // Field order matters for deterministic JSON output. We don't rely
        // on PHP's array ordering — we explicitly construct the array in a
        // fixed sequence and use JSON_UNESCAPED_UNICODE so Arabic content
        // doesn't get \uXXXX escaped (which would make canonical bytes
        // different on different PHP versions).
        $payload = [
            'user_id' => $row['user_id'] ?? null,
            'company_id' => $row['company_id'] ?? null,
            'action' => $action,
            'resource_type' => $row['resource_type'] ?? null,
            'resource_id' => isset($row['resource_id']) ? (int) $row['resource_id'] : null,
            'before' => $row['before'] ?? null,
            'after' => $row['after'] ?? null,
            'ip_address' => $row['ip_address'] ?? null,
            'user_agent' => $row['user_agent'] ?? null,
            'status' => $row['status'] ?? null,
            'created_at' => isset($row['created_at'])
                ? (is_string($row['created_at']) ? $row['created_at'] : (string) $row['created_at'])
                : null,
        ];

        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '';
    }

    /**
     * Compute the hash for a row, given its canonical bytes and the chain
     * parent. Same recipe used at write time and at verify time.
     */
    public static function computeHash(string $canonical, ?string $previousHash): string
    {
        return hash('sha256', ($previousHash ?? '').'|'.$canonical);
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            // Pin created_at BEFORE hashing — Eloquent would set it itself
            // a moment later, but we need it in the canonical payload. If
            // the caller already set it (e.g. backfill), keep their value.
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
            if (empty($log->updated_at)) {
                $log->updated_at = $log->created_at;
            }

            // Lock the latest row while we read its hash, so two concurrent
            // inserts don't both append the same parent and fork the chain.
            // The lock is held for microseconds — only the SELECT — and the
            // INSERT itself doesn't need additional locking because the
            // chain pointer is already decided.
            $previousHash = DB::transaction(function () {
                return static::query()
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('hash');
            });

            $log->previous_hash = $previousHash ?: null;

            $log->hash = static::computeHash(
                static::canonicalize($log->getAttributes()),
                $previousHash
            );
        });
    }
}
