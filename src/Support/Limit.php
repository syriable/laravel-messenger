<?php

namespace Syriable\Messenger\Support;

/**
 * Normalises a user-supplied query limit.
 *
 * A null/absent limit means "no limit". Any provided value is clamped to a
 * minimum of 1, so a zero or negative limit can never silently disable the
 * LIMIT clause (which would return the entire unbounded result set).
 *
 * An optional $max applies a ceiling: when set, an absent limit falls back to
 * the ceiling and any larger value is capped to it. This lets the host enforce
 * a hard page size so an unbounded read can never load an entire history into
 * memory in production (#audit P1).
 */
class Limit
{
    public static function normalize(mixed $value, ?int $max = null): ?int
    {
        $max = ($max !== null && $max > 0) ? $max : null;

        if ($value === null) {
            return $max;
        }

        $value = max(1, (int) $value);

        return $max !== null ? min($value, $max) : $value;
    }
}
