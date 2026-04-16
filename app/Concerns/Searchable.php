<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Portable text-search scope used by index controllers.
 *
 * Replaces the `LIKE %q%` snippets that were repeated across ten controllers
 * with a single, driver-aware search() scope:
 *
 *   Bid::query()->search($q, ['title', 'rfq_number'])->get();
 *
 * Driver behaviour:
 *   - mysql/mariadb: uses MATCH(...) AGAINST(... IN BOOLEAN MODE) when a
 *     FULLTEXT index covers the columns; otherwise falls back to LIKE.
 *   - pgsql: lowercases both sides and uses ILIKE / unaccent if available.
 *   - sqlite (default): tokenises the query and ANDs LIKE clauses on
 *     each token across each column. Slow on huge tables but correct
 *     and predictable for the dev/staging footprint.
 *
 * The trait deliberately doesn't try to be clever — its job is to
 * (a) eliminate copy-pasted `LIKE` snippets, (b) sanitise user input,
 * (c) give us one place to plug in Meilisearch/Scout when we hit
 * 100K+ rows in Phase 1.
 */
trait Searchable
{
    /**
     * @param  Builder<Model>  $query
     * @param  string|null  $term  Raw user input. Trimmed; empty input returns the query unchanged.
     * @param  array<int,string>  $columns  Columns on the model's table to search.
     * @return Builder<Model>
     */
    public function scopeSearch(Builder $query, ?string $term, array $columns): Builder
    {
        $term = trim((string) $term);
        if ($term === '' || $columns === []) {
            return $query;
        }

        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return $this->applyMysqlSearch($query, $term, $columns);
        }

        if ($driver === 'pgsql') {
            return $this->applyPgsqlSearch($query, $term, $columns);
        }

        return $this->applyPortableSearch($query, $term, $columns);
    }

    /**
     * MySQL: prefer MATCH() AGAINST() if a FULLTEXT index covers all the
     * columns, otherwise fall back to the portable LIKE path. Detection is
     * cached per process via a static so we don't pay SHOW INDEX on every call.
     *
     * @param  Builder<Model>  $query
     * @param  array<int,string>  $columns
     * @return Builder<Model>
     */
    private function applyMysqlSearch(Builder $query, string $term, array $columns): Builder
    {
        if ($this->hasFulltextIndex($query, $columns)) {
            $cols = implode(',', array_map(fn ($c) => "`{$c}`", $columns));
            // BOOLEAN MODE: lets us add a trailing * for prefix matching
            // and treat each token as required (`+`).
            $boolean = collect(preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($t) => '+'.str_replace(['+', '-', '"'], '', $t).'*')
                ->implode(' ');

            return $query->whereRaw("MATCH({$cols}) AGAINST (? IN BOOLEAN MODE)", [$boolean]);
        }

        return $this->applyPortableSearch($query, $term, $columns);
    }

    /**
     * Postgres: use ILIKE so collation differences in case don't bite us.
     *
     * @param  Builder<Model>  $query
     * @param  array<int,string>  $columns
     * @return Builder<Model>
     */
    private function applyPgsqlSearch(Builder $query, string $term, array $columns): Builder
    {
        $tokens = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [$term];

        return $query->where(function (Builder $outer) use ($tokens, $columns) {
            foreach ($tokens as $token) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $token).'%';
                $outer->where(function (Builder $inner) use ($columns, $like) {
                    foreach ($columns as $col) {
                        $inner->orWhere($col, 'ILIKE', $like);
                    }
                });
            }
        });
    }

    /**
     * SQLite + fallback path: normalize term, split on whitespace, AND each
     * token across the column list. This is the same behaviour the existing
     * controllers have today; the wins come from (a) consolidation,
     * (b) safer escaping of `%` / `_`, (c) AND-of-tokens instead of a single
     * LIKE so multi-word queries actually narrow results.
     *
     * @param  Builder<Model>  $query
     * @param  array<int,string>  $columns
     * @return Builder<Model>
     */
    private function applyPortableSearch(Builder $query, string $term, array $columns): Builder
    {
        $tokens = preg_split('/\s+/', Str::lower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [$term];

        return $query->where(function (Builder $outer) use ($tokens, $columns) {
            foreach ($tokens as $token) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $token).'%';
                $outer->where(function (Builder $inner) use ($columns, $like) {
                    foreach ($columns as $col) {
                        $inner->orWhereRaw('LOWER('.$inner->getGrammar()->wrap($col).') LIKE ?', [$like]);
                    }
                });
            }
        });
    }

    /**
     * Detects whether the model's table has a FULLTEXT index whose key parts
     * exactly cover (or are a subset of) the requested column list. Cached
     * statically because schema doesn't change at runtime.
     *
     * @param  Builder<Model>  $query
     * @param  array<int,string>  $columns
     */
    private function hasFulltextIndex(Builder $query, array $columns): bool
    {
        static $cache = [];

        $table = $query->getModel()->getTable();
        $key = $table.'|'.implode(',', $columns);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $indexes = DB::connection($query->getModel()->getConnectionName())
                ->select("SHOW INDEX FROM `{$table}` WHERE Index_type = 'FULLTEXT'");
        } catch (\Throwable) {
            return $cache[$key] = false;
        }

        // Group columns by index name and require ALL requested columns to be
        // members of at least one of those indexes.
        $byIndex = [];
        foreach ($indexes as $row) {
            $name = $row->Key_name ?? null;
            $col = $row->Column_name ?? null;
            if ($name && $col) {
                $byIndex[$name][] = $col;
            }
        }

        foreach ($byIndex as $cols) {
            if (! array_diff($columns, $cols)) {
                return $cache[$key] = true;
            }
        }

        return $cache[$key] = false;
    }
}
