<?php

/**
 * Multi-process first-message race harness.
 *
 * Spawns N separate PHP processes that all send the very first message between
 * the same user/agent pair at the same time, against one shared file-backed
 * SQLite database (WAL + busy_timeout). This exercises the lazy-creation race
 * under real OS-level parallelism — beyond what an in-process DB::transaction
 * loop can show — and verifies that exactly one conversation is created and no
 * message is lost.
 *
 * Run:  php scripts/parallel-stress.php [workers]   (default 12)
 *
 * For production-like contention, point the connection at MySQL/Postgres in
 * scripts/bootstrap.php instead of file SQLite.
 */

use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Tests\Models\Agent;
use Syriable\Messenger\Tests\Models\User;

require __DIR__.'/bootstrap.php';

$workers = (int) ($argv[1] ?? 12);
$database = sys_get_temp_dir().'/messenger-stress.sqlite';

echo "Messenger parallel first-message stress ({$workers} workers)\n";
echo str_repeat('=', 48)."\n";

// Build a fresh schema and the participant pair the workers will race on.
$app = messenger_qa_boot($database, fresh: true);
$user = User::create(['name' => 'Race User']);
$agent = Agent::create(['name' => 'Race Agent']);

// Launch all workers as close together as possible.
$worker = escapeshellarg(__DIR__.'/parallel-stress-worker.php');
$php = escapeshellarg(PHP_BINARY);
$procs = [];

for ($n = 0; $n < $workers; $n++) {
    $cmd = "{$php} {$worker} ".escapeshellarg($database)." {$user->id} {$agent->id} {$n} 2>&1";
    $procs[$n] = popen($cmd, 'r');
}

$failures = 0;
foreach ($procs as $n => $proc) {
    $output = stream_get_contents($proc);
    $code = pclose($proc);

    if ($code !== 0) {
        $failures++;
        echo "  \033[31mworker {$n} exited {$code}\033[0m ".trim($output)."\n";
    }
}

// Re-open to inspect the final state.
$app = messenger_qa_boot($database, fresh: false);

$conversations = Conversation::query()->count();
$messages = Message::query()->count();

echo "\nResults\n-------\n";
qa_assert("all {$workers} workers succeeded", $failures === 0);
qa_assert('exactly one conversation exists', $conversations === 1);
qa_assert("every message persisted ({$messages}/{$workers})", $messages === $workers);

$conversation = Messenger::between($user, $agent);
qa_assert('conversation has exactly two participants',
    $conversation !== null && $conversation->participants()->count() === 2);

exit(qa_summary());
