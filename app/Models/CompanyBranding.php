<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBranding extends Model
{
    protected $table = 'company_branding';

    protected $fillable = [
        'company_id',
        'invoice_logo_path', 'email_logo_path',
        'primary_color', 'accent_color',
        'email_from_name', 'email_from_address', 'email_sender_verified',
        'invoice_footer_text', 'contract_footer_text', 'po_footer_text',
    ];

    protected function casts(): array
    {
        return [
            'email_sender_verified' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
