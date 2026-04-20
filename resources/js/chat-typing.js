/**
 * Alpine component that broadcasts a throttled `typing` whisper on the
 * messaging presence channel for a conversation while the user is actively
 * composing, and a trailing `typing:false` whisper once they fall idle.
 *
 * Usage:
 *
 *   <input
 *     x-data="chatTypingEmitter(conversationId)"
 *     @input="ping()"
 *     @blur="stopNow()"
 *   />
 *
 * The sender identity is pulled from <meta name="chat-user-id"> and
 * <meta name="chat-user-name"> so the component does not need to be passed
 * Livewire state.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('chatTypingEmitter', (conversationId) => ({
        conversationId: Number(conversationId) || null,
        /** throttle interval for outbound "typing:true" whispers */
        throttleMs: 2000,
        /** idle period after which we send a "typing:false" whisper */
        idleMs: 3000,
        lastPingAt: 0,
        /** @type {number|null} */
        idleTimer: null,
        isActive: false,
        /** @type {(e: CustomEvent<{ conversationId?: number|string }>) => void | null} */
        sentListener: null,

        init() {
            this.$watch && this.$watch('conversationId', () => this.stopNow());

            this.sentListener = (event) => {
                const sentConversationId = Number(event?.detail?.conversationId);
                if (!Number.isInteger(sentConversationId) || sentConversationId !== this.conversationId) {
                    return;
                }

                this.stopNow();
            };

            window.addEventListener('chat-message-appended', this.sentListener);
        },

        destroy() {
            this.stopNow();

            if (this.sentListener) {
                window.removeEventListener('chat-message-appended', this.sentListener);
                this.sentListener = null;
            }
        },

        who() {
            const idMeta = document.querySelector('meta[name="chat-user-id"]');
            const nameMeta = document.querySelector('meta[name="chat-user-name"]');
            const rawId = idMeta?.getAttribute('content') ?? '';
            const id = rawId !== '' ? Number(rawId) : NaN;

            return {
                messageable_type: null,
                messageable_id: Number.isNaN(id) ? null : id,
                name: nameMeta?.getAttribute('content') ?? '',
            };
        },

        ping() {
            const echo = window.__chatMessagingEcho;
            const cid = this.conversationId;
            if (!echo || !Number.isInteger(cid) || cid <= 0) {
                return;
            }

            const now = Date.now();
            if (now - this.lastPingAt >= this.throttleMs) {
                echo.whisperTyping(cid, this.who());
                this.lastPingAt = now;
                this.isActive = true;
            }

            if (this.idleTimer) {
                window.clearTimeout(this.idleTimer);
            }
            this.idleTimer = window.setTimeout(() => this.stopNow(), this.idleMs);
        },

        stopNow() {
            if (this.idleTimer) {
                window.clearTimeout(this.idleTimer);
                this.idleTimer = null;
            }

            const echo = window.__chatMessagingEcho;
            const cid = this.conversationId;
            if (!echo || !Number.isInteger(cid) || cid <= 0) {
                this.isActive = false;
                this.lastPingAt = 0;

                return;
            }

            if (this.isActive) {
                echo.whisperStopTyping(cid, this.who());
            }

            this.isActive = false;
            this.lastPingAt = 0;
        },
    }));
});
