<?php

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\Branch;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $threads = MessageThread::query()
            ->whereHas('participants', fn ($query) => $query->where('users.id', $userId))
            ->with(['participants:id,name', 'latestMessage'])
            ->withMax('messages', 'created_at')
            ->withCount('participants')
            ->orderByDesc('messages_max_created_at')
            ->paginate(20)
            ->withQueryString();

        $this->setUnreadCounts($threads->getCollection(), $userId);

        return view('messages.index', [
            'threads' => $threads,
            'canAnnounce' => $request->user()->can('messages.announce'),
        ]);
    }

    public function compose(Request $request): View
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $recipients = User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->when(! $isSuperAdmin, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->with('branch:id,name,branch_number')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'branch_id']);
        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $isSuperAdmin, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('branch_number')
            ->get();
        $roles = Role::query()->where('name', '!=', 'Super Admin')->orderBy('name')->pluck('name');

        return view('messages.compose', [
            'recipients' => $recipients,
            'branches' => $branches,
            'roles' => $roles,
            'canAnnounce' => $user->can('messages.announce'),
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['direct', 'announcement'])],
            'subject' => ['nullable', 'required_if:type,announcement', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'recipient_id' => [
                'nullable',
                'required_if:type,direct',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'audience' => [
                'nullable',
                'required_if:type,announcement',
                Rule::in(['branch', 'role']),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'role' => [
                'nullable',
                'required_if:audience,role',
                'string',
                Rule::exists('roles', 'name'),
            ],
        ]);

        $sender = $request->user();
        $isSuperAdmin = $sender->hasRole('Super Admin');

        if ($data['type'] === 'direct') {
            $recipient = User::query()
                ->where('is_active', true)
                ->whereKey((int) $data['recipient_id'])
                ->when(! $isSuperAdmin, fn ($query) => $query->where('branch_id', $sender->branch_id))
                ->firstOrFail();

            if ((int) $recipient->id === (int) $sender->id) {
                throw ValidationException::withMessages([
                    'recipient_id' => __('You cannot send a message to yourself.'),
                ]);
            }

            $recipients = collect([$recipient]);
            $branchId = $recipient->branch_id;
            $type = 'direct';
        } else {
            abort_unless($sender->can('messages.announce'), 403);

            $branchId = $isSuperAdmin
                ? (int) ($data['branch_id'] ?? 0)
                : (int) $sender->branch_id;
            if (! $branchId) {
                throw ValidationException::withMessages([
                    'branch_id' => __('Select an active branch for the announcement.'),
                ]);
            }
            if (! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'branch_id' => __('Select an active branch for the announcement.'),
                ]);
            }

            if (($data['audience'] ?? null) === 'role' && ($data['role'] ?? null) === 'Super Admin') {
                throw ValidationException::withMessages([
                    'role' => __('Choose a branch role for the announcement.'),
                ]);
            }

            $recipients = User::query()
                ->where('is_active', true)
                ->where('branch_id', $branchId)
                ->whereKeyNot($sender->id)
                ->when(
                    ($data['audience'] ?? null) === 'role',
                    fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $data['role']))
                )
                ->get();

            if ($recipients->isEmpty()) {
                throw ValidationException::withMessages([
                    'audience' => __('No active users match this announcement audience.'),
                ]);
            }

            $type = 'announcement';
        }

        $thread = DB::transaction(function () use ($data, $sender, $recipients, $branchId, $type): MessageThread {
            $now = now();
            $thread = $type === 'direct'
                ? MessageThread::query()
                    ->where('type', 'direct')
                    ->whereHas('participants', fn ($query) => $query->where('users.id', $sender->id))
                    ->whereHas('participants', fn ($query) => $query->whereIn('users.id', $recipients->pluck('id')))
                    ->orderByDesc('id')
                    ->first()
                : null;

            if (! $thread) {
                $thread = MessageThread::create([
                    'type' => $type,
                    'subject' => $data['subject'] ?? $recipients->first()->name,
                    'branch_id' => $branchId,
                    'created_by' => $sender->id,
                ]);

                $participants = collect([$sender])->concat($recipients)->unique('id');
                $thread->participants()->attach($participants->mapWithKeys(fn (User $participant) => [
                    $participant->id => ['last_read_at' => $participant->id === $sender->id ? $now : null],
                ])->all());
            } else {
                $thread->participants()->updateExistingPivot($sender->id, ['last_read_at' => $now]);
            }

            $message = $thread->messages()->create([
                'sender_id' => $sender->id,
                'sender_name' => $sender->name,
                'body' => $data['body'],
            ]);

            $this->notifyRecipients($thread, $message, $sender, $recipients);

            return $thread;
        });
        $this->broadcast(new MessageSent($thread->latestMessage()->firstOrFail()));

        return redirect()->route('messages.show', $thread)->with('success', __('Message sent.'));
    }

    public function show(Request $request, int $threadId): View
    {
        $user = $request->user();
        $thread = $this->visibleThread($user, $threadId);
        $this->markThreadRead($thread, $user);

        $thread->load(['participants:id,name,branch_id', 'messages']);
        $threads = MessageThread::query()
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
            ->with(['participants:id,name', 'latestMessage'])
            ->withMax('messages', 'created_at')
            ->withCount('participants')
            ->orderByDesc('messages_max_created_at')
            ->limit(30)
            ->get();

        if (! $threads->contains(fn (MessageThread $candidate): bool => (int) $candidate->id === $threadId)) {
            $threads->push(MessageThread::query()
                ->whereKey($threadId)
                ->with(['participants:id,name', 'latestMessage'])
                ->withMax('messages', 'created_at')
                ->withCount('participants')
                ->firstOrFail());
        }
        $threads = $threads->sortByDesc('messages_max_created_at')->values();
        $this->setUnreadCounts($threads, (int) $user->id);

        return view('messages.show', [
            'thread' => $thread,
            'threads' => $threads,
            'canReply' => $thread->type === 'direct',
        ]);
    }

    public function markRead(Request $request, int $threadId): JsonResponse
    {
        $user = $request->user();
        $thread = $this->visibleThread($user, $threadId);
        $this->markThreadRead($thread, $user);

        return response()->json(['read' => true]);
    }

    public function reply(Request $request, int $threadId): RedirectResponse|JsonResponse
    {
        $thread = $this->visibleThread($request->user(), $threadId);
        abort_unless($thread->type === 'direct', 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $sender = $request->user();
        $recipients = $thread->participants()
            ->where('users.id', '!=', $sender->id)
            ->where('users.is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'body' => __('There is no active recipient for this conversation.'),
            ]);
        }

        $message = DB::transaction(function () use ($thread, $sender, $recipients, $data): Message {
            $message = $thread->messages()->create([
                'sender_id' => $sender->id,
                'sender_name' => $sender->name,
                'body' => $data['body'],
            ]);

            $this->notifyRecipients($thread, $message, $sender, $recipients);

            return $message;
        });
        $this->broadcast(new MessageSent($message));

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->messagePayload($message)], 201);
        }

        return redirect()->route('messages.show', $thread)->with('success', __('Reply sent.'));
    }

    private function visibleThread(User $user, int $threadId): MessageThread
    {
        return MessageThread::query()
            ->whereKey($threadId)
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
            ->firstOrFail();
    }

    private function setUnreadCounts(Collection $threads, int $userId): void
    {
        $threadIds = $threads->pluck('id');
        $unreadCounts = collect();

        if ($threadIds->isNotEmpty()) {
            $unreadCounts = DB::table('messages')
                ->join('message_thread_participants', function (JoinClause $join) use ($userId): void {
                    $join->on('message_thread_participants.message_thread_id', '=', 'messages.message_thread_id')
                        ->where('message_thread_participants.user_id', '=', $userId);
                })
                ->whereIn('messages.message_thread_id', $threadIds)
                ->where(function ($query) use ($userId): void {
                    $query->whereNull('messages.sender_id')
                        ->orWhere('messages.sender_id', '!=', $userId);
                })
                ->where(function ($query): void {
                    $query->whereNull('message_thread_participants.last_read_at')
                        ->orWhereColumn('messages.created_at', '>', 'message_thread_participants.last_read_at');
                })
                ->selectRaw('messages.message_thread_id, COUNT(*) AS unread_count')
                ->groupBy('messages.message_thread_id')
                ->pluck('unread_count', 'messages.message_thread_id');
        }

        foreach ($threads as $thread) {
            $thread->setAttribute('unread_count', (int) $unreadCounts->get($thread->id, 0));
        }
    }

    private function markThreadRead(MessageThread $thread, User $user): void
    {
        $readAt = now();
        $thread->participants()->updateExistingPivot($user->id, ['last_read_at' => $readAt]);

        $user->unreadNotifications()
            ->where('type', OperationalNotification::class)
            ->get()
            ->each(function ($notification) use ($thread): void {
                if ((int) ($notification->data['message_thread_id'] ?? 0) === (int) $thread->id) {
                    $notification->markAsRead();
                }
            });

        if ($thread->type === 'direct') {
            $this->broadcast(new MessageRead(
                (int) $thread->id,
                (int) $user->id,
                $user->name,
                $readAt->toIso8601String(),
            ));
        }
    }

    private function broadcast(MessageSent|MessageRead $event): void
    {
        try {
            event($event);
        } catch (BroadcastException $exception) {
            Log::warning('A real-time messaging event could not be delivered.', [
                'event' => $event::class,
                'thread_id' => $event instanceof MessageSent
                    ? $event->message->message_thread_id
                    : $event->threadId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'thread_id' => $message->message_thread_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender_name,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    private function notifyRecipients(
        MessageThread $thread,
        Message $message,
        User $sender,
        Collection $recipients
    ): void {
        $title = $thread->type === 'announcement'
            ? __('New branch announcement from :name', ['name' => $sender->name])
            : __('New message from :name', ['name' => $sender->name]);

        foreach ($recipients as $recipient) {
            $recipient->notify(new OperationalNotification([
                'event' => 'message_received',
                'category' => 'messages',
                'title' => $title,
                'title_key' => $thread->type === 'announcement'
                    ? 'New branch announcement from :name'
                    : 'New message from :name',
                'title_params' => ['name' => $sender->name],
                'message' => Str::limit($message->body, 140),
                'url' => route('messages.show', $thread),
                'icon' => 'inbox',
                'message_thread_id' => $thread->id,
            ]));
        }
    }
}
