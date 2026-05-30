<?php

/**
 * Parallel-stress worker (invoked by scripts/parallel-stress.php).
 *
 * Boots the app against the shared SQLite file (without rebuilding the schema)
 * and sends a single message from the user to the agent, identified by the
 * keys passed as arguments. Exits 0 on success, 1 on failure — the orchestrator
 * aggregates results.
 *
 * Usage:  php parallel-stress-worker.php <db-path> <user-id> <agent-id> <n>
 */

use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\Agent;
use Syriable\Messenger\Tests\Models\User;

require __DIR__.'/bootstrap.php';

[$script, $database, $userId, $agentId, $n] = $argv + [null, null, null, null, '0'];

$app = messenger_qa_boot($database, fresh: false);

$user = User::findOrFail($userId);
$agent = Agent::findOrFail($agentId);

// SQLite serialises writers (one at a time), so under multi-process contention
// a send can hit "database is locked" even with WAL + busy_timeout. That is a
// SQLite constraint, not a package issue — a real SQLite-backed app retries the
// same way. (Point bootstrap.php at MySQL/Postgres to avoid this entirely.)
$attempts = 0;

while (true) {
    try {
        Messenger::send($user, $agent, "worker {$n}");
        exit(0);
    } catch (Throwable $e) {
        $locked = str_contains($e->getMessage(), 'database is locked')
            || str_contains($e->getMessage(), 'database table is locked');

        if (! $locked || ++$attempts > 50) {
            fwrite(STDERR, "worker {$n} failed: ".$e->getMessage()."\n");
            exit(1);
        }

        // Brief randomised backoff before retrying.
        usleep(random_int(2000, 20000));
    }
}
