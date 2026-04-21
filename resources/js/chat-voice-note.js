import { videoNoteMixin } from './chat-video-note';

document.addEventListener('alpine:init', () => {
    Alpine.data('chatVoiceNote', (config) => ({
        ...videoNoteMixin(config),
        ...config,
        conversationId: Number(config?.conversationId) || null,
        recording: false,
        processing: false,
        paused: false,
        discardIntent: false,
        mediaRecorder: null,
        chunks: [],
        stream: null,
        maxDurationTimer: null,
        timerInterval: null,
        elapsedSeconds: 0,
        maxRecordSeconds: 180,
        /** @type {number[]|null} */
        waveformBars: null,
        waveformFrame: null,
        audioContext: null,
        analyser: null,
        /** @type {Uint8Array|null} */
        freqData: null,
        barCount: 40,
        previewUrl: null,
        previewListening: false,
        previewDuration: 0,
        /**
         * Heartbeat interval id. While the user is recording we whisper the
         * `recording` event roughly every 5s so other participants' TTLs keep
         * extending. The `stopRecordingWhisper()` tear-down path sends a
         * trailing false + clears the interval.
         */
        recordingHeartbeat: null,
        recordingWhisperMs: 5000,

        init() {
            this.waveformBars = Array.from({ length: this.barCount }, () => 8);
        },

        destroy() {
            this.stopRecordingWhisper();
            this.cleanupVideoNoteResources();
        },

        recordingIdentity() {
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

        startRecordingWhisper() {
            const echo = window.__chatMessagingEcho;
            const cid = this.conversationId;

            if (! echo || ! Number.isInteger(cid) || cid <= 0) {
                return;
            }

            const who = this.recordingIdentity();

            echo.whisperRecording(cid, who);

            if (this.recordingHeartbeat) {
                window.clearInterval(this.recordingHeartbeat);
            }

            this.recordingHeartbeat = window.setInterval(() => {
                if (! this.recording && ! this.videoRecording) {
                    this.stopRecordingWhisper();

                    return;
                }

                echo.whisperRecording(cid, who);
            }, this.recordingWhisperMs);
        },

        stopRecordingWhisper() {
            if (this.recordingHeartbeat) {
                window.clearInterval(this.recordingHeartbeat);
                this.recordingHeartbeat = null;
            }

            const echo = window.__chatMessagingEcho;
            const cid = this.conversationId;

            if (! echo || ! Number.isInteger(cid) || cid <= 0) {
                return;
            }

            echo.whisperStopRecording(cid, this.recordingIdentity());
        },

        $lw() {
            const root = this.$root.closest('[wire\\:id]');

            if (! root || ! window.Livewire) {
                return null;
            }

            return window.Livewire.find(root.getAttribute('wire:id'));
        },

        revokePreviewUrl() {
            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = null;
            }

            const audio = this.$refs.previewAudio;

            if (audio) {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
            }

            this.previewListening = false;
            this.previewDuration = 0;
        },

        formatElapsed() {
            const s = this.elapsedSeconds;
            const m = Math.floor(s / 60);
            const r = s % 60;

            return `${m}:${r.toString().padStart(2, '0')}`;
        },

        toggle() {
            if (this.processing) {
                return;
            }

            if (this.videoRecording || this.videoNotePreview) {
                return;
            }

            if (this.recording) {
                this.finishRecording();

                return;
            }

            this.startRecording();
        },

        pickMime() {
            const candidates = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/mp4',
                'audio/ogg;codecs=opus',
            ];

            for (const t of candidates) {
                if (window.MediaRecorder?.isTypeSupported(t)) {
                    return t;
                }
            }

            return '';
        },

        extForMime(mime) {
            if (! mime) {
                return 'webm';
            }

            if (mime.includes('webm')) {
                return 'webm';
            }

            if (mime.includes('ogg')) {
                return 'ogg';
            }

            if (mime.includes('mp4') || mime.includes('mpeg')) {
                return 'm4a';
            }

            if (mime.includes('wav')) {
                return 'wav';
            }

            return 'webm';
        },

        cleanupStream() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },

        stopTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },

        startTimer() {
            this.stopTimer();
            this.elapsedSeconds = 0;
            this.timerInterval = setInterval(() => {
                if (this.recording && ! this.paused) {
                    this.elapsedSeconds += 1;
                }
            }, 1000);
        },

        stopWaveform() {
            if (this.waveformFrame) {
                cancelAnimationFrame(this.waveformFrame);
                this.waveformFrame = null;
            }

            if (this.audioContext && this.audioContext.state !== 'closed') {
                this.audioContext.close();
            }

            this.audioContext = null;
            this.analyser = null;
            this.freqData = null;
        },

        async startWaveformAnalysis(stream) {
            this.stopWaveform();

            const AudioCtx = window.AudioContext || window.webkitAudioContext;

            if (! AudioCtx) {
                return;
            }

            this.audioContext = new AudioCtx();

            if (this.audioContext.state === 'suspended') {
                try {
                    await this.audioContext.resume();
                } catch {
                }
            }

            this.analyser = this.audioContext.createAnalyser();
            this.analyser.fftSize = 256;
            this.analyser.smoothingTimeConstant = 0.65;

            const source = this.audioContext.createMediaStreamSource(stream);
            source.connect(this.analyser);

            this.freqData = new Uint8Array(this.analyser.frequencyBinCount);

            const step = Math.max(1, Math.floor(this.freqData.length / this.barCount));

            const tick = () => {
                if (! this.recording || ! this.analyser || ! this.freqData) {
                    return;
                }

                /** Only animate from the live mic while capture is running (not paused / preview). */
                if (! this.paused) {
                    this.analyser.getByteFrequencyData(this.freqData);

                    const next = [];

                    for (let i = 0; i < this.barCount; i++) {
                        let sum = 0;
                        const start = i * step;

                        for (let j = 0; j < step && start + j < this.freqData.length; j++) {
                            sum += this.freqData[start + j];
                        }

                        const avg = sum / step;
                        const pct = 8 + (avg / 255) * 92;

                        next.push(Math.min(100, Math.round(pct)));
                    }

                    this.waveformBars = next;
                }

                this.waveformFrame = requestAnimationFrame(tick);
            };

            this.waveformFrame = requestAnimationFrame(tick);
        },

        stopPreviewPlayback() {
            const audio = this.$refs.previewAudio;

            if (audio && ! audio.paused) {
                audio.pause();
            }

            this.previewListening = false;
        },

        togglePause() {
            if (! this.mediaRecorder || ! this.recording) {
                return;
            }

            try {
                if (this.paused) {
                    this.stopPreviewPlayback();
                    this.mediaRecorder.resume();
                    this.paused = false;
                } else {
                    this.mediaRecorder.pause();
                    this.paused = true;
                }
            } catch {
                this.paused = false;
            }
        },

        async togglePreviewPlayback() {
            const audio = this.$refs.previewAudio;

            if (! audio || ! this.recording || this.processing) {
                return;
            }

            if (audio.src && ! audio.paused) {
                audio.pause();
                this.previewListening = false;

                return;
            }

            try {
                if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
                    this.mediaRecorder.pause();
                    this.paused = true;
                }

                try {
                    this.mediaRecorder?.requestData?.();
                } catch {
                }

                await new Promise((r) => setTimeout(r, 120));

                const mimeType = this.mediaRecorder?.mimeType || 'audio/webm';
                const blob = new Blob(this.chunks, { type: mimeType });

                if (blob.size === 0) {
                    return;
                }

                this.revokePreviewUrl();
                this.previewUrl = URL.createObjectURL(blob);
                audio.src = this.previewUrl;

                await new Promise((resolve, reject) => {
                    const ok = () => {
                        audio.removeEventListener('loadedmetadata', ok);
                        audio.removeEventListener('error', bad);
                        resolve();
                    };

                    const bad = () => {
                        audio.removeEventListener('loadedmetadata', ok);
                        audio.removeEventListener('error', bad);
                        reject(new Error('audio load'));
                    };

                    audio.addEventListener('loadedmetadata', ok, { once: true });
                    audio.addEventListener('error', bad, { once: true });
                });

                this.previewDuration = audio.duration || 0;
                await audio.play();
                this.previewListening = true;
            } catch {
                this.previewListening = false;
            }
        },

        onPreviewLoaded() {
            const audio = this.$refs.previewAudio;

            if (audio && ! Number.isNaN(audio.duration)) {
                this.previewDuration = audio.duration;
            }
        },

        onPreviewEnded() {
            this.previewListening = false;
        },

        discardRecording() {
            if (this.processing || ! this.recording) {
                return;
            }

            this.revokePreviewUrl();
            this.discardIntent = true;
            this.stopTimer();
            this.stopWaveform();

            if (this.maxDurationTimer) {
                clearTimeout(this.maxDurationTimer);
                this.maxDurationTimer = null;
            }

            if (this.mediaRecorder) {
                this.mediaRecorder.ondataavailable = null;

                try {
                    this.mediaRecorder.stop();
                } catch {
                    this.discardIntent = false;
                    this.finalizeDiscard();
                }
            } else {
                this.discardIntent = false;
                this.finalizeDiscard();
            }
        },

        async finalizeDiscard() {
            this.revokePreviewUrl();
            this.chunks = [];
            this.recording = false;
            this.paused = false;
            this.cleanupStream();
            this.mediaRecorder = null;
            this.waveformBars = Array.from({ length: this.barCount }, () => 8);

            const lw = this.$lw();

            if (lw) {
                await lw.$call('clearVoiceNoteError');
            }
        },

        async startRecording() {
            const $wire = this.$lw();

            if (! $wire) {
                return;
            }

            if (this.videoRecording || this.videoNotePreview) {
                return;
            }

            this.revokePreviewUrl();

            if (! navigator.mediaDevices?.getUserMedia || ! window.MediaRecorder) {
                await $wire.$call('setVoiceNoteError', this.errUnsupported);

                return;
            }

            await $wire.$call('clearVoiceNoteError');

            let stream;

            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch {
                await $wire.$call('setVoiceNoteError', this.errPermission);

                return;
            }

            this.stream = stream;
            this.chunks = [];
            this.discardIntent = false;
            this.paused = false;
            const preferredMime = this.pickMime();

            try {
                this.mediaRecorder = preferredMime
                    ? new MediaRecorder(stream, { mimeType: preferredMime })
                    : new MediaRecorder(stream);
            } catch {
                this.cleanupStream();
                await $wire.$call('setVoiceNoteError', this.errUnsupported);

                return;
            }

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.chunks.push(event.data);
                }
            };

            this.mediaRecorder.onerror = () => {
                this.discardIntent = false;
                this.recording = false;
                this.paused = false;
                this.stopRecordingWhisper();

                if (this.maxDurationTimer) {
                    clearTimeout(this.maxDurationTimer);
                    this.maxDurationTimer = null;
                }

                this.revokePreviewUrl();
                this.stopTimer();
                this.stopWaveform();
                this.chunks = [];
                this.cleanupStream();
                this.mediaRecorder = null;
                this.waveformBars = Array.from({ length: this.barCount }, () => 8);
                $wire.$call('setVoiceNoteError', this.errUpload);
            };

            this.mediaRecorder.onstop = async () => {
                this.recording = false;
                this.paused = false;
                this.stopRecordingWhisper();

                if (this.maxDurationTimer) {
                    clearTimeout(this.maxDurationTimer);
                    this.maxDurationTimer = null;
                }

                this.stopTimer();
                this.stopWaveform();
                this.revokePreviewUrl();

                const lw = this.$lw();

                if (this.discardIntent) {
                    this.discardIntent = false;
                    await this.finalizeDiscard();

                    return;
                }

                if (! lw) {
                    return;
                }

                const mimeType = this.mediaRecorder.mimeType || preferredMime || 'audio/webm';
                const blob = new Blob(this.chunks, { type: mimeType });

                this.chunks = [];
                this.cleanupStream();
                this.mediaRecorder = null;
                this.waveformBars = Array.from({ length: this.barCount }, () => 8);

                if (blob.size === 0) {
                    await lw.$call('clearVoiceNoteError');

                    return;
                }

                const ext = this.extForMime(blob.type || mimeType);
                const file = new File([blob], `voice-note.${ext}`, {
                    type: blob.type || mimeType || 'audio/webm',
                });

                this.processing = true;

                try {
                    await lw.$call('prepareVoiceNoteForImmediateSend');

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
                    await lw.$call('clearVoiceNoteError');
                } catch {
                    await lw.$call('setVoiceNoteError', this.errUpload);
                } finally {
                    this.processing = false;
                }
            };

            this.mediaRecorder.start(250);
            this.recording = true;
            this.startRecordingWhisper();
            this.startTimer();
            this.startWaveformAnalysis(stream);

            this.maxDurationTimer = setTimeout(() => {
                if (this.recording) {
                    this.finishRecording();
                }
            }, this.maxRecordSeconds * 1000);
        },

        finishRecording() {
            if (this.processing || ! this.recording) {
                return;
            }

            this.revokePreviewUrl();

            if (this.mediaRecorder && this.recording) {
                try {
                    this.mediaRecorder.requestData();
                } catch {
                }
                try {
                    this.mediaRecorder.stop();
                } catch {
                }
            }
        },
    }));
});
