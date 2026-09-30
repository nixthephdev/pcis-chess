<script setup lang="ts">
import { computed, ref } from 'vue';
import { Board, isPiece, isWhite, squareName } from '@/game/engine';
import PieceIcon from './PieceIcon.vue';
import StarIcon from './StarIcon.vue';

const props = defineProps<{
    board: Board;
    selected: number;
    targets: number[];
    lastMove: number[] | null;
    danger: Set<number> | null;
    alert: number[] | null;
    hint: number[] | null;
    checkSquare: number;
    popSquare: number;
    locked: boolean;
    shake: boolean;
}>();

const emit = defineEmits<{
    square: [index: number];
    drop: [from: number, to: number];
}>();

const NAMES: Record<string, string> = { p: 'pawn', n: 'knight', b: 'bishop', r: 'rook', q: 'queen', k: 'king' };

const squares = computed(() =>
    props.board.map((c, i) => {
        const r = i >> 3;
        const f = i & 7;
        let label = squareName(i);
        if (c === '*') label += ', star';
        else if (isPiece(c)) label += `, ${isWhite(c) ? 'white' : 'black'} ${NAMES[c.toLowerCase()]}`;
        if (props.targets.includes(i)) label += ', can move here';
        return {
            i,
            c,
            dark: (r + f) % 2 === 1,
            rank: f === 0 ? 8 - r : null,
            file: r === 7 ? 'abcdefgh'[f] : null,
            label,
        };
    }),
);

// Dragging: pointer-down on your own piece picks it up; releasing over another square drops it there.
const boardEl = ref<HTMLElement | null>(null);
const drag = ref<{ from: number; piece: string; x: number; y: number; sx: number; sy: number; moved: boolean } | null>(null);
const ghostSize = ref(48);

function onPointerDown(e: PointerEvent, i: number) {
    if (props.locked || (e.pointerType === 'mouse' && e.button !== 0)) return;
    emit('square', i);
    const c = props.board[i];
    if (!isWhite(c) || !boardEl.value) return;
    ghostSize.value = boardEl.value.clientWidth / 8;
    drag.value = { from: i, piece: c, x: e.clientX, y: e.clientY, sx: e.clientX, sy: e.clientY, moved: false };
    boardEl.value.setPointerCapture(e.pointerId);
}

function onPointerMove(e: PointerEvent) {
    const d = drag.value;
    if (!d) return;
    d.x = e.clientX;
    d.y = e.clientY;
    if (!d.moved && Math.hypot(d.x - d.sx, d.y - d.sy) > 8) d.moved = true;
}

function onPointerUp(e: PointerEvent) {
    const d = drag.value;
    drag.value = null;
    if (!d?.moved) return;
    const el = document.elementFromPoint(e.clientX, e.clientY)?.closest<HTMLElement>('[data-sq]');
    if (el) {
        const to = Number(el.dataset.sq);
        if (to !== d.from) emit('drop', d.from, to);
    }
}

function onKey(e: KeyboardEvent, i: number) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (!props.locked) emit('square', i);
    }
}
</script>

<template>
    <div class="board-frame">
        <div
            ref="boardEl"
            class="board"
            :class="{ shake }"
            role="grid"
            aria-label="Chess board"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="drag = null"
        >
            <button
                v-for="s in squares"
                :key="s.i"
                type="button"
                class="sq"
                :class="{
                    dark: s.dark,
                    last: lastMove?.includes(s.i),
                    sel: s.i === selected,
                    tgt: targets.includes(s.i) && !isPiece(s.c),
                    cap: targets.includes(s.i) && isPiece(s.c),
                    danger: danger?.has(s.i),
                    alert: alert?.includes(s.i),
                    hint: hint?.includes(s.i),
                    check: s.i === checkSquare,
                    lifted: drag?.moved && drag.from === s.i,
                }"
                :data-sq="s.i"
                :aria-label="s.label"
                @pointerdown="onPointerDown($event, s.i)"
                @keydown="onKey($event, s.i)"
            >
                <span v-if="s.c === '*'" class="star"><StarIcon /></span>
                <span v-else-if="isPiece(s.c)" class="pc" :class="{ pop: s.i === popSquare }"><PieceIcon :piece="s.c" /></span>
                <span v-if="s.rank" class="coord rank">{{ s.rank }}</span>
                <span v-if="s.file" class="coord file">{{ s.file }}</span>
            </button>
        </div>
        <div
            v-if="drag?.moved"
            class="ghost"
            :style="{ width: ghostSize * 1.2 + 'px', height: ghostSize * 1.2 + 'px', left: drag.x + 'px', top: drag.y + 'px' }"
        >
            <PieceIcon :piece="drag.piece" />
        </div>
    </div>
</template>

<style scoped>
.board-frame {
    padding: clamp(5px, 1.4vmin, 10px);
    border-radius: clamp(12px, 2.4vmin, 20px);
    background: var(--ink);
    box-shadow: 0 6px 0 #0d1330;
}
.board {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    aspect-ratio: 1;
    width: 100%;
    border-radius: 10px;
    overflow: hidden;
    container-type: inline-size;
    user-select: none;
    -webkit-user-select: none;
    touch-action: none;
}
.sq {
    position: relative;
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    border: 0;
    padding: 0;
    background: var(--sq-light);
    cursor: pointer;
}
.sq.dark {
    background: var(--sq-dark);
}
.sq:focus-visible {
    outline: 3px solid var(--tang);
    outline-offset: -3px;
    z-index: 3;
}
.sq::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.sq.last::before {
    background: rgba(255, 210, 63, 0.45);
}
.sq.sel::before {
    background: rgba(255, 138, 61, 0.6);
}
.sq.danger::before {
    background: rgba(255, 90, 95, 0.55);
}
.sq.alert::before {
    background: rgba(255, 90, 95, 0.85);
    animation: blink 0.4s 3;
}
.sq.hint::before {
    background: rgba(63, 174, 115, 0.8);
    animation: blink 0.5s 3;
}
.sq.check::before {
    background: radial-gradient(circle, #ff5a5f 0 42%, transparent 72%);
}
.sq.tgt::after {
    content: '';
    position: absolute;
    width: 30%;
    height: 30%;
    border-radius: 50%;
    background: rgba(28, 37, 65, 0.35);
    pointer-events: none;
}
.sq.cap::after {
    content: '';
    position: absolute;
    inset: 4%;
    border-radius: 50%;
    border: 0.9cqw solid rgba(28, 37, 65, 0.45);
    pointer-events: none;
}
.pc {
    position: relative;
    z-index: 1;
    width: 92%;
    height: 92%;
    pointer-events: none;
    filter: drop-shadow(0 0.5cqw 0 rgba(28, 37, 65, 0.3));
}
.pc.pop {
    animation: pop 0.25s ease-out;
}
.sq.lifted .pc {
    opacity: 0.3;
}
.star {
    position: relative;
    z-index: 1;
    width: 62%;
    height: 62%;
    pointer-events: none;
    animation: twinkle 1.6s ease-in-out infinite;
}
.coord {
    position: absolute;
    z-index: 2;
    font-size: max(9px, 2.3cqw);
    font-weight: 800;
    line-height: 1;
    color: rgba(28, 37, 65, 0.55);
    pointer-events: none;
}
.rank {
    top: 3px;
    left: 4px;
}
.file {
    bottom: 3px;
    right: 5px;
}
.ghost {
    position: fixed;
    z-index: 50;
    transform: translate(-50%, -60%);
    pointer-events: none;
    filter: drop-shadow(0 6px 4px rgba(28, 37, 65, 0.35));
}
.shake {
    animation: shake 0.15s 3;
}
@keyframes twinkle {
    50% {
        transform: scale(1.12) rotate(8deg);
    }
}
@keyframes pop {
    0% {
        transform: scale(0.6);
    }
    70% {
        transform: scale(1.15);
    }
    100% {
        transform: scale(1);
    }
}
@keyframes blink {
    50% {
        opacity: 0.2;
    }
}
@keyframes shake {
    25% {
        transform: translateX(-6px);
    }
    75% {
        transform: translateX(6px);
    }
}
</style>
