import { ref, watch } from 'vue';

const KEY = 'chessquest.muted';

function readMuted(): boolean {
    try {
        return localStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
}

export const muted = ref(readMuted());

watch(muted, (m) => {
    try {
        localStorage.setItem(KEY, m ? '1' : '0');
    } catch {
        /* storage blocked: the setting lasts for this visit only */
    }
});

let ctx: AudioContext | null = null;

function tone(freq: number, dur: number, type: OscillatorType = 'sine', when = 0, vol = 0.12) {
    if (muted.value) return;
    try {
        ctx ??= new AudioContext();
        if (ctx.state === 'suspended') void ctx.resume();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        const t = ctx.currentTime + when;
        o.type = type;
        o.frequency.value = freq;
        g.gain.setValueAtTime(vol, t);
        g.gain.exponentialRampToValueAtTime(0.001, t + dur);
        o.connect(g);
        g.connect(ctx.destination);
        o.start(t);
        o.stop(t + dur + 0.02);
    } catch {
        /* audio unavailable */
    }
}

export const sfx = {
    tap: () => tone(660, 0.05, 'triangle', 0, 0.06),
    move: () => tone(440, 0.09, 'triangle'),
    star: () => {
        tone(880, 0.1);
        tone(1320, 0.18, 'sine', 0.08);
    },
    capture: () => {
        tone(220, 0.1, 'square', 0, 0.06);
        tone(330, 0.12, 'triangle', 0.06);
    },
    oops: () => {
        tone(260, 0.18, 'sawtooth', 0, 0.06);
        tone(180, 0.3, 'sawtooth', 0.15, 0.06);
    },
    win: () => [523, 659, 784, 1047].forEach((f, i) => tone(f, 0.25, 'triangle', i * 0.11, 0.12)),
};
