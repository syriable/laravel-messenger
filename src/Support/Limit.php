<?php

namespace Syriable\Messenger\Support;

/**
 * Normalises a user-supplied query limit.
 *
 * A null/absent limit means "no limit". Any provided value is clamped to a
 * minimum of 1, so a zero or negative limit can never silently disable the
 * LIMIT clause (which would return the entire unbounded result set).
 */
class Limit
{
    public static function normalize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return max(1, (int) $value);
    }
}
