/**
 * Keep the chat thread pinned to the bottom while layout height changes (e.g. images decoding).
 * Cancels if the user scrolls up (wheel deltaY < 0).
 */
export function stabilizeChatScrollToBottom() {
    const el = document.getElementById('chat-scroll-area');
    if (!el) {
        return;
    }

    let cancelled = false;
    const onWheel = (e) => {
        if (e.deltaY < 0) {
            cancelled = true;
        }
    };
    el.addEventListener('wheel', onWheel, { passive: true });

    const maxMs = 2800;
    const settleFrames = 5;
    const start = performance.now();
    let lastHeight = -1;
    let stableCount = 0;

    const finish = () => {
        el.removeEventListener('wheel', onWheel);
    };

    const tick = () => {
        if (cancelled) {
            finish();

            return;
        }

        el.scrollTop = el.scrollHeight;
        const h = el.scrollHeight;
        if (h === lastHeight) {
            stableCount++;
        } else {
            stableCount = 0;
            lastHeight = h;
        }

        const elapsed = performance.now() - start;
        if (stableCount >= settleFrames && elapsed > 120) {
            finish();

            return;
        }
        if (elapsed >= maxMs) {
            finish();

            return;
        }

        requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
}

window.stabilizeChatScrollToBottom = stabilizeChatScrollToBottom;
