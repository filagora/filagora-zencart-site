<?php

namespace App\Search;

use Carbon\CarbonInterface;

/**
 * The scoring the old site applied to the engine's candidates before showing the top few: a field
 * scores by how whole-word and how close to the start each query word sits in it, weighted by the
 * field, and a thread gains a bonus for recent activity. Kept as it was so results order the same.
 */
final class Relevance
{
    /**
     * @param  list<string>  $tokens  lower-cased query words
     */
    public static function score(string $text, array $tokens, int $weight): int
    {
        if ($text === '' || $tokens === []) {
            return 0;
        }

        $normalized = mb_strtolower($text);
        // Padded so a word at the very start or end of the field counts as a whole word.
        $padded = ' '.$normalized.' ';
        $score = 0;

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if ($normalized === $token) {
                $score += 8 * $weight;

                continue;
            }

            if (str_starts_with($normalized, $token)) {
                $score += 6 * $weight;
            }

            if (str_contains($padded, ' '.$token.' ')) {
                $score += 5 * $weight;

                continue;
            }

            if (str_contains($normalized, $token)) {
                $score += 3 * $weight;
            }
        }

        return $score;
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $query): array
    {
        $tokens = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : array_values(array_unique($tokens));
    }

    public static function freshnessBonus(?CarbonInterface $lastActivity): int
    {
        if ($lastActivity === null || $lastActivity->getTimestamp() <= 0) {
            return 0;
        }

        $ageInDays = max(0, $lastActivity->diffInDays(now()));

        return match (true) {
            $ageInDays <= 30 => 60,
            $ageInDays <= 180 => 45,
            $ageInDays <= 365 => 30,
            $ageInDays <= 365 * 3 => 20,
            $ageInDays <= 365 * 10 => 10,
            default => 0,
        };
    }
}
