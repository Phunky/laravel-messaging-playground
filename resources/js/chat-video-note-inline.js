/**
 * Inline circular video note in the message bubble: play/pause toggle and duration label.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('chatVideoNoteInline', () => ({
        durationLabel: '0:00',
        playing: false,

        init() {
            const v = this.$refs.video;

            if (! v) {
                return;
            }

            const syncDuration = () => {
                if (v.duration && ! Number.isNaN(v.duration)) {
                    this.durationLabel = this.formatTime(v.duration);
                }
            };

            v.addEventListener('loadedmetadata', syncDuration);
            v.addEventListener('durationchange', syncDuration);
            v.addEventListener('play', () => {
                this.playing = true;
            });
            v.addEventListener('pause', () => {
                this.playing = false;
            });
            v.addEventListener('ended', () => {
                this.playing = false;
            });
        },

        formatTime(totalSeconds) {
            const s = Math.max(0, Math.floor(Number(totalSeconds) || 0));
            const m = Math.floor(s / 60);
            const r = s % 60;

            return `${m}:${r.toString().padStart(2, '0')}`;
        },

        togglePlay() {
            const v = this.$refs.video;

            if (! v) {
                return;
            }

            if (v.paused) {
                v.play().catch(() => {});
            } else {
                v.pause();
            }
        },
    }));
});
