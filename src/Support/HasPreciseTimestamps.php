<?php

namespace Syriable\Messenger\Support;

/**
 * Persists model timestamps with microsecond precision.
 *
 * The clear/read/reply visibility boundaries compare timestamps strictly, so
 * second-resolution storage would wrongly hide a message created in the same
 * wall-clock second as a clear. Storing microseconds keeps those boundaries
 * correct. Pairs with `timestamp(..., 6)` columns in the migrations.
 */
trait HasPreciseTimestamps
{
    /**
     * The storage format for the model's date columns (microsecond precision).
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }
}
