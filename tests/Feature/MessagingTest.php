<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Branch;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::default() ?? Branch::create([
            'name' => 'Main Branch',
            'branch_number' => '001',
            'is_active' => true,
        ]);
        $this->otherBranch = Branch::create([
            'name' => 'North Store',
            'branch_number' => '002',
            'is_active' => true,
        ]);
    }

    public function test_direct_messages_are_private_and_replies_notify_the_other_participant(): void
    {
        config(['queue.default' => 'database']);

        $sender = $this->userInBranch($this->branch, 'Admin', 'sender@example.test');
        $recipient = $this->userInBranch($this->branch, 'Cashier', 'recipient@example.test');
        $outsider = $this->userInBranch($this->otherBranch, 'Admin', 'outsider@example.test');

        $this->actingAs($sender)
            ->get(route('messages.compose'))
            ->assertOk()
            ->assertSee('recipient@example.test')
            ->assertDontSee('outsider@example.test');

        $this->actingAs($sender)->post(route('messages.store'), [
            'type' => 'direct',
            'recipient_id' => $outsider->id,
            'subject' => 'Cross-branch attempt',
            'body' => 'This message must not be delivered.',
        ])->assertNotFound();

        $this->post(route('messages.store'), [
            'type' => 'direct',
            'recipient_id' => $recipient->id,
            'body' => "Please check aisle 3.\nThanks.",
        ])->assertRedirect();

        $thread = MessageThread::query()->latest('id')->firstOrFail();
        $message = Message::query()->where('message_thread_id', $thread->id)->firstOrFail();
        $event = new MessageSent($message, [(int) $recipient->id], 'direct', $sender->name);
        $this->assertSame(
            ['private-messages.thread.'.$thread->id, 'private-messages.user.'.$recipient->id],
            array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn()),
        );
        $this->assertSame($recipient->name, $thread->subject);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(2, $thread->participants()->count());
        $this->assertSame('message_received', $recipient->unreadNotifications()->firstOrFail()->data['event']);
        $recipient->unreadNotifications()->update(['read_at' => now()]);

        $this->actingAs($recipient)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-messages.thread.'.$thread->id,
        ])->assertOk();
        $this->actingAs($outsider)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-messages.thread.'.$thread->id,
        ])->assertForbidden();
        $this->actingAs($recipient)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-messages.user.'.$recipient->id,
        ])->assertOk();
        $this->actingAs($outsider)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-messages.user.'.$recipient->id,
        ])->assertForbidden();

        $this->actingAs($recipient)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSeeText($sender->name)
            ->assertSee('class="content message-content"', false)
            ->assertSee('data-chat-inbox', false)
            ->assertSee('data-chat-connection', false)
            ->assertSee('aria-label="Messages"', false)
            ->assertSee('class="message-shortcut-count">1</span>', false);

        $this->actingAs($recipient)
            ->get(route('messages.show', $thread))
            ->assertOk()
            ->assertSee('class="content message-content"', false)
            ->assertSee('data-chat-conversation', false)
            ->assertSeeText($sender->name)
            ->assertSeeText('Please check aisle 3.')
            ->assertSeeText('Thanks.');
        $this->assertSame(0, $recipient->fresh()->unreadNotifications()->count());
        $this->assertSame(2, DB::table('jobs')->count());

        $this->actingAs($sender)
            ->get(route('messages.show', $thread))
            ->assertOk()
            ->assertSeeText('Seen');

        $this->actingAs($sender)->post(route('messages.store'), [
            'type' => 'direct',
            'recipient_id' => $recipient->id,
            'subject' => 'New subject is ignored for the existing chat',
            'body' => 'Following up on the stock count.',
        ])->assertRedirect(route('messages.show', $thread));
        $this->assertSame(1, MessageThread::query()->where('type', 'direct')->count());
        $this->assertSame(2, $thread->messages()->count());

        $this->actingAs($recipient)
            ->get(route('messages.show', $thread))
            ->assertOk()
            ->assertSeeText('Following up on the stock count.');

        $this->actingAs($outsider)
            ->get(route('messages.show', $thread))
            ->assertNotFound();

        $this->actingAs($recipient)
            ->postJson(route('messages.reply', $thread), ['body' => 'I will check it now.'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'I will check it now.');

        $this->assertDatabaseHas('messages', [
            'message_thread_id' => $thread->id,
            'sender_id' => $recipient->id,
            'body' => 'I will check it now.',
        ]);
        $this->assertSame(1, $sender->fresh()->unreadNotifications()->count());
    }

    public function test_announcements_can_target_a_branch_role_but_not_other_branches(): void
    {
        Queue::fake();

        $manager = $this->userInBranch($this->branch, 'Manager', 'manager@example.test');
        $cashier = $this->userInBranch($this->branch, 'Cashier', 'cashier@example.test');
        $otherCashier = $this->userInBranch($this->otherBranch, 'Cashier', 'north-cashier@example.test');

        $this->actingAs($manager)->post(route('messages.store'), [
            'type' => 'announcement',
            'audience' => 'role',
            'role' => 'Cashier',
            'subject' => 'Closing checklist',
            'body' => 'Please complete the register checklist before closing.',
        ])->assertRedirect();

        $thread = MessageThread::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $thread->participants()->count());
        $this->assertTrue($thread->participants()->whereKey($cashier->id)->exists());
        $this->assertFalse($thread->participants()->whereKey($otherCashier->id)->exists());
        $this->assertSame(1, $cashier->unreadNotifications()->count());
        $this->assertSame(0, $otherCashier->notifications()->count());

        $this->actingAs($cashier)
            ->get(route('messages.show', $thread))
            ->assertOk()
            ->assertSeeText('This announcement is read-only.');
        $this->post(route('messages.reply', $thread), ['body' => 'Unauthorized reply'])
            ->assertForbidden();

        $this->actingAs($cashier)->post(route('messages.store'), [
            'type' => 'announcement',
            'audience' => 'branch',
            'subject' => 'Unauthorized announcement',
            'body' => 'This should be rejected.',
        ])->assertForbidden();
    }

    public function test_messaging_pages_still_work_when_synchronous_reverb_broadcasts_fail(): void
    {
        config([
            'queue.default' => 'sync',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
            'broadcasting.connections.reverb.client_options' => [
                'connect_timeout' => 1,
                'timeout' => 1,
            ],
        ]);
        Log::spy();

        $sender = $this->userInBranch($this->branch, 'Admin', 'offline-sender@example.test');
        $recipient = $this->userInBranch($this->branch, 'Cashier', 'offline-recipient@example.test');

        $this->actingAs($sender)->post(route('messages.store'), [
            'type' => 'direct',
            'recipient_id' => $recipient->id,
            'body' => 'This message must persist while Reverb is offline.',
        ])->assertRedirect();

        $thread = MessageThread::query()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('messages', [
            'message_thread_id' => $thread->id,
            'body' => 'This message must persist while Reverb is offline.',
        ]);

        $this->actingAs($recipient)
            ->get(route('messages.show', $thread))
            ->assertOk()
            ->assertSeeText('This message must persist while Reverb is offline.');

        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_message_notification_titles_follow_the_viewers_locale(): void
    {
        Queue::fake();

        $sender = $this->userInBranch($this->branch, 'Admin', 'language-sender@example.test');
        $recipient = $this->userInBranch($this->branch, 'Cashier', 'language-recipient@example.test');

        $this->actingAs($sender)->post(route('messages.store'), [
            'type' => 'direct',
            'recipient_id' => $recipient->id,
            'body' => 'Please review this update.',
        ])->assertRedirect();

        $this->actingAs($recipient)->get('/locale/prs')->assertRedirect();
        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('پیام تازه از طرف '.$sender->name);

        $this->get('/locale/ps')->assertRedirect();
        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('د '.$sender->name.' نوی پیغام');
    }

    private function userInBranch(Branch $branch, string $role, string $email): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => $email,
            'is_active' => true,
        ]);
        $user->syncRoles($role);

        return $user;
    }
}
