/**
 * Video note recording (camera + mic) for the chat composer.
 * Merged into {@see chatVoiceNote} so one Alpine scope controls voice + video.
 *
 * @param {object} config
 * @param {string} [config.errVideoUnsupported]
 * @param {string} [config.errVideoPermission]
 * @param {string} [config.errVideoUpload]
 */
export function videoNoteMixin(config) {
    const maxSec = Number(config?.maxVideoRecordSeconds);

    return {
        videoRecording: false,
        /** After recording stops: review clip before upload/send. */
        videoNotePreview: false,
        videoNotePreviewUrl: null,
        videoNotePendingFile: null,
        videoNotePreviewPlaying: false,
        videoNotePreviewDurationLabel: '0:00',
        videoMediaRecorder: null,
        videoStream: null,
        videoChunks: [],
        videoDiscardIntent: false,
        videoMaxDurationTimer: null,
        videoTimerInterval: null,
        videoElapsedSeconds: 0,
        maxVideoRecordSeconds: Number.isFinite(maxSec) && maxSec > 0 ? Math.min(600, Math.max(5, maxSec)) : 60,

        errVideoUnsupported: config.errVideoUnsupported ?? '',
        errVideoPermission: config.errVideoPermission ?? '',
        errVideoUpload: config.errVideoUpload ?? '',

        syncBodyScrollLock(lock) {
            if (typeof document === 'undefined') {
                return;
            }

            const fn = lock ? 'add' : 'remove';

            document.body.classList[fn]('overflow-hidden');
            document.documentElement.classList[fn]('overflow-hidden');
        },

        revokeVideoNotePreviewState() {
            if (this.videoNotePreviewUrl) {
                URL.revokeObjectURL(this.videoNotePreviewUrl);
                this.videoNotePreviewUrl = null;
            }

            this.videoNotePendingFile = null;
            this.videoNotePreview = false;
            this.videoNotePreviewPlaying = false;
            this.videoNotePreviewDurationLabel = '0:00';

            const prev = this.$refs?.videoNotePreviewVideo;

            if (prev) {
                prev.pause?.();
                prev.removeAttribute('src');

                try {
                    prev.load();
                } catch {
                }
            }
        },

        cleanupVideoNoteResources() {
            this.syncBodyScrollLock(false);

            if (this.videoMaxDurationTimer) {
                clearTimeout(this.videoMaxDurationTimer);
                this.videoMaxDurationTimer = null;
            }

            this.stopVideoTimer();

            if (this.videoStream) {
                this.videoStream.getTracks().forEach((t) => t.stop());
                this.videoStream = null;
            }

            const live = this.$refs?.videoLivePreview;

            if (live) {
                live.pause?.();
                live.removeAttribute('src');
                live.srcObject = null;
            }

            this.revokeVideoNotePreviewState();

            this.videoMediaRecorder = null;
            this.videoChunks = [];
            this.videoRecording = false;
            this.videoDiscardIntent = false;
        },

        pickVideoMime() {
            const candidates = [
                'video/webm;codecs=vp8,opus',
                'video/webm;codecs=vp9,opus',
                'video/webm',
                'video/mp4',
            ];

            for (const t of candidates) {
                if (window.MediaRecorder?.isTypeSupported(t)) {
                    return t;
                }
            }

            return '';
        },

        extForVideoMime(mime) {
            if (! mime) {
                return 'webm';
            }

            if (mime.includes('webm')) {
                return 'webm';
            }

            if (mime.includes('mp4') || mime.includes('quicktime')) {
                return 'mp4';
            }

            return 'webm';
        },

        stopVideoTimer() {
            if (this.videoTimerInterval) {
                clearInterval(this.videoTimerInterval);
                this.videoTimerInterval = null;
            }
        },

        startVideoTimer() {
            this.stopVideoTimer();
            this.videoElapsedSeconds = 0;
            this.videoTimerInterval = setInterval(() => {
                if (this.videoRecording) {
                    this.videoElapsedSeconds += 1;
                }
            }, 1000);
        },

        formatVideoClock(totalSeconds) {
            const s = Math.max(0, Math.floor(totalSeconds));
            const m = Math.floor(s / 60);
            const r = s % 60;

            return `${m}:${r.toString().padStart(2, '0')}`;
        },

        formatVideoElapsed() {
            return this.formatVideoClock(this.videoElapsedSeconds);
        },

        formatVideoMaxClock() {
            return this.formatVideoClock(this.maxVideoRecordSeconds);
        },

        toggleVideo() {
            if (this.processing || this.recording) {
                return;
            }

            if (this.videoNotePreview) {
                return;
            }

            if (this.videoRecording) {
                this.finishVideoRecording();

                return;
            }

            this.startVideoRecording();
        },

        async discardVideoRecording() {
            if (this.processing || ! this.videoRecording) {
                return;
            }

            this.videoDiscardIntent = true;

            if (this.videoMaxDurationTimer) {
                clearTimeout(this.videoMaxDurationTimer);
                this.videoMaxDurationTimer = null;
            }

            if (this.videoMediaRecorder) {
                this.videoMediaRecorder.ondataavailable = null;

                try {
                    this.videoMediaRecorder.stop();
                } catch {
                    this.videoDiscardIntent = false;
                    await this.finalizeVideoDiscard();
                }
            } else {
                this.videoDiscardIntent = false;
                await this.finalizeVideoDiscard();
            }
        },

        async finalizeVideoDiscard() {
            this.stopRecordingWhisper();
            this.cleanupVideoNoteResources();

            const lw = this.$lw();

            if (lw) {
                await lw.$call('clearVideoNoteError');
            }
        },

        async startVideoRecording() {
            const $wire = this.$lw();

            if (! $wire) {
                return;
            }

            if (! navigator.mediaDevices?.getUserMedia || ! window.MediaRecorder) {
                await $wire.$call('setVideoNoteError', this.errVideoUnsupported);

                return;
            }

            await $wire.$call('clearVideoNoteError');

            this.revokeVideoNotePreviewState();

            let stream;

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                });
            } catch {
                await $wire.$call('setVideoNoteError', this.errVideoPermission);

                return;
            }

            this.videoStream = stream;
            this.videoChunks = [];
            this.videoDiscardIntent = false;

            this.videoRecording = true;
            this.syncBodyScrollLock(true);

            await new Promise((resolve) => {
                requestAnimationFrame(resolve);
            });

            const live = this.$refs?.videoLivePreview;

            if (live) {
                live.muted = true;
                live.playsInline = true;
                live.srcObject = stream;

                try {
                    await live.play();
                } catch {
                }
            }

            const preferredMime = this.pickVideoMime();

            try {
                this.videoMediaRecorder = preferredMime
                    ? new MediaRecorder(stream, { mimeType: preferredMime })
                    : new MediaRecorder(stream);
            } catch {
                this.cleanupVideoNoteResources();
                await $wire.$call('setVideoNoteError', this.errVideoUnsupported);

                return;
            }

            this.videoMediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.videoChunks.push(event.data);
                }
            };

            this.videoMediaRecorder.onerror = () => {
                this.videoDiscardIntent = false;
                this.stopRecordingWhisper();
                this.cleanupVideoNoteResources();
                $wire.$call('setVideoNoteError', this.errVideoUpload);
            };

            this.videoMediaRecorder.onstop = async () => {
                this.stopRecordingWhisper();

                if (this.videoMaxDurationTimer) {
                    clearTimeout(this.videoMaxDurationTimer);
                    this.videoMaxDurationTimer = null;
                }

                this.stopVideoTimer();

                if (live) {
                    live.pause?.();
                    live.srcObject = null;
                }

                if (this.videoDiscardIntent) {
                    this.videoDiscardIntent = false;
                    this.cleanupVideoNoteResources();

                    const lwDiscard = this.$lw();

                    if (lwDiscard) {
                        await lwDiscard.$call('clearVideoNoteError');
                    }

                    return;
                }

                this.videoRecording = false;

                const lw = this.$lw();

                if (! lw) {
                    this.cleanupVideoNoteResources();

                    return;
                }

                const mimeType = this.videoMediaRecorder.mimeType || preferredMime || 'video/webm';
                const blob = new Blob(this.videoChunks, { type: mimeType });

                this.videoChunks = [];

                if (this.videoStream) {
                    this.videoStream.getTracks().forEach((t) => t.stop());
                    this.videoStream = null;
                }

                this.videoMediaRecorder = null;

                if (blob.size === 0) {
                    await lw.$call('clearVideoNoteError');
                    this.cleanupVideoNoteResources();

                    return;
                }

                const ext = this.extForVideoMime(blob.type || mimeType);
                const file = new File([blob], `video-note.${ext}`, {
                    type: blob.type || mimeType || 'video/webm',
                });

                this.videoNotePreviewUrl = URL.createObjectURL(blob);
                this.videoNotePendingFile = file;
                this.videoNotePreview = true;
            };

            this.videoMediaRecorder.start(250);
            this.startRecordingWhisper();
            this.startVideoTimer();

            this.videoMaxDurationTimer = setTimeout(() => {
                if (this.videoRecording) {
                    this.finishVideoRecording();
                }
            }, this.maxVideoRecordSeconds * 1000);
        },

        finishVideoRecording() {
            if (this.processing || ! this.videoRecording) {
                return;
            }

            if (this.videoMediaRecorder && this.videoRecording) {
                try {
                    this.videoMediaRecorder.requestData();
                } catch {
                }

                try {
                    this.videoMediaRecorder.stop();
                } catch {
                }
            }
        },

        toggleVideoNotePreviewPlayback() {
            const v = this.$refs?.videoNotePreviewVideo;

            if (! v) {
                return;
            }

            if (v.paused) {
                v.play().catch(() => {});
            } else {
                v.pause();
            }
        },

        async discardVideoNotePreview() {
            if (this.processing || ! this.videoNotePreview) {
                return;
            }

            this.cleanupVideoNoteResources();

            const lw = this.$lw();

            if (lw) {
                await lw.$call('clearVideoNoteError');
            }
        },

        async closeVideoNoteModal() {
            if (this.processing) {
                return;
            }

            if (this.videoNotePreview) {
                await this.discardVideoNotePreview();

                return;
            }

            if (this.videoRecording) {
                await this.discardVideoRecording();
            }
        },

        async sendVideoNoteFromPreview() {
            if (this.processing || ! this.videoNotePreview || ! this.videoNotePendingFile) {
                return;
            }

            const lw = this.$lw();

            if (! lw) {
                return;
            }

            const file = this.videoNotePendingFile;

            this.processing = true;

            try {
                await lw.$call('prepareVideoNoteForImmediateSend');

                await new Promise((resolve, reject) => {
                    lw.$upload(
                        'pendingFiles',
                        file,
                        () => resolve(),
                        () => reject(new Error('upload failed')),
                        () => {},
                        () => reject(new Error('cancelled')),
                    );
                });

                await lw.$call('sendMessage');
                await lw.$call('clearVideoNoteError');
                this.cleanupVideoNoteResources();
            } catch {
                await lw.$call('setVideoNoteError', this.errVideoUpload);
            } finally {
                this.processing = false;
            }
        },
    };
}
