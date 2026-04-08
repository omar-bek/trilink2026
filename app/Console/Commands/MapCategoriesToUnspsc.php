<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

/**
 * Phase 1 / task 1.11 — best-effort mapping of legacy/seeded categories
 * onto the UNSPSC taxonomy seeded by UnspscSegmentsSeeder.
 *
 * Strategy:
 *   1. Load every UNSPSC top-level segment we know about.
 *   2. For every category WITHOUT an unspsc_segment, derive a list of
 *      keywords from its name and check whether any segment's name
 *      contains one of them.
 *   3. If exactly one segment matches, set the category's
 *      `unspsc_segment` (and `unspsc_code` to the 8-digit form).
 *   4. Ambiguous matches and zero-matches are reported but left untouched
 *      so a human can decide.
 *
 * The command is idempotent and read-only by default — pass --apply to
 * persist the changes. Without it, the command prints a dry-run summary.
 */
class MapCategoriesToUnspsc extends Command
{
    protected $signature   = 'unspsc:map {--apply : Persist changes (omit for a dry run)}';
    protected $description = 'Best-effort mapping of existing categories to UNSPSC segments by keyword';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $segments = Category::query()
            ->whereNotNull('unspsc_segment')
            ->whereNull('parent_id')
            ->get(['id', 'name', 'name_ar', 'unspsc_segment']);

        if ($segments->isEmpty()) {
            $this->error('No UNSPSC segments found. Run `php artisan db:seed --class=UnspscSegmentsSeeder` first.');
            return self::FAILURE;
        }

        // Pre-compute a keyword set for each segment so the inner loop
        // doesn't re-tokenise on every category.
        $segmentKeywords = $segments->mapWithKeys(function (Category $seg) {
            $keywords = $this->keywords($seg->name);
            // Drop very short / generic words ("and", "or") and dedupe.
            return [
                $seg->id => [
                    'segment_code' => (int) $seg->unspsc_segment,
                    'keywords'     => array_values(array_filter($keywords, fn ($k) => strlen($k) >= 4)),
                ],
            ];
        })->all();

        $unmapped = Category::query()
            ->whereNull('unspsc_segment')
            ->get(['id', 'name', 'name_ar']);

        $matched   = 0;
        $ambiguous = 0;
        $missed    = 0;

        foreach ($unmapped as $cat) {
            $catKeywords = $this->keywords($cat->name);
            $hits = [];

            foreach ($segmentKeywords as $segId => $row) {
                if (array_intersect($catKeywords, $row['keywords'])) {
                    $hits[$segId] = $row['segment_code'];
                }
            }

            if (count($hits) === 1) {
                $segCode = (int) reset($hits);
                $matched++;
                $this->line(sprintf('  ✓ %s → %02d', $cat->name, $segCode));
                if ($apply) {
                    $cat->update([
                        'unspsc_segment' => $segCode,
                        'unspsc_code'    => sprintf('%02d000000', $segCode),
                    ]);
                }
            } elseif (count($hits) > 1) {
                $ambiguous++;
                $this->warn(sprintf('  ? %s → %d candidate segments (skipped)', $cat->name, count($hits)));
            } else {
                $missed++;
                $this->line(sprintf('  · %s (no match)', $cat->name));
            }
        }

        $this->newLine();
        $this->info(sprintf('Mapped: %d · Ambiguous: %d · No match: %d', $matched, $ambiguous, $missed));
        if (! $apply) {
            $this->comment('Dry run — re-run with --apply to persist.');
        }

        return self::SUCCESS;
    }

    /**
     * Tokenise a category name into lowercase keywords. Strips punctuation,
     * splits on whitespace, deduplicates, and removes obvious stop words.
     *
     * @return array<int,string>
     */
    private function keywords(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $stop = ['and', 'the', 'for', 'with', 'including', 'related', 'general', 'other', 'products'];
        $tokens = preg_split('/[\s\/,&\-_]+/', strtolower($text));
        $tokens = array_filter((array) $tokens, fn ($t) => $t !== '' && ! in_array($t, $stop, true));

        return array_values(array_unique($tokens));
    }
}
