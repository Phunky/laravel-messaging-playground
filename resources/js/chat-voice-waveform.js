/**
 * Voice message waveform: decode peaks, cache by URL, canvas drawing (vanilla helpers + Alpine player).
 */

export const DEFAULT_BAR_COUNT = 50;

/** @type {Map<string, Float32Array>} */
export const voicePeaksCache = new Map();

/** @type {Map<string, Promise<Float32Array>>} */
const inflightPeaksByUrl = new Map();

/**
 * @param {ArrayBuffer} arrayBuffer
 * @param {number} barCount
 * @returns {Promise<Float32Array>}
 */
export async function peaksFromArrayBuffer(arrayBuffer, barCount = DEFAULT_BAR_COUNT) {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (! Ctx) {
        throw new Error('AudioContext unavailable');
    }

    const ctx = new Ctx();
    const copy = arrayBuffer.slice(0);
    const audioBuffer = await ctx.decodeAudioData(copy);
    await ctx.close();

    const ch = audioBuffer.numberOfChannels;
    const len = audioBuffer.length;
    const chunk = Math.max(1, Math.floor(len / barCount));
    const peaks = new Float32Array(barCount);

    for (let b = 0; b < barCount; b++) {
        const start = b * chunk;
        const end = Math.min(len, start + chunk);
        let p = 0;

        for (let i = start; i < end; i++) {
            let s = 0;

            for (let c = 0; c < ch; c++) {
                s += audioBuffer.getChannelData(c)[i];
            }

            s /= ch;
            const a = Math.abs(s);

            if (a > p) {
                p = a;
            }
        }

        peaks[b] = p;
    }

    let m = 0;

    for (let i = 0; i < barCount; i++) {
        if (peaks[i] > m) {
            m = peaks[i];
        }
    }

    if (m > 0) {
        for (let i = 0; i < barCount; i++) {
            peaks[i] /= m;
        }
    }

    return peaks;
}

/**
 * @param {Blob} blob
 * @param {number} barCount
 * @returns {Promise<Float32Array>}
 */
export async function peaksFromBlob(blob, barCount = DEFAULT_BAR_COUNT) {
    const ab = await blob.arrayBuffer();

    return peaksFromArrayBuffer(ab, barCount);
}

/**
 * @param {string} url
 * @param {number} barCount
 * @returns {Promise<Float32Array>}
 */
export async function peaksFromUrl(url, barCount = DEFAULT_BAR_COUNT) {
    if (voicePeaksCache.has(url)) {
        return voicePeaksCache.get(url);
    }

    if (inflightPeaksByUrl.has(url)) {
        return inflightPeaksByUrl.get(url);
    }

    const promise = (async () => {
        const res = await fetch(url, {
            credentials: 'same-origin',
            mode: 'cors',
        });

        if (! res.ok) {
            throw new Error(`fetch failed: ${res.status}`);
        }

        const buf = await res.arrayBuffer();
        const type = res.headers.get('content-type') || 'audio/webm';
        const blob = new Blob([buf], { type });
        const peaks = await peaksFromBlob(blob, barCount);

        voicePeaksCache.set(url, peaks);

        return peaks;
    })();

    inflightPeaksByUrl.set(url, promise);

    try {
        return await promise;
    } finally {
        inflightPeaksByUrl.delete(url);
    }
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {Float32Array|number[]} amplitudes
 * @param {number} progress01
 * @param {object} [opts]
 */
export function drawWaveform(canvas, amplitudes, progress01, opts = {}) {
    const {
        playedColor = '#3b82f6',
        unplayedColor = 'rgba(0,0,0,0.22)',
        gap = 2,
        minBar = 3,
        radius = 2,
        padY = 4,
    } = opts;

    const dpr = window.devicePixelRatio || 1;
    const cssW = canvas.clientWidth;
    const cssH = canvas.clientHeight;

    if (cssW <= 0 || cssH <= 0) {
        return;
    }

    canvas.width = Math.floor(cssW * dpr);
    canvas.height = Math.floor(cssH * dpr);

    const ctx = canvas.getContext('2d');
    if (! ctx) {
        return;
    }

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const n = amplitudes.length;
    const H = cssH - padY * 2;
    const totalGap = gap * (n - 1);
    const barW = (cssW - totalGap) / n;
    const splitX = progress01 * cssW;

    ctx.clearRect(0, 0, cssW, cssH);

    const rr = typeof ctx.roundRect === 'function';

    for (let i = 0; i < n; i++) {
        const x = i * (barW + gap);
        const amp = amplitudes[i] ?? 0;
        const h = Math.max(minBar, amp * H);
        const y = padY + (H - h);

        ctx.fillStyle = unplayedColor;
        ctx.beginPath();

        if (rr) {
            ctx.roundRect(x, y, barW, h, radius);
        } else {
            ctx.rect(x, y, barW, h);
        }

        ctx.fill();

        if (splitX > x) {
            const w = Math.min(barW, splitX - x);

            ctx.save();
            ctx.beginPath();
            ctx.rect(x, 0, w, cssH);
            ctx.clip();
            ctx.fillStyle = playedColor;
            ctx.beginPath();

            if (rr) {
                ctx.roundRect(x, y, barW, h, radius);
            } else {
                ctx.rect(x, y, barW, h);
            }

            ctx.fill();
            ctx.restore();
        }
    }
}

/**
 * @param {HTMLAudioElement} audio
 * @param {(p: number) => void} onProgress
 * @returns {() => void}
 */
export function attachPlaybackProgress(audio, onProgress) {
    const tick = () => {
        const d = audio.duration;
        const p = d && ! Number.isNaN(d)
            ? Math.min(1, Math.max(0, audio.currentTime / d))
            : 0;
        onProgress(p);
    };

    audio.addEventListener('timeupdate', tick);
    audio.addEventListener('seeked', tick);
    audio.addEventListener('ended', tick);
    tick();

    return () => {
        audio.removeEventListener('timeupdate', tick);
        audio.removeEventListener('seeked', tick);
        audio.removeEventListener('ended', tick);
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('chatVoiceAttachment', (config) => ({
        audioUrl: config.audioUrl,
        isMine: config.isMine ?? true,
        /** @type {Float32Array|null} */
        peaks: null,
        loadError: false,
        loading: false,
        progress: 0,
        playing: false,
        timeLabel: '0:00',
        /** @type {(() => void)|null} */
        detachProgress: null,
        /** @type {IntersectionObserver|null} */
        io: null,
        /** @type {ResizeObserver|null} */
        ro: null,

        init() {
            this.io = new IntersectionObserver(
                (entries) => {
                    if (entries.some((e) => e.isIntersecting)) {
                        this.loadWhenVisible();
                        this.io?.disconnect();
                        this.io = null;
                    }
                },
                { root: null, rootMargin: '80px 0px', threshold: 0 },
            );
            this.io.observe(this.$el);

            this.ro = new ResizeObserver(() => {
                if (this.peaks) {
                    this.draw();
                }
            });
            this.ro.observe(this.$el);
        },

        async loadWhenVisible() {
            if (this.peaks !== null || this.loading) {
                return;
            }

            this.loading = true;

            try {
                this.peaks = await peaksFromUrl(this.audioUrl);
            } catch {
                this.loadError = true;
                this.peaks = new Float32Array(DEFAULT_BAR_COUNT);
            }

            await this.$nextTick();
            this.draw();
            this.setupAudio();
            this.loading = false;
        },

        setupAudio() {
            const audio = this.$refs.audio;

            if (! audio || this.detachProgress) {
                return;
            }

            const updateTime = () => {
                const d = audio.duration;
                const c = audio.currentTime;

                if (Number.isFinite(d) && d > 0) {
                    this.timeLabel = `${this.formatDur(c)} / ${this.formatDur(d)}`;
                } else {
                    this.timeLabel = '0:00';
                }
            };

            audio.addEventListener('loadedmetadata', updateTime);
            this.detachProgress = attachPlaybackProgress(audio, (p) => {
                this.progress = p;
                updateTime();
                this.draw();
            });

            audio.addEventListener('play', () => {
                this.playing = true;
            });
            audio.addEventListener('pause', () => {
                this.playing = false;
            });
            audio.addEventListener('ended', () => {
                this.playing = false;
            });
        },

        draw() {
            const canvas = this.$refs.canvas;

            if (! canvas || ! this.peaks) {
                return;
            }

            const playedColor = this.isMine ? '#ecfdf5' : '#3b82f6';
            const unplayedColor = this.isMine ? 'rgba(255,255,255,0.35)' : 'rgba(113,113,122,0.45)';

            drawWaveform(canvas, this.peaks, this.progress, {
                playedColor,
                unplayedColor,
                gap: 2,
                minBar: 3,
                radius: 2,
                padY: 4,
            });
        },

        togglePlay() {
            const audio = this.$refs.audio;

            if (! audio) {
                return;
            }

            if (audio.paused) {
                audio.play().catch(() => {});
            } else {
                audio.pause();
            }
        },

        formatDur(sec) {
            if (! Number.isFinite(sec) || sec < 0) {
                return '0:00';
            }

            const m = Math.floor(sec / 60);
            const s = Math.floor(sec % 60);

            return `${m}:${s.toString().padStart(2, '0')}`;
        },
    }));
});
