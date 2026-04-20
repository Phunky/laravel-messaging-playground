document.addEventListener('alpine:init', () => {
    Alpine.data('messageLongPress', (messageId) => ({
        _t: null,
        start(ev) {
            if (! ev.touches || ev.touches.length === 0) {
                return;
            }
            this.clear();
            const id = messageId;
            this._t = setTimeout(() => {
                window.Livewire?.dispatch('open-message-reaction-picker', { messageId: id });
                this._t = null;
            }, 480);
        },
        clear() {
            if (this._t) {
                clearTimeout(this._t);
                this._t = null;
            }
        },
    }));
});
