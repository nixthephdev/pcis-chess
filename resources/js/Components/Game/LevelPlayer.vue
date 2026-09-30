<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    applyMove,
    attackerOf,
    Board,
    findWinningMove,
    guardedSquares,
    hasLegalMove,
    inCheck,
    isBlack,
    isWhite,
    kingSquare,
    legalMoves,
    pseudoMoves,
    squareName,
} from '@/game/engine';
import { isPuzzle, Level, nextLevel, parFor, worldById } from '@/game/catalog';
import { sfx } from '@/game/sound';
import { confetti } from '@/game/confetti';
import ChessBoard from './ChessBoard.vue';
import PieceIcon from './PieceIcon.vue';
import StarIcon from './StarIcon.vue';

const props = defineProps<{
    level: Level;
    starsFor: (levelId: string) => number;
}>();

const emit = defineEmits<{
    complete: [level: Level, stars: number, moves: number];
    open: [level: Level];
    exit: [];
}>();

const NAMES: Record<string, string> = { p: 'pawn', n: 'knight', b: 'bishop', r: 'rook', q: 'queen', k: 'king' };
const PRAISE = ['Awesome!', 'Brilliant!', 'Super star!', 'Chess wizard!', 'Fantastic!', 'You rock!'];
const GOT_STAR = ['Got it!', 'Shiny!', 'Nice one!', 'Star collected!'];
const pick = <T,>(a: T[]) => a[Math.floor(Math.random() * a.length)];

const world = computed(() => worldById(props.level.worldId)!);
const puzzle = computed(() => isPuzzle(props.level));
const par = computed(() => parFor(props.level));

const board = ref<Board>([]);
const selected = ref(-1);
const moves = ref(0);
const misses = ref(0);
const locked = ref(false);
const lastMove = ref<number[] | null>(null);
const danger = ref<Set<number> | null>(null);
const alert = ref<number[] | null>(null);
const hint = ref<number[] | null>(null);
const popSquare = ref(-1);
const shake = ref(false);
const bubble = ref({ text: '', mood: '' as '' | 'good' | 'bad' });
const result = ref<{ title: string; stars: number; message: string; next: Level | null } | null>(null);
const nextButton = ref<HTMLButtonElement | null>(null);

let timers: number[] = [];
const later = (fn: () => void, ms: number) => timers.push(window.setTimeout(fn, ms));
const clearTimers = () => {
    timers.forEach(clearTimeout);
    timers = [];
};
onBeforeUnmount(clearTimers);

function reset(message?: { text: string; mood: '' | 'good' | 'bad' }) {
    clearTimers();
    board.value = props.level.board.slice();
    selected.value = -1;
    moves.value = 0;
    misses.value = 0;
    locked.value = false;
    lastMove.value = null;
    danger.value = null;
    alert.value = null;
    hint.value = null;
    popSquare.value = -1;
    shake.value = false;
    result.value = null;
    bubble.value = message ?? { text: props.level.tip, mood: '' };
}

watch(() => props.level.id, () => reset(), { immediate: true });

const movesFor = (sq: number) => (puzzle.value ? legalMoves(board.value, sq) : pseudoMoves(board.value, sq));
const targets = computed(() => (selected.value >= 0 ? movesFor(selected.value) : []));
const checkSquare = computed(() => (puzzle.value && inCheck(board.value, 'b') ? kingSquare(board.value, 'b') : -1));

const goalText = computed(() => {
    const b = board.value;
    switch (props.level.goal) {
        case 'stars': {
            const n = b.filter((c) => c === '*').length;
            return `Collect all the stars · ${n} left`;
        }
        case 'capture': {
            const n = b.filter(isBlack).length;
            return `Capture every black piece · ${n} left`;
        }
        case 'check':
            return 'Give check in 1 move';
        default:
            return 'Checkmate in 1 move';
    }
});

function say(text: string, mood: '' | 'good' | 'bad' = '') {
    bubble.value = { text, mood };
}

function onSquare(i: number) {
    if (locked.value) return;
    hint.value = null;
    if (selected.value >= 0 && targets.value.includes(i)) {
        doMove(selected.value, i);
    } else if (isWhite(board.value[i])) {
        selected.value = selected.value === i ? -1 : i;
        sfx.tap();
    } else {
        selected.value = -1;
    }
}

function onDrop(from: number, to: number) {
    if (locked.value) return;
    if (movesFor(from).includes(to)) doMove(from, to);
    else selected.value = -1;
}

function doMove(from: number, to: number) {
    const before = board.value;
    const target = before[to];
    const after = applyMove(before, from, to);
    board.value = after;
    selected.value = -1;
    lastMove.value = [from, to];
    popSquare.value = to;
    moves.value++;

    if (!puzzle.value) {
        const attacker = attackerOf(after, to, 'b');
        if (attacker >= 0) return caught(attacker, to);

        const done = props.level.goal === 'stars' ? !after.includes('*') : !after.some(isBlack);
        if (done) return win();

        if (target === '*') {
            sfx.star();
            say(pick(GOT_STAR), 'good');
        } else if (isBlack(target)) {
            sfx.capture();
            say(`You captured the ${NAMES[target]}!`, 'good');
        } else {
            sfx.move();
        }
        return;
    }

    const check = inCheck(after, 'b');
    const solved = props.level.goal === 'check' ? check : check && !hasLegalMove(after, 'b');
    if (solved) return win();

    misses.value++;
    locked.value = true;
    sfx.oops();
    say(
        check
            ? 'That is check, but the king can still escape or be saved. Find checkmate!'
            : 'That move does not attack the king. Try another one!',
        'bad',
    );
    later(() => {
        board.value = before;
        lastMove.value = null;
        locked.value = false;
    }, 1300);
}

function caught(attacker: number, to: number) {
    locked.value = true;
    alert.value = [attacker, to];
    shake.value = true;
    sfx.oops();
    const text = `Oh no! The black ${NAMES[board.value[attacker]]} on ${squareName(attacker)} can capture you on ${squareName(to)}. Let's try again!`;
    say(text, 'bad');
    later(() => reset({ text: text + ' Tip: tap Hint to see the danger squares.', mood: 'bad' }), 2200);
}

function showHint() {
    if (locked.value) return;
    sfx.tap();
    if (puzzle.value) {
        const m = findWinningMove(board.value, props.level.goal as 'check' | 'mate');
        if (m) {
            hint.value = [m[0]];
            selected.value = -1;
            say(`Try moving your ${NAMES[board.value[m[0]].toLowerCase()]} on ${squareName(m[0])}.`);
        }
        return;
    }
    danger.value = guardedSquares(board.value);
    say("Red squares are guarded by black pieces. Don't land on them!");
    later(() => (danger.value = null), 2500);
}

function win() {
    locked.value = true;
    let stars: number;
    let message: string;
    if (puzzle.value) {
        stars = misses.value === 0 ? 3 : misses.value === 1 ? 2 : 1;
        message = stars === 3 ? 'Solved on your first try!' : 'Solved! Find it on your first try for 3 stars.';
    } else {
        const p = par.value ?? moves.value;
        stars = moves.value <= p ? 3 : moves.value <= p + 2 ? 2 : 1;
        message =
            stars === 3
                ? `Perfect! ${moves.value} moves is the best possible.`
                : `You used ${moves.value} moves. Can you do it in ${p} for 3 stars?`;
    }
    sfx.win();
    confetti();
    emit('complete', props.level, stars, moves.value);
    say(pick(PRAISE), 'good');
    later(async () => {
        result.value = { title: pick(PRAISE), stars, message, next: nextLevel(props.level) };
        await nextTick();
        nextButton.value?.focus();
    }, 500);
}

function goNext() {
    sfx.tap();
    const n = result.value?.next;
    if (n) emit('open', n);
    else emit('exit');
}

const levelUnlocked = (i: number) => i === 0 || props.starsFor(world.value.levels[i - 1].id) > 0;
</script>

<template>
    <div class="player">
        <header class="bar">
            <button type="button" class="btn btn-sm" @click="emit('exit')">← Map</button>
            <div class="min-w-0 flex-1">
                <h1 class="truncate font-display text-2xl font-extrabold leading-tight sm:text-3xl">{{ world.name }}</h1>
                <p class="text-sm font-bold text-ink-soft">Level {{ level.index + 1 }} of {{ world.levels.length }} · {{ world.blurb }}</p>
            </div>
            <nav class="pips" aria-label="Levels">
                <button
                    v-for="(l, i) in world.levels"
                    :key="l.id"
                    type="button"
                    class="pip"
                    :class="{ on: l.id === level.id }"
                    :disabled="!levelUnlocked(i)"
                    :aria-label="`Level ${i + 1}`"
                    :aria-current="l.id === level.id ? 'step' : undefined"
                    @click="emit('open', l)"
                >
                    {{ i + 1 }}
                    <span v-if="starsFor(l.id)" class="pip-stars">
                        <StarIcon v-for="n in 3" :key="n" :filled="n <= starsFor(l.id)" class="h-[11px] w-[11px]" />
                    </span>
                </button>
            </nav>
        </header>

        <div class="stage">
            <div class="coach">
                <div class="face"><PieceIcon piece="N" /></div>
                <p class="bubble chunky" :class="bubble.mood" aria-live="polite">{{ bubble.text }}</p>
            </div>

            <div class="board-slot">
                <ChessBoard
                    :board="board"
                    :selected="selected"
                    :targets="targets"
                    :last-move="lastMove"
                    :danger="danger"
                    :alert="alert"
                    :hint="hint"
                    :check-square="checkSquare"
                    :pop-square="popSquare"
                    :locked="locked"
                    :shake="shake"
                    @square="onSquare"
                    @drop="onDrop"
                />
            </div>

            <section class="panel chunky">
                <div>
                    <div class="label-caps">Your mission</div>
                    <div class="font-display text-xl font-extrabold leading-tight">{{ goalText }}</div>
                </div>
                <div class="stats">
                    <div class="stat">
                        <span class="label-caps">Moves</span>
                        <b>{{ moves }}</b>
                    </div>
                    <div class="stat">
                        <span class="label-caps">{{ puzzle ? '3-star try' : '3-star target' }}</span>
                        <b>{{ puzzle ? (misses === 0 ? 'First!' : 'Keep going') : (par ?? '–') }}</b>
                    </div>
                </div>
                <div class="actions">
                    <button type="button" class="btn" @click="(sfx.tap(), reset())">Restart</button>
                    <button type="button" class="btn btn-go" @click="showHint">Hint</button>
                </div>
            </section>
        </div>

        <div v-if="result" class="modal" role="dialog" aria-modal="true" aria-labelledby="win-title">
            <div class="card chunky">
                <h2 id="win-title" class="font-display text-4xl font-extrabold">{{ result.title }}</h2>
                <div class="big-stars">
                    <span v-for="n in 3" :key="n" class="big-star" :class="{ got: n <= result.stars }" :style="{ animationDelay: n * 0.15 + 's' }">
                        <StarIcon :filled="n <= result.stars" />
                    </span>
                </div>
                <p class="font-bold">{{ result.message }}</p>
                <div class="flex flex-wrap justify-center gap-3">
                    <button type="button" class="btn" @click="(sfx.tap(), reset())">Play again</button>
                    <button ref="nextButton" type="button" class="btn btn-go" @click="goNext">
                        {{ !result.next ? 'Back to map' : result.next.worldId === level.worldId ? 'Next level →' : `Next: ${worldById(result.next.worldId)?.name} →` }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.player {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 1100px;
    margin: 0 auto;
}
.bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 14px;
}
.pips {
    display: flex;
    flex: 1 1 100%;
    min-width: 0;
    gap: 8px;
    overflow-x: auto;
    padding: 2px 2px 12px;
}
@media (min-width: 640px) {
    .pips {
        flex: 0 1 auto;
    }
}
.pip {
    position: relative;
    flex: none;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    border: 3px solid var(--ink);
    background: var(--paper);
    font-family: 'Baloo 2', sans-serif;
    font-weight: 800;
    font-size: 1.1rem;
    box-shadow: 0 3px 0 var(--ink);
}
.pip.on {
    background: var(--sun);
}
.pip:disabled {
    opacity: 0.4;
    box-shadow: none;
    cursor: not-allowed;
}
.pip-stars {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -10px;
    display: flex;
    justify-content: center;
}

/* Phone / portrait tablet: coach, board, panel stacked. The board is sized to leave room for the rest on screen. */
.stage {
    display: grid;
    gap: 14px;
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas: 'coach' 'board' 'panel';
}
.coach {
    grid-area: coach;
    display: grid;
    grid-template-columns: 52px 1fr;
    gap: 12px;
    align-items: start;
}
.face {
    width: 52px;
    height: 52px;
    padding: 4px;
    border-radius: 50%;
    background: var(--sun);
    border: 3px solid var(--ink);
    box-shadow: 0 4px 0 var(--ink);
}
.bubble {
    position: relative;
    margin: 0;
    padding: 10px 14px;
    font-weight: 700;
    font-size: 1rem;
    line-height: 1.4;
    min-height: 52px;
}
.bubble::before {
    content: '';
    position: absolute;
    left: -13px;
    top: 14px;
    border: 10px solid transparent;
    border-right-color: var(--ink);
    border-left: 0;
}
.bubble.good {
    background: #e3f8ec;
}
.bubble.bad {
    background: #ffe6e7;
}
.board-slot {
    grid-area: board;
    width: min(100%, max(280px, calc(100dvh - 330px)));
    justify-self: center;
}
.panel {
    grid-area: panel;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px 16px;
}
.stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.stat {
    border: 2px dashed var(--ink-soft);
    border-radius: 12px;
    padding: 6px 10px;
}
.stat b {
    display: block;
    font-family: 'Baloo 2', sans-serif;
    font-size: 1.5rem;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}
.actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

/* Landscape tablets and desktops: board on the left, coach and panel beside it. */
@media (min-width: 720px) and (min-aspect-ratio: 1/1) {
    .stage {
        grid-template-columns: auto minmax(260px, 380px);
        grid-template-areas: 'board coach' 'board panel';
        grid-template-rows: auto 1fr;
        align-items: start;
        justify-content: center;
        gap: 18px 24px;
    }
    .board-slot {
        width: min(calc(100dvh - 150px), 62vw, 680px);
        min-width: 300px;
    }
    .coach {
        grid-template-columns: 68px 1fr;
    }
    .face {
        width: 68px;
        height: 68px;
    }
    .bubble {
        font-size: 1.08rem;
        min-height: 72px;
        padding: 14px 16px;
    }
    .bubble::before {
        top: 22px;
    }
}

.modal {
    position: fixed;
    inset: 0;
    z-index: 40;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(28, 37, 65, 0.55);
}
.card {
    width: min(420px, 100%);
    padding: 24px;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 14px;
    animation: pop 0.3s ease-out;
}
.big-stars {
    display: flex;
    justify-content: center;
    gap: 8px;
}
.big-star {
    width: 64px;
    height: 64px;
}
.big-star:nth-child(2) {
    transform: translateY(-10px);
}
.big-star.got {
    animation: pop 0.4s ease-out backwards;
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
</style>
