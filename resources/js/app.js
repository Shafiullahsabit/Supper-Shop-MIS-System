//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

const inbox = document.querySelector('[data-chat-inbox]');
const conversation = document.querySelector('[data-chat-conversation]');
const chatRoot = conversation || inbox;

if (chatRoot && window.Echo) {
    const currentUserId = Number(chatRoot.dataset.userId);
    const subscribedThreads = new Set();
    const handledMessageIds = new Set();
    const messageList = conversation?.querySelector('[data-message-list]');

    const updateConnection = (state) => {
        const status = chatRoot.querySelector('[data-chat-connection]');
        if (!status) return;
        status.textContent = chatRoot.dataset[`label${state}`] || state;
        status.dataset.state = state.toLowerCase();
    };

    const formatTime = (date) => new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);

    const incrementNotificationBadges = () => {
        const bell = document.querySelector('.notification-bell');
        const navigationLink = [...document.querySelectorAll('.nav-link')].find((link) => link.href.endsWith('/notifications'));

        [bell, navigationLink].forEach((link) => {
            if (!link) return;
            let badge = link.querySelector('.notification-bell-count, .nav-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = link === bell ? 'notification-bell-count' : 'nav-badge';
                link.append(badge);
            }
            badge.textContent = String((Number(badge.textContent) || 0) + 1);
        });
    };

    const updateMessageBadges = (change) => {
        const navigation = [
            document.querySelector('.message-shortcut'),
            [...document.querySelectorAll('.nav-link')].find((link) => link.href.endsWith('/messages')),
        ];

        navigation.forEach((link) => {
            if (!link) return;
            let badge = link.querySelector('.message-shortcut-count, .nav-badge');
            const count = Math.max(0, (Number.parseInt(badge?.textContent || '0', 10) || 0) + change);
            if (count === 0) {
                badge?.remove();
                return;
            }
            if (!badge) {
                badge = document.createElement('span');
                badge.className = link.classList.contains('message-shortcut')
                    ? 'message-shortcut-count'
                    : 'nav-badge';
                link.append(badge);
            }
            badge.textContent = count > 99 ? '99+' : String(count);
        });
    };

    const createThreadRow = (message) => {
        const list = chatRoot.querySelector('[data-chat-list]');
        if (!list) return null;

        const threadId = String(message.thread_id);
        const threadName = message.thread_type === 'announcement'
            ? message.thread_title
            : message.sender_name;
        const row = document.createElement('a');
        row.className = 'chat-thread-row';
        row.href = `${chatRoot.dataset.messagesUrl}/${encodeURIComponent(threadId)}`;
        row.dataset.threadRow = '';
        row.dataset.threadId = threadId;
        row.dataset.threadUrl = row.href;
        row.dataset.threadName = threadName;
        row.dataset.lastMessageTime = message.created_at;
        row.setAttribute('aria-current', 'false');

        const avatar = document.createElement('span');
        avatar.className = `chat-avatar${message.thread_type === 'announcement' ? ' is-announcement' : ''}`;
        avatar.textContent = Array.from(threadName || '?')[0].toLocaleUpperCase();

        const details = document.createElement('span');
        details.className = 'chat-thread-details';
        const topLine = document.createElement('span');
        topLine.className = 'chat-thread-topline';
        const title = document.createElement('strong');
        title.dataset.threadTitle = '';
        title.textContent = threadName;
        const time = document.createElement('time');
        time.dataset.threadTime = '';
        time.textContent = formatTime(new Date(message.created_at));
        topLine.append(title, time);

        const bottomLine = document.createElement('span');
        bottomLine.className = 'chat-thread-bottomline';
        const preview = document.createElement('span');
        preview.className = 'chat-thread-preview';
        preview.dataset.threadPreview = '';
        const unread = document.createElement('span');
        unread.className = 'chat-unread-count';
        unread.dataset.threadUnread = '';
        unread.hidden = true;
        bottomLine.append(preview, unread);
        details.append(topLine, bottomLine);
        row.append(avatar, details);
        list.querySelector('.chat-list-empty')?.remove();
        list.prepend(row);

        return row;
    };

    const changeThreadUnread = (row, nextCount) => {
        const badge = row?.querySelector('[data-thread-unread]');
        if (!badge) return;

        const previousCount = Number(badge.textContent) || 0;
        const count = Math.max(0, nextCount);
        badge.textContent = String(count);
        badge.hidden = count === 0;
        updateMessageBadges(count - previousCount);
    };

    const updateThreadRow = (message) => {
        let row = chatRoot.querySelector(`[data-thread-row][data-thread-id="${message.thread_id}"]`);
        if (!row) row = createThreadRow(message);
        if (!row) return;

        const preview = row.querySelector('[data-thread-preview]');
        const sender = Number(message.sender_id) === currentUserId
            ? chatRoot.dataset.labelYou
            : message.sender_name;
        if (preview) preview.textContent = `${sender}: ${message.body}`.slice(0, 62);

        const date = new Date(message.created_at);
        const time = row.querySelector('[data-thread-time]');
        if (time) time.textContent = formatTime(date);
        row.dataset.lastMessageTime = date.toISOString();

        if (Number(message.sender_id) !== currentUserId && row.getAttribute('aria-current') !== 'page') {
            const badge = row.querySelector('[data-thread-unread]');
            if (badge) {
                changeThreadUnread(row, (Number(badge.textContent) || 0) + 1);
            }
            incrementNotificationBadges();
        }

        const list = row.parentElement;
        if (list) {
            [...list.querySelectorAll('[data-thread-row]')]
                .sort((a, b) => new Date(b.dataset.lastMessageTime || 0) - new Date(a.dataset.lastMessageTime || 0))
                .forEach((item) => list.append(item));
        }
    };

    const appendMessage = (message) => {
        if (!messageList || messageList.querySelector(`[data-message-id="${message.id}"]`)) return;

        const ownMessage = Number(message.sender_id) === currentUserId;
        const article = document.createElement('article');
        article.className = `chat-message${ownMessage ? ' is-own' : ''}`;
        article.dataset.messageId = message.id;
        article.dataset.senderId = message.sender_id;
        article.dataset.createdAt = message.created_at;

        if (!ownMessage) {
            const sender = document.createElement('strong');
            sender.className = 'chat-message-sender';
            sender.textContent = message.sender_name;
            article.append(sender);
        }

        const body = document.createElement('p');
        body.className = 'chat-message-body';
        body.textContent = message.body;
        article.append(body);

        const meta = document.createElement('div');
        meta.className = 'chat-message-meta';
        const time = document.createElement('time');
        time.dateTime = message.created_at;
        time.textContent = formatTime(new Date(message.created_at));
        meta.append(time);

        if (ownMessage && conversation.dataset.threadType === 'direct') {
            const status = document.createElement('span');
            status.className = 'chat-message-status';
            status.dataset.messageStatus = '';
            status.textContent = chatRoot.dataset.labelSent;
            meta.append(status);
        }

        article.append(meta);
        messageList.append(article);
        messageList.scrollTop = messageList.scrollHeight;
    };

    const markConversationRead = async () => {
        if (!conversation || conversation.dataset.threadType !== 'direct') return;

        const response = await fetch(conversation.dataset.readUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) throw new Error('Could not update the read receipt.');
        const row = chatRoot.querySelector(`[data-thread-row][data-thread-id="${conversation.dataset.threadId}"]`);
        changeThreadUnread(row, 0);
    };

    const updateReadReceipt = (event) => {
        if (!conversation || Number(event.user_id) === currentUserId) return;

        messageList.querySelectorAll('.chat-message.is-own').forEach((message) => {
            if (new Date(message.dataset.createdAt) <= new Date(event.read_at)) {
                const status = message.querySelector('[data-message-status]');
                if (status) status.textContent = chatRoot.dataset.labelSeen;
            }
        });
    };

    const handleMessage = (message) => {
        const messageId = String(message.id);
        if (handledMessageIds.has(messageId)) return;
        handledMessageIds.add(messageId);
        if (handledMessageIds.size > 300) {
            handledMessageIds.delete(handledMessageIds.values().next().value);
        }

        updateThreadRow(message);
        if (!conversation || Number(message.thread_id) !== Number(conversation.dataset.threadId)) return;

        appendMessage(message);
        if (Number(message.sender_id) !== currentUserId) {
            markConversationRead().catch((error) => console.error(error));
        }
    };

    document.querySelectorAll('[data-thread-row]').forEach((row) => {
        const threadId = row.dataset.threadId;
        if (subscribedThreads.has(threadId)) return;
        subscribedThreads.add(threadId);

        window.Echo.private(`messages.thread.${threadId}`)
            .listen('.message.sent', handleMessage)
            .listen('.message.read', updateReadReceipt);
    });

    window.Echo.private(`messages.user.${currentUserId}`)
        .listen('.message.sent', handleMessage);

    const search = document.querySelector('[data-chat-search]');
    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase();
        document.querySelectorAll('[data-thread-row]').forEach((row) => {
            row.hidden = !row.dataset.threadName.toLocaleLowerCase().includes(query);
        });
    });

    if (conversation) {
        messageList.scrollTop = messageList.scrollHeight;

        const form = conversation.querySelector('[data-message-form]');
        if (form) {
            const input = conversation.querySelector('[data-message-input]');
            const error = conversation.querySelector('[data-compose-error]');
            const sendButton = form.querySelector('button[type="submit"]');

            const showError = (message) => {
                error.textContent = message;
                error.hidden = false;
            };

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const body = input.value.trim();
                if (!body) return;

                error.hidden = true;
                sendButton.disabled = true;
                try {
                    const response = await fetch(conversation.dataset.replyUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ body }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || chatRoot.dataset.labelSendError);
                    }

                    input.value = '';
                    appendMessage(result.message);
                    input.focus();
                } catch (sendError) {
                    showError(sendError.message);
                } finally {
                    sendButton.disabled = false;
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form.requestSubmit();
                }
            });
        }

    }

    const pusherConnection = window.Echo.connector.pusher.connection;
    pusherConnection.bind('connected', () => updateConnection('Live'));
    pusherConnection.bind('connecting', () => updateConnection('Connecting'));
    pusherConnection.bind('unavailable', () => updateConnection('Offline'));
    pusherConnection.bind('disconnected', () => updateConnection('Offline'));
}
