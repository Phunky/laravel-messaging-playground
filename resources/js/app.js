import './bootstrap';
import './chat-scroll';
import './chat-voice-note';
import './chat-messaging-echo';
import './chat-typing';

document.addEventListener('livewire:initialized', () => {
    if (window.__chatScrollIslandHooksRegistered) {
        return;
    }

    window.__chatScrollIslandHooksRegistered = true;

    const Livewire = window.Livewire;

    if (! Livewire?.hook) {
        return;
    }

    Livewire.hook('island.morph', () => {
        const el = document.getElementById('chat-scroll-area');
        if (! el) {
            return;
        }

        el.dataset.chatScrollH = String(el.scrollHeight);
        el.dataset.chatScrollT = String(el.scrollTop);
    });

    Livewire.hook('island.morphed', () => {
        const el = document.getElementById('chat-scroll-area');
        if (! el?.dataset.chatScrollH) {
            return;
        }

        const before = parseInt(el.dataset.chatScrollH, 10);
        const top = parseInt(el.dataset.chatScrollT, 10);
        el.scrollTop = el.scrollHeight - before + top;

        delete el.dataset.chatScrollH;
        delete el.dataset.chatScrollT;
    });
});
