<?php

namespace App\Support;

/**
 * Case-insensitive "contains" search that behaves the same everywhere.
 *
 * The app runs on PostgreSQL and its test suite runs on SQLite. `ilike` is a
 * PostgreSQL word: the identical query is a syntax error in SQLite, so a search
 * that works on the deployed site can fail the moment it is tested - or worse,
 * pass a test that never exercised the term. LOWER(column) LIKE ? means the
 * same thing to both databases.
 *
 * The columns are always written in the calling code (never taken from a
 * request), so the raw expression cannot carry anything a user typed; only the
 * search term is a bound parameter.
 */
class Search
{
    /**
     * Add "any of these columns contains the term" to a query.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, string>  $columns  qualified column names, e.g. 'e.Full_Name'
     */
    public static function contains($query, array $columns, string $term): void
    {
        $term = trim($term);

        if ($term === '' || empty($columns)) {
            return;
        }

        $needle = '%' . mb_strtolower($term) . '%';

        $query->where(function ($q) use ($columns, $needle) {
            foreach ($columns as $column) {
                $q->orWhereRaw('LOWER(' . $column . ') LIKE ?', [$needle]);
            }
        });
    }
}
