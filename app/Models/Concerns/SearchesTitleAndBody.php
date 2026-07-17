<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait SearchesTitleAndBody
{
    /**
     * Search title and body. On MySQL a boolean-mode fulltext prefix search is
     * used (backed by the fulltext index); other drivers fall back to LIKE.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        if ($query->getConnection()->getDriverName() === 'mysql') {
            $booleanTerm = collect(preg_split('/\s+/', Str::of($term)->replaceMatches('/[+\-<>~"()*@]/', ' ')))
                ->filter(fn (string $word): bool => mb_strlen($word) >= 3)
                ->map(fn (string $word): string => "{$word}*")
                ->implode(' ');

            if ($booleanTerm !== '') {
                return $query->whereFullText(['title', 'body'], $booleanTerm, ['mode' => 'boolean']);
            }
        }

        return $query->where(function (Builder $innerQuery) use ($term): void {
            $innerQuery
                ->where('title', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%");
        });
    }
}
