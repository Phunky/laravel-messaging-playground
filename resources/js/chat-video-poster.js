/**
 * Generate a JPEG poster from a seeked frame so <video> does not show a black
 * first paint. MediaRecorder WebM often has a black or undecodable frame until
 * the first keyframe — use a later seek for webm + wait for loadeddata.
 */
const POSTER_CLASS = 'chat-video-poster';

function wantsAggressiveSeek(video) {
    const m = (video.dataset.mimeType || '').toLowerCase();

    return m.includes('webm') || m.includes('matroska');
}

function computeSeekTime(video) {
    const dur = video.duration;

    if (! Number.isFinite(dur) || dur <= 0) {
        return wantsAggressiveSeek(video) ? 0.5 : 0.2;
    }

    if (wantsAggressiveSeek(video)) {
        return Math.min(dur - 0.05, Math.max(0.45, dur * 0.18));
    }

    return Math.min(1.25, Math.max(0.1, dur * 0.08));
}

function attachVideoPoster(video) {
    if (!(video instanceof HTMLVideoElement) || !video.classList.contains(POSTER_CLASS)) {
        return;
    }

    if (video.dataset.posterReady === '1') {
        return;
    }

    if (video.dataset.posterPending === '1') {
        return;
    }

    video.dataset.posterPending = '1';

    const complete = () => {
        video.dataset.posterPending = '';
        video.dataset.posterReady = '1';
    };

    const drawFrame = () => {
        const vw = video.videoWidth;
        const vh = video.videoHeight;

        if (vw < 2 || vh < 2) {
            complete();

            return;
        }

        const maxW = 720;
        let cw = vw;
        let ch = vh;

        if (cw > maxW) {
            ch = (vh * maxW) / vw;
            cw = maxW;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(cw);
        canvas.height = Math.round(ch);
        const ctx = canvas.getContext('2d');

        if (! ctx) {
            complete();

            return;
        }

        const encodePoster = () => {
            try {
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                video.poster = canvas.toDataURL('image/jpeg', 0.85);
            } catch {
                // CORS-tainted canvas or security error — leave poster unset.
            }

            try {
                video.currentTime = 0;
            } catch {
            }

            complete();
        };

        if (typeof video.requestVideoFrameCallback === 'function') {
            video.requestVideoFrameCallback(() => encodePoster());

            return;
        }

        requestAnimationFrame(() => {
            requestAnimationFrame(() => encodePoster());
        });
    };

    const seekAndCapture = () => {
        const t = computeSeekTime(video);

        let drawn = false;

        const runDrawOnce = () => {
            if (drawn) {
                return;
            }

            drawn = true;
            drawFrame();
        };

        const fallback = window.setTimeout(() => {
            if (drawn) {
                return;
            }

            if (video.readyState >= 2) {
                runDrawOnce();
            } else {
                complete();
            }
        }, 800);

        video.addEventListener(
            'seeked',
            () => {
                window.clearTimeout(fallback);
                runDrawOnce();
            },
            { once: true },
        );

        try {
            video.currentTime = t;
        } catch {
            window.clearTimeout(fallback);
            complete();
        }
    };

    const ensureBufferedThenSeek = () => {
        const run = () => {
            seekAndCapture();
        };

        if (video.readyState >= 2) {
            run();

            return;
        }

        let ran = false;

        const onReady = () => {
            if (ran) {
                return;
            }

            ran = true;
            run();
        };

        video.addEventListener('loadeddata', onReady, { once: true });
        video.addEventListener('canplay', onReady, { once: true });

        if (video.preload === 'metadata') {
            try {
                video.preload = 'auto';
            } catch {
            }
        }
    };

    video.addEventListener(
        'error',
        () => {
            complete();
        },
        { once: true },
    );

    const start = () => {
        ensureBufferedThenSeek();
    };

    if (video.readyState >= 1 && Number.isFinite(video.duration)) {
        start();
    } else {
        video.addEventListener(
            'loadedmetadata',
            () => {
                start();
            },
            { once: true },
        );
    }
}

export function initChatVideoPosters(root = document) {
    if (! root?.querySelectorAll) {
        return;
    }

    root.querySelectorAll(`video.${POSTER_CLASS}:not([data-poster-ready="1"])`).forEach((el) => {
        attachVideoPoster(el);
    });
}

function bootstrap() {
    initChatVideoPosters(document.body);

    document.addEventListener(
        'loadedmetadata',
        (event) => {
            const target = event.target;

            if (target instanceof HTMLVideoElement && target.classList.contains(POSTER_CLASS)) {
                attachVideoPoster(target);
            }
        },
        true,
    );

    let debounce;

    if (typeof document !== 'undefined' && document.body) {
        new MutationObserver(() => {
            clearTimeout(debounce);
            debounce = setTimeout(() => initChatVideoPosters(document.body), 100);
        }).observe(document.body, { childList: true, subtree: true });
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
}
