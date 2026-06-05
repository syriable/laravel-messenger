<?php

/**
 * Consuming-app integration smoke test.
 *
 * Boots a real Laravel app, installs the package, and exercises the public
 * surface the way a host application would: cross-model morph messaging,
 * spam/unspam, broadcast contract, attachments, and the prune command.
 *
 * Run:  php scripts/integration-qa.php
 */

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Events\Broadcast\MessageSentBroadcast;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\Agent;
use Syriable\Messenger\Tests\Models\User;

require __DIR__.'/bootstrap.php';

$app = messenger_qa_boot(sys_get_temp_dir().'/messenger-qa.sqlite');

echo "Messenger integration QA\n========================\n";

Storage::fake('local');

$user = User::create(['name' => 'Ann']);
$agent = Agent::create(['name' => 'Support']);

// 1. Cross-model morph conversation.
$message = $user->sendMessageTo($agent, 'I need help');
$conversation = Messenger::between($user, $agent);
qa_assert('user<->agent morph conversation created', $conversation !== null);
qa_assert('conversation has two participants', $conversation->participants()->count() === 2);

// 2. Direction independence.
$agent->sendMessageTo($user, 'How can I help?');
qa_assert('agent->user resolves to the same conversation',
    Messenger::between($agent, $user)->id === $conversation->id);

// 3. Unread is participant-specific.
qa_assert('recipient accrues unread', $agent->unreadMessagesCount() >= 1 || $user->unreadMessagesCount() >= 1);

// 4. Spam blocks mutually, then unspam restores.
Messenger::spam($conversation, $agent);
$blocked = false;
try {
    Messenger::send($user, $agent, 'still there?');
} catch (ConversationBlockedException) {
    $blocked = true;
}
qa_assert('spam blocks sending (mutual)', $blocked);
qa_assert('history preserved while spammed', Messenger::messages($conversation, $agent)->count() >= 2);
Messenger::unspam($conversation, $agent);
qa_assert('unspam restores sending', Messenger::send($user, $agent, 'back')->body === 'back');

// 5. Broadcast contract.
$broadcast = new MessageSentBroadcast(Messenger::messages($conversation, $user)->last());
$channel = $broadcast->broadcastOn()[0];
qa_assert('broadcastAs is message.sent', $broadcast->broadcastAs() === 'message.sent');
qa_assert('broadcast channel uses the configured prefix',
    str_contains($channel->name, 'messenger.conversation.'));
qa_assert('broadcast payload has the documented keys',
    array_keys($broadcast->broadcastWith()) === ['id', 'conversation_id', 'sender_type', 'sender_id', 'body', 'reply_to_id', 'created_at', 'has_attachments', 'attachments']);

// 6. Attachments + prune.
$withFile = Messenger::send($user, $agent, ['attachments' => [UploadedFile::fake()->image('a.jpg')]]);
$path = $withFile->attachments->first()->path;
qa_assert('attachment stored on disk', Storage::disk('local')->exists($path));

$directory = config('messenger.attachments.directory');
Storage::disk('local')->put($directory.'/orphan.jpg', 'junk');
$pruned = Messenger::pruneAttachments();
qa_assert('prune removes the orphan only', $pruned->count() === 1 && Storage::disk('local')->exists($path));

exit(qa_summary());
