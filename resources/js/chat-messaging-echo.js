/**
 * Subscribe to messaging presence channels via Laravel Echo + Reverb.
 *
 * The `phunky/laravel-messaging` package broadcasts every conversation event
 * on a presence channel: `{prefix}.conversation.{id}`. We:
 *
 *   - Join one channel per conversation the signed-in user participates in
 *     (the list is provided in <meta name="chat-conversation-ids">). This lets
 *     typing whispers reach the inbox even when the user isn't viewing that
 *     thread.
 *   - Track presence members per conversation so Livewire can render an online
 *     dot for 1:1 rows / avatars.
 *   - Track typing whispers (client event `typing`) per conversation with a 5s
 *     expiry so the indicator auto-clears if the sender never explicitly stops.
 *
 * Event names must match `broadcastAs()` in each Phunky\LaravelMessaging\Events\*
 * class (leading dot tells Echo to use the literal name, without the app
 * namespace prefix).
 */
const Ev = {
    MessageSent: '.messaging.message.sent',
    MessageEdited: '.messaging.message.edited',
    MessageDeleted: '.messaging.message.deleted',
    AllMessagesRead: '.messaging.all_messages.read',
    ReactionAdded: '.messaging.reaction.added',
    ReactionRemoved: '.messaging.reaction.removed',
    AttachmentAttached: '.messaging.attachment.attached',
    AttachmentDetached: '.messaging.attachment.detached',
};

const WHISPER_KINDS = [
    {
        kind: 'typing',
        flag: 'typing',
        activeField: 'typing',
        ttlMs: 5000,
        livewireEvent: 'messaging-typing-updated',
        payloadKey: 'typingUsers',
    },
    {
        kind: 'recording',
        flag: 'recording',
        activeField: 'recording',
        ttlMs: 15000,
        livewireEvent: 'messaging-recording-updated',
        payloadKey: 'recordingUsers',
    },
];

const WHISPER_KIND_BY_NAME = new Map(WHISPER_KINDS.map((row) => [row.kind, row]));

/**
 * Opt-in verbose logging. Enable from DevTools with:
 *   localStorage.messagingDebug = '1'
 * then reload. Disable with:
 *   delete localStorage.messagingDebug
 */
function debugEnabled() {
    try {
        return window.localStorage?.getItem('messagingDebug') === '1';
    } catch {
        return false;
    }
}

function debugLog(...args) {
    if (debugEnabled()) {
        console.info('[messaging]', ...args);
    }
}

/**
 * @type {Map<number, {
 *     channel: any,
 *     members: Map<string, { id: number|string, name: string }>,
 *     whispers: Map<string, Map<string, { name: string, timer: number|null }>>,
 * }>}
 */
const subscriptions = new Map();

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

function channelNameFor(conversationId) {
    return `${channelPrefix()}.conversation.${conversationId}`;
}

function memberKey(member) {
    if (!member) {
        return '';
    }

    return String(member.id ?? member.user_id ?? '');
}

function emitPresence(conversationId) {
    const state = subscriptions.get(conversationId);
    if (!state) {
        return;
    }

    const onlineUserIds = Array.from(state.members.values())
        .map((m) => Number(m.id))
        .filter((id) => !Number.isNaN(id));

    dispatchToLivewire('messaging-presence-updated', {
        conversationId,
        onlineUserIds,
    });
}

function emitWhisper(conversationId, kindDef) {
    const state = subscriptions.get(conversationId);
    if (!state) {
        return;
    }

    const bucket = state.whispers.get(kindDef.kind);
    if (!bucket) {
        return;
    }

    const users = Array.from(bucket.entries()).map(([id, v]) => ({
        id: Number(id),
        name: v.name,
    }));

    dispatchToLivewire(kindDef.livewireEvent, {
        conversationId,
        [kindDef.payloadKey]: users,
    });
}

function clearWhisper(conversationId, kindDef, userKey) {
    const state = subscriptions.get(conversationId);
    if (!state) {
        return;
    }

    const bucket = state.whispers.get(kindDef.kind);
    if (!bucket) {
        return;
    }

    const entry = bucket.get(userKey);
    if (entry?.timer) {
        clearTimeout(entry.timer);
    }

    if (bucket.delete(userKey)) {
        emitWhisper(conversationId, kindDef);
    }
}

/**
 * Pull the conversation + message ids out of a reaction/attachment broadcast
 * payload. Both packages serialize the `$message` model, so `conversation_id`
 * and `id` live there; the conversation_id on the wrapped reaction/attachment
 * row is used as a fallback in case a future version changes the shape.
 */
function extractConversationAndMessageIds(payload, wrappedKey, requireMessageId = true) {
    const message = payload?.message;
    const wrapped = wrappedKey ? payload?.[wrappedKey] : null;

    const cid = message?.conversation_id ?? wrapped?.conversation_id ?? wrapped?.id;
    const mid = message?.id ?? wrapped?.message_id;

    const conversationId = cid !== undefined && cid !== null ? Number(cid) : NaN;
    const messageId = mid !== undefined && mid !== null ? Number(mid) : null;

    if (!Number.isInteger(conversationId)) {
        return null;
    }

    if (requireMessageId && !Number.isInteger(messageId)) {
        return null;
    }

    return { conversationId, messageId };
}

function handleReactionChange(payload) {
    const ids = extractConversationAndMessageIds(payload, 'reaction', true);
    if (!ids) {
        console.warn('[messaging] reaction payload missing ids', payload);

        return;
    }

    dispatchToLivewire('messaging-remote-reaction-updated', ids);
    dispatchToLivewire('conversation-updated', {});
}

function handleAttachmentChange(payload) {
    const ids = extractConversationAndMessageIds(payload, 'attachment', true);
    if (!ids) {
        console.warn('[messaging] attachment payload missing ids', payload);

        return;
    }

    dispatchToLivewire('messaging-remote-attachment-updated', ids);
    dispatchToLivewire('conversation-updated', {});
}

/**
 * Attach a broadcast listener that routes payload ids into Livewire events.
 *
 * @param {{
 *   channel: any,
 *   eventName: string,
 *   wrappedKey?: string|null,
 *   livewireEvent?: string|null,
 *   logLabel: string,
 *   alsoBumpInbox?: boolean,
 *   requireMessageId?: boolean,
 * }} config
 */
function routeMessageBroadcast(config) {
    const {
        channel,
        eventName,
        wrappedKey = null,
        livewireEvent = null,
        logLabel,
        alsoBumpInbox = true,
        requireMessageId = true,
    } = config;

    channel.listen(eventName, (payload) => {
        debugLog(`received ${logLabel}`, payload);

        const ids = extractConversationAndMessageIds(payload, wrappedKey, requireMessageId);
        if (!ids) {
            console.warn(`[messaging] ${logLabel} payload missing ids`, payload);

            return;
        }

        if (livewireEvent) {
            /** @type {Record<string, number>} */
            const dispatched = { conversationId: ids.conversationId };
            if (requireMessageId && Number.isInteger(ids.messageId)) {
                dispatched.messageId = /** @type {number} */ (ids.messageId);
            }
            dispatchToLivewire(livewireEvent, dispatched);
        }

        if (alsoBumpInbox) {
            dispatchToLivewire('conversation-updated', {});
        }
    });
}

function setWhisper(conversationId, kindDef, payload) {
    const state = subscriptions.get(conversationId);
    if (!state) {
        return;
    }

    const bucket = state.whispers.get(kindDef.kind);
    if (!bucket) {
        return;
    }

    const id = payload?.messageable_id ?? payload?.id;
    const name = String(payload?.name ?? '').trim();
    if (id === undefined || id === null || name === '') {
        return;
    }

    const key = String(id);

    if (payload?.[kindDef.activeField] === false) {
        clearWhisper(conversationId, kindDef, key);

        return;
    }

    const existing = bucket.get(key);
    if (existing?.timer) {
        clearTimeout(existing.timer);
    }

    const timer = window.setTimeout(() => {
        clearWhisper(conversationId, kindDef, key);
    }, kindDef.ttlMs);

    bucket.set(key, { name, timer });
    emitWhisper(conversationId, kindDef);
}

function leaveChannel(conversationId) {
    const state = subscriptions.get(conversationId);
    if (!state) {
        return;
    }

    for (const bucket of state.whispers.values()) {
        for (const { timer } of bucket.values()) {
            if (timer) {
                clearTimeout(timer);
            }
        }
    }

    if (window.Echo) {
        window.Echo.leave(channelNameFor(conversationId));
    }

    subscriptions.delete(conversationId);
    for (const kindDef of WHISPER_KINDS) {
        emitWhisper(conversationId, kindDef);
    }
    emitPresence(conversationId);
}

function leaveAllChannels() {
    for (const id of Array.from(subscriptions.keys())) {
        leaveChannel(id);
    }
}

/**
 * @param {number} conversationId
 */
function joinChannel(conversationId) {
    if (!window.Echo || !Number.isInteger(conversationId) || subscriptions.has(conversationId)) {
        return;
    }

    const name = channelNameFor(conversationId);
    const channel = window.Echo.join(name);

    debugLog('join requested', name);

    const state = {
        channel,
        name,
        members: new Map(),
        whispers: new Map(WHISPER_KINDS.map((row) => [row.kind, new Map()])),
        subscribed: false,
    };
    subscriptions.set(conversationId, state);

    channel
        .here((members) => {
            state.subscribed = true;
            state.members.clear();
            (members ?? []).forEach((m) => {
                const key = memberKey(m);
                if (key !== '') {
                    state.members.set(key, m);
                }
            });
            debugLog('subscribed', name, { members: Array.from(state.members.values()) });
            emitPresence(conversationId);
        })
        .joining((member) => {
            const key = memberKey(member);
            if (key === '') {
                return;
            }
            state.members.set(key, member);
            debugLog('member joined', name, member);
            emitPresence(conversationId);
        })
        .leaving((member) => {
            const key = memberKey(member);
            if (key === '') {
                return;
            }
            state.members.delete(key);
            for (const kindDef of WHISPER_KINDS) {
                clearWhisper(conversationId, kindDef, key);
            }
            debugLog('member left', name, member);
            emitPresence(conversationId);
        })
        .error((e) => {
            console.warn('[messaging] presence channel error', name, e);
        })
        ;

    WHISPER_KINDS.forEach((kindDef) => {
        channel.listenForWhisper(kindDef.flag, (payload) => {
            setWhisper(conversationId, kindDef, payload ?? {});
        });
    });

    [
        {
            eventName: Ev.MessageSent,
            livewireEvent: 'messaging-remote-message-sent',
            logLabel: 'MessageSent',
            wrappedKey: 'conversation',
        },
        {
            eventName: Ev.MessageEdited,
            livewireEvent: 'messaging-remote-message-edited',
            logLabel: 'MessageEdited',
        },
        {
            eventName: Ev.MessageDeleted,
            livewireEvent: 'messaging-remote-message-deleted',
            logLabel: 'MessageDeleted',
            wrappedKey: 'conversation',
        },
        {
            eventName: Ev.AllMessagesRead,
            livewireEvent: null,
            logLabel: 'AllMessagesRead',
            wrappedKey: 'conversation',
            requireMessageId: false,
        },
    ].forEach((route) => {
        routeMessageBroadcast({
            channel,
            eventName: route.eventName,
            wrappedKey: route.wrappedKey ?? null,
            livewireEvent: route.livewireEvent,
            logLabel: `${route.logLabel} ${name}`,
            requireMessageId: route.requireMessageId ?? true,
        });
    });

    [
        { eventName: Ev.ReactionAdded, logLabel: 'ReactionAdded', handler: handleReactionChange },
        { eventName: Ev.ReactionRemoved, logLabel: 'ReactionRemoved', handler: handleReactionChange },
        { eventName: Ev.AttachmentAttached, logLabel: 'AttachmentAttached', handler: handleAttachmentChange },
        { eventName: Ev.AttachmentDetached, logLabel: 'AttachmentDetached', handler: handleAttachmentChange },
    ].forEach((route) => {
        channel.listen(route.eventName, (payload) => {
            debugLog(`received ${route.logLabel}`, name, payload);
            route.handler(payload);
        });
    });
}

/**
 * Kept for the legacy API (message-pane calls this when the open conversation
 * changes). It is now effectively a no-op when the user already participates
 * in that conversation — the channel was joined during fan-out on page load —
 * but still handles newly created conversations that weren't in the initial
 * meta list.
 *
 * @param {number|null|undefined} conversationId
 */
export function subscribeToMessagingConversation(conversationId) {
    if (!window.Echo) {
        return;
    }

    const id = conversationId === null || conversationId === undefined ? null : Number(conversationId);

    if (id === null || Number.isNaN(id)) {
        return;
    }

    joinChannel(id);
}

/**
 * Fan-out: join every conversation the current user participates in so that
 * inbox rows can receive typing whispers + presence for threads that are not
 * currently on screen.
 */
function subscribeToAllUserConversations() {
    if (!window.Echo) {
        return;
    }

    const meta = document.querySelector('meta[name="chat-conversation-ids"]');
    const raw = meta?.getAttribute('content') ?? '';

    raw.split(',')
        .map((s) => s.trim())
        .filter((s) => s !== '')
        .map((s) => Number(s))
        .filter((n) => Number.isInteger(n))
        .forEach(joinChannel);
}

/**
 * @param {number} conversationId
 * @param {string} kind
 * @param {{ messageable_type?: string, messageable_id?: number|string, name: string }} who
 * @param {boolean} active
 */
function whisper(conversationId, kind, who, active) {
    const state = subscriptions.get(conversationId);
    if (!state) {
        return;
    }

    const kindDef = WHISPER_KIND_BY_NAME.get(kind);
    if (!kindDef) {
        return;
    }

    state.channel.whisper(kindDef.flag, {
        messageable_type: who?.messageable_type ?? null,
        messageable_id: who?.messageable_id ?? null,
        name: who?.name ?? '',
        [kindDef.activeField]: active,
    });
}

/**
 * Throttled typing whisper for the composer. Safe to call on every keystroke.
 *
 * @param {number} conversationId
 * @param {{ messageable_type?: string, messageable_id?: number|string, name: string }} who
 */
function whisperTyping(conversationId, who) {
    whisper(conversationId, 'typing', who, true);
}

function whisperStopTyping(conversationId, who) {
    whisper(conversationId, 'typing', who, false);
}

/**
 * Heartbeat whisper for "recording a voice note". Callers should ping every
 * few seconds while recording is active; a trailing false is sent on stop.
 *
 * @param {number} conversationId
 * @param {{ messageable_type?: string, messageable_id?: number|string, name: string }} who
 */
function whisperRecording(conversationId, who) {
    whisper(conversationId, 'recording', who, true);
}

function whisperStopRecording(conversationId, who) {
    whisper(conversationId, 'recording', who, false);
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

function bootstrap() {
    subscribeToUserInbox();
    subscribeToAllUserConversations();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
}

window.__chatMessagingEcho = {
    subscribe: subscribeToMessagingConversation,
    /**
     * Without an argument this is a no-op under the fan-out model: closing the
     * open thread should not drop the presence/typing subscription because the
     * inbox still needs it. Pass a conversation id to explicitly unsubscribe
     * (e.g. when the user leaves a conversation).
     */
    leave: (conversationId) => {
        if (conversationId === undefined || conversationId === null) {
            return;
        }

        const id = Number(conversationId);
        if (Number.isInteger(id)) {
            leaveChannel(id);
        }
    },
    leaveAll: leaveAllChannels,
    subscribeUserInbox: subscribeToUserInbox,
    subscribeAll: subscribeToAllUserConversations,
    whisper,
    whisperTyping,
    whisperStopTyping,
    whisperRecording,
    whisperStopRecording,
    /**
     * Inspect current subscription state from DevTools:
     *   window.__chatMessagingEcho.debug()
     */
    debug() {
        const metaIds = document.querySelector('meta[name="chat-conversation-ids"]')?.getAttribute('content') ?? '';
        const metaUserId = document.querySelector('meta[name="chat-user-id"]')?.getAttribute('content') ?? '';
        const metaUserName = document.querySelector('meta[name="chat-user-name"]')?.getAttribute('content') ?? '';

        return {
            echoReady: Boolean(window.Echo),
            debugLogging: debugEnabled(),
            meta: { userId: metaUserId, userName: metaUserName, conversationIds: metaIds },
            subscriptions: Array.from(subscriptions.entries()).map(([id, state]) => ({
                conversationId: id,
                channel: state.name,
                subscribed: state.subscribed,
                memberCount: state.members.size,
                members: Array.from(state.members.values()),
                whispers: Object.fromEntries(WHISPER_KINDS.map((kindDef) => [
                    kindDef.kind,
                    Array.from(state.whispers.get(kindDef.kind)?.entries() ?? [])
                        .map(([key, entry]) => ({ id: key, name: entry.name })),
                ])),
                typing: Array.from(state.whispers.get('typing')?.entries() ?? [])
                    .map(([key, entry]) => ({ id: key, name: entry.name })),
                recording: Array.from(state.whispers.get('recording')?.entries() ?? [])
                    .map(([key, entry]) => ({ id: key, name: entry.name })),
            })),
        };
    },
};
