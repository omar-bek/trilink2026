<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyCardVault extends Model
{
    use SoftDeletes;

    protected $table = 'company_card_vault';

    protected $fillable = [
        'company_id', 'saved_by_user_id',
        'gateway', 'token', 'fingerprint',
        'brand', 'last4', 'exp_month', 'exp_year',
        'cardholder_name', 'issuing_country',
        'is_default', 'is_company_card', 'label',
        'last_used_at', 'revoked_at', 'revoked_by_user_id',
    ];

    protected $hidden = ['token', 'fingerprint'];

    protected function casts(): array
    {
        return [
            // Tokens are worthless without the gateway's secret but we
            // still encrypt at rest so a read-only DB leak yields nothing.
            'token' => 'encrypted',
            'is_default' => 'boolean',
            'is_company_card' => 'boolean',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by_user_id');
    }

    /**
     * Render a masked card label for UI — "Visa •••• 4242 · 12/28".
     * Never surface the token or fingerprint to the browser.
     */
    public function displayLabel(): string
    {
        $brand = ucfirst($this->brand ?? 'card');
        $exp = sprintf('%02d/%02d', $this->exp_month, $this->exp_year % 100);

        return "{$brand} •••• {$this->last4} · {$exp}";
    }

    public function isExpired(): bool
    {
        $end = now()->setDate($this->exp_year, $this->exp_month, 1)->endOfMonth();

        return $end->isPast();
    }
}
