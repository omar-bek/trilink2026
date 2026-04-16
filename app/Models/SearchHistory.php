<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 1 / task 1.13 — search history per user.
 *
 * The static helpers `record()` and `recentFor()` are the only API the
 * application uses; the row is otherwise read-only after creation.
 * `record()` is idempotent against the user's most-recent term so a
 * page refresh doesn't fill the history with duplicates.
 */
class SearchHistory extends Model
{
    protected $table = 'search_history';

    protected $fillable = [
        'user_id',
        'term',
        'result_count',
    ];

    protected function casts(): array
    {
        return [
            'result_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Persist a search for a user. De-dupes against the user's most-recent
     * row, then prunes anything older than the last 10 to keep the table
     * size bounded per user.
     */
    public static function record(int $userId, string $term, int $resultCount = 0): void
    {
        $term = mb_substr(trim($term), 0, 200);
        if ($term === '') {
            return;
        }

        $latest = self::where('user_id', $userId)->latest()->first();
        if ($latest && $latest->term === $term) {
            // Same term as the previous entry — bump the timestamp + count.
            $latest->update(['result_count' => $resultCount, 'updated_at' => now()]);

            return;
        }

        self::create([
            'user_id' => $userId,
            'term' => $term,
            'result_count' => $resultCount,
        ]);

        // Prune anything beyond the most-recent 10 entries for this user.
        $keepIds = self::where('user_id', $userId)->latest()->limit(10)->pluck('id');
        self::where('user_id', $userId)->whereNotIn('id', $keepIds)->delete();
    }

    /** @return Collection<int,SearchHistory> */
    public static function recentFor(int $userId, int $limit = 10): Collection
    {
        return self::where('user_id', $userId)->latest()->limit($limit)->get();
    }
}
