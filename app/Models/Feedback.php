<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'contract_id',
        'rater_company_id',
        'target_company_id',
        'rater_user_id',
        'rating',
        'comment',
        'quality_score',
        'on_time_score',
        'communication_score',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'quality_score' => 'integer',
            'on_time_score' => 'integer',
            'communication_score' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function raterCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'rater_company_id');
    }

    public function targetCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'target_company_id');
    }

    public function raterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }
}
