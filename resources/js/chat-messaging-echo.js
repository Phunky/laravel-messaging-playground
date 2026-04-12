/**
 * Subscribe to private messaging channels via Laravel Echo + Reverb.
 * Dispatches Livewire events consumed by chat.message-thread and conversation-list.
 *
 * Laravel broadcasts using the full event class name (see BroadcastEvent) unless
 * broadcastAs() is defined — so we must listen with a leading dot + FQCN, not ".MessageSent".
 */
const Ev = {
    MessageSent: '.Phunky\\LaravelMessaging\\Events\\MessageSent',
    MessageEdited: '.Phunky\\LaravelMessaging\\Events\\MessageEdited',
    MessageDeleted: '.Phunky\\LaravelMessaging\\Events\\MessageDeleted',
    AllMessagesRead: '.Phunky\\LaravelMessaging\\Events\\AllMessagesRead',
};

let subscribedConversationId = null;

let subscribedChatUserId = null;

function dispatchToLivewire(name, payload) {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.dispatch !== 'function') {
        return;
    }

    window.Livewire.dispatch(name, payload);
}

function channelPrefix() {
    return import.meta.env.VITE_MESSAGING_CHANNEL_PREFIX || 'messaging';
}

function leaveChannel() {
    if (!window.Echo || subscribedConversationId === null) {
        subscribedConversationId = null;

        return;
    }

    const name = `${channelPrefix()}.conversation.${subscribedConversationId}`;
    window.Echo.leave(name);
    subscribedConversationId = null;
}

/**
 * @param {number|null|undefined} conversationId
 */
export function subscribeToMessagingConversation(conversationId) {
    if (!window.Echo) {
        return;
    }

    const id = conversationId === null || conversationId === undefined ? null : Number(conversationId);

    if (id === null || Number.isNaN(id)) {
        leaveChannel();

        return;
    }

    if (subscribedConversationId === id) {
        return;
    }

    leaveChannel();

    const prefix = channelPrefix();
    const channelName = `${prefix}.conversation.${id}`;

    window.Echo.private(channelName)
        .listen(Ev.MessageSent, (payload) => {
            const message = payload?.message;
            const conversation = payload?.conversation;
            const cid = message?.conversation_id ?? conversation?.id;
            const mid = message?.id;
            if (cid === undefined || mid === undefined) {
                return;
            }

            dispatchToLivewire('messaging-remote-message-sent', {
                conversationId: Number(cid),
                messageId: Number(mid),
            });
            dispatchToLivewire('conversation-updated', {});
        })
        .listen(Ev.MessageEdited, (payload) => {
            const message = payload?.message;
            if (!message?.id || message.conversation_id === undefined) {
                return;
            }

            dispatchToLivewire('messaging-remote-message-edited', {
                conversationId: Number(message.conversation_id),
                messageId: Number(message.id),
            });
            dispatchToLivewire('conversation-updated', {});
        })
        .listen(Ev.MessageDeleted, (payload) => {
            const message = payload?.message;
            const conversation = payload?.conversation;
            const cid = message?.conversation_id ?? conversation?.id;
            const mid = message?.id;
            if (cid === undefined || mid === undefined) {
                return;
            }

            dispatchToLivewire('messaging-remote-message-deleted', {
                conversationId: Number(cid),
                messageId: Number(mid),
            });
            dispatchToLivewire('conversation-updated', {});
        })
        .listen(Ev.AllMessagesRead, (payload) => {
            const conversation = payload?.conversation;
            if (!conversation?.id) {
                return;
            }

            dispatchToLivewire('conversation-updated', {});
        });

    subscribedConversationId = id;
}

const EvInbox = {
    InboxUpdated: '.messaging.inbox.updated',
};

/**
 * Subscribe to the signed-in user's private channel so the conversation list
 * refreshes when any of their threads change — not only the conversation Echo
 * is currently subscribed to for the open thread.
 */
export function subscribeToUserInbox() {
    if (!window.Echo) {
        return;
    }

    const meta = document.querySelector('meta[name="chat-user-id"]');
    const raw = meta?.getAttribute('content');
    const userId = raw !== null && raw !== '' ? Number(raw) : NaN;

    if (Number.isNaN(userId)) {
        return;
    }

    if (subscribedChatUserId === userId) {
        return;
    }

    if (subscribedChatUserId !== null) {
        window.Echo.leave(`App.Models.User.${subscribedChatUserId}`);
        subscribedChatUserId = null;
    }

    window.Echo.private(`App.Models.User.${userId}`).listen(EvInbox.InboxUpdated, () => {
        dispatchToLivewire('conversation-updated', {});
    });

    subscribedChatUserId = userId;
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => subscribeToUserInbox());
    } else {
        subscribeToUserInbox();
    }
}

window.__chatMessagingEcho = {
    subscribe: subscribeToMessagingConversation,
    leave: leaveChannel,
    subscribeUserInbox: subscribeToUserInbox,
};
