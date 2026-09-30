<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    CalendarCheck,
    Check,
    ChevronLeft,
    ChevronRight,
    Crown,
    Flame,
    GraduationCap,
    Lightbulb,
    Plus,
    RotateCcw,
    Search,
    Sparkles,
    Star,
    Swords,
    Target,
    Trophy,
    Users,
    X,
} from 'lucide-vue-next';
import { applyMove, attackerOf, Board, inCheck, isBlack, isPiece, isWhite, kingSquare, legalMoves, squareName } from '@/game/engine';
import {
    evaluate,
    Evaluation,
    fromFen,
    Grade,
    gradeIndex,
    GRADES,
    isMated,
    Lesson,
    LESSONS,
    PUZZLES,
    san,
    sq,
    Tier,
    tierOf,
    uci,
    VALUES,
    yearOf,
} from '@/game/club';
import { confetti } from '@/game/confetti';
import { sfx } from '@/game/sound';
import PieceIcon from '@/Components/Game/PieceIcon.vue';
import StarIcon from '@/Components/Game/StarIcon.vue';

// ------------------------------------------------------------------ storage (this browser only)

function load<T>(key: string, fallback: T): T {
    try {
        const raw = localStorage.getItem(key);
        return raw ? { ...fallback, ...JSON.parse(raw) } : fallback;
    } catch {
        return fallback;
    }
}
function save(key: string, value: unknown) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        /* storage blocked: this visit only */
    }
}

// ------------------------------------------------------------------ grade tier

const saved = load<{ grade: string }>('chessclub.grade', { grade: 'PYP 3' });
const grade = ref<Grade>((GRADES as readonly string[]).includes(saved.grade) ? (saved.grade as Grade) : 'PYP 3');
const tier = computed<Tier>(() => tierOf(grade.value));
const explorer = computed(() => tier.value === 'explorer');
watch(grade, (g) => save('chessclub.grade', { grade: g }));

type Tab = 'arena' | 'lessons' | 'roster' | 'leaderboard';
const tab = ref<Tab>('arena');
const TABS = computed(() => [
    { id: 'arena' as const, icon: Swords, label: explorer.value ? 'Puzzle Arena' : 'Play & Puzzles' },
    { id: 'lessons' as const, icon: BookOpen, label: 'Lessons' },
    { id: 'roster' as const, icon: Users, label: explorer.value ? 'Club Register' : 'Roster & Attendance' },
    { id: 'leaderboard' as const, icon: Trophy, label: explorer.value ? 'Stars & Badges' : 'Leaderboard & XP' },
]);

// ------------------------------------------------------------------ weeks

const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
function mondayOf(d: Date, weeksBack = 0) {
    const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    x.setDate(x.getDate() - ((x.getDay() + 6) % 7) - 7 * weeksBack);
    return x;
}
const THIS_WEEK = ymd(mondayOf(new Date()));
const WEEKS = [3, 2, 1, 0].map((n) => {
    const d = mondayOf(new Date(), n);
    return { key: ymd(d), label: n === 0 ? 'This week' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) };
});

// ------------------------------------------------------------------ your progress, XP and badges

interface Progress {
    xp: number;
    solved: Record<string, { firstTry: boolean }>;
    lessons: string[];
    weekly: Record<string, { puzzles: number; lessons: number; xp: number }>;
}
const progress = reactive<Progress>(load('chessclub.progress.v1', { xp: 0, solved: {}, lessons: [], weekly: {} }));
watch(progress, (p) => save('chessclub.progress.v1', p), { deep: true });

const week = computed(() => progress.weekly[THIS_WEEK] ?? { puzzles: 0, lessons: 0, xp: 0 });
const level = computed(() => Math.floor(progress.xp / 100) + 1);

const solvedIn = (t: Tier) => PUZZLES.filter((p) => p.tier === t && progress.solved[p.id]);
const BADGES = [
    { id: 'first-star', tier: 'explorer', name: 'First Star', desc: 'Solve your first puzzle', icon: Star, test: () => solvedIn('explorer').length >= 1 },
    { id: 'knight-rider', tier: 'explorer', name: 'Knight Rider', desc: "Solve The Knight's L-shaped Jump", icon: Sparkles, test: () => !!progress.solved['ex-knight-jump'] },
    { id: 'castle-keeper', tier: 'explorer', name: 'Castle Keeper', desc: 'Solve Protect the Castle!', icon: Crown, test: () => !!progress.solved['ex-back-rank'] },
    {
        id: 'mate-champ',
        tier: 'explorer',
        name: 'Checkmate Champ',
        desc: 'Solve every checkmate puzzle',
        icon: Trophy,
        test: () => PUZZLES.filter((p) => p.tier === 'explorer' && p.goal === 'mate').every((p) => progress.solved[p.id]),
    },
    {
        id: 'bookworm',
        tier: 'explorer',
        name: 'Bookworm',
        desc: 'Finish 3 lessons',
        icon: BookOpen,
        test: () => LESSONS.filter((l) => l.tier === 'explorer' && progress.lessons.includes(l.id)).length >= 3,
    },
    { id: 'tactician', tier: 'grandmaster', name: 'Tactician', desc: 'Solve 3 combinations', icon: Swords, test: () => solvedIn('grandmaster').length >= 3 },
    {
        id: 'clean-sheet',
        tier: 'grandmaster',
        name: 'Clean Sheet',
        desc: 'Solve a combination at 100% accuracy',
        icon: Target,
        test: () => solvedIn('grandmaster').some((p) => progress.solved[p.id].firstTry),
    },
    {
        id: 'theorist',
        tier: 'grandmaster',
        name: 'Opening Theorist',
        desc: 'Finish Control the Center and The Sicilian Defence',
        icon: BookOpen,
        test: () => progress.lessons.includes('center') && progress.lessons.includes('sicilian'),
    },
    {
        id: 'technician',
        tier: 'grandmaster',
        name: 'Endgame Technician',
        desc: 'Solve Rook Roller and finish Building a Bridge',
        icon: Crown,
        test: () => !!progress.solved['gm-rook-roller'] && progress.lessons.includes('lucena'),
    },
    {
        id: 'arsenal',
        tier: 'grandmaster',
        name: 'Complete Arsenal',
        desc: 'Solve every combination',
        icon: Trophy,
        test: () => PUZZLES.filter((p) => p.tier === 'grandmaster').every((p) => progress.solved[p.id]),
    },
] as const;
const earnedIds = () => BADGES.filter((b) => b.test()).map((b) => b.id as string);
const tierBadges = computed(() => BADGES.filter((b) => b.tier === tier.value).map((b) => ({ ...b, earned: b.test() })));

const CHALLENGES = computed(() =>
    explorer.value
        ? [
              { label: 'Solve 3 puzzles', have: week.value.puzzles, need: 3 },
              { label: 'Finish a lesson', have: week.value.lessons, need: 1 },
              { label: 'Earn 100 XP', have: week.value.xp, need: 100 },
          ]
        : [
              { label: 'Solve 4 combinations', have: week.value.puzzles, need: 4 },
              { label: 'Complete 2 lessons', have: week.value.lessons, need: 2 },
              { label: 'Earn 150 XP', have: week.value.xp, need: 150 },
          ],
);

const toasts = ref<{ id: number; text: string }[]>([]);
let toastId = 0;
function toast(text: string) {
    const id = ++toastId;
    toasts.value.push({ id, text });
    later(() => (toasts.value = toasts.value.filter((t) => t.id !== id)), 3200);
}

function award(xp: number, kind: 'puzzles' | 'lessons') {
    const before = earnedIds();
    const w = (progress.weekly[THIS_WEEK] ??= { puzzles: 0, lessons: 0, xp: 0 });
    progress.xp += xp;
    w.xp += xp;
    w[kind]++;
    for (const b of BADGES) if (!before.includes(b.id) && b.test()) toast(`Badge unlocked: ${b.name}!`);
}

// ------------------------------------------------------------------ timers

let timers: number[] = [];
function later(fn: () => void, ms: number) {
    timers.push(window.setTimeout(fn, ms));
}
onBeforeUnmount(() => timers.forEach(clearTimeout));

// ------------------------------------------------------------------ puzzle arena

const tierPuzzles = computed(() => PUZZLES.filter((p) => p.tier === tier.value));
const puzzleIdx = ref(0);
const puzzle = computed(() => tierPuzzles.value[puzzleIdx.value % tierPuzzles.value.length]);

const pz = reactive({
    board: [] as Board,
    step: 0,
    history: [] as string[],
    lastMove: null as number[] | null,
    locked: false,
    correct: 0,
    wrong: 0,
    hintLevel: 0,
    solved: false,
    message: '',
    mood: '' as '' | 'good' | 'bad',
    shake: false,
    pop: 0,
    result: null as null | { stars: number; accuracy: number; xp: number },
});
const evalRes = ref<Evaluation | null>(null);
let evalToken = 0;

function runEval() {
    if (explorer.value) return;
    const token = ++evalToken;
    const b = pz.board;
    const side = pz.step % 2 === 0 ? 'w' : 'b';
    evalRes.value = null;
    // Let the move render first; the look-ahead takes a moment.
    later(() => {
        if (token === evalToken) evalRes.value = pz.solved ? evaluate(b, side, 2) : evaluate(b, side);
    }, 60);
}

function resetPuzzle() {
    Object.assign(pz, {
        board: fromFen(puzzle.value.fen),
        step: 0,
        history: [],
        lastMove: null,
        locked: false,
        correct: 0,
        wrong: 0,
        hintLevel: 0,
        solved: false,
        message: puzzle.value.story,
        mood: '',
        shake: false,
        pop: 0,
        result: null,
    });
    selected.value = -1;
    runEval();
}

watch(tier, () => (puzzleIdx.value = 0));
watch(puzzle, resetPuzzle);

const accuracy = computed(() => (pz.correct + pz.wrong ? Math.round((pz.correct / (pz.correct + pz.wrong)) * 100) : 100));
const turnText = computed(() => {
    if (pz.solved) return explorer.value ? 'Puzzle solved!' : 'Solved';
    if (pz.step % 2) return explorer.value ? 'Black is thinking…' : 'Black to move';
    return explorer.value ? 'Your turn! (White)' : 'White to move';
});
const moveRows = computed(() => {
    const rows: { n: number; w: string; b: string }[] = [];
    for (let i = 0; i < pz.history.length; i += 2) rows.push({ n: i / 2 + 1, w: pz.history[i], b: pz.history[i + 1] ?? '' });
    return rows;
});

const WRONG_EXPLORER = ['Oops, not that one. Try again!', 'Almost! Look again carefully.', 'Hmm, that one does not work. You can do it!'];
const WRONG_GM = ['Inaccuracy: that lets Black off the hook. Recalculate.', 'Not the strongest continuation. Check forcing moves first.', 'Black holds after that. Look for checks, captures, threats.'];
const pick = <T,>(a: T[]) => a[Math.floor(Math.random() * a.length)];

function puzzleMove(from: number, to: number) {
    const p = puzzle.value;
    const [ef, et] = uci(p.solution[pz.step]);
    const after = applyMove(pz.board, from, to);
    const last = pz.step === p.solution.length - 1;
    const ok = (from === ef && to === et) || (last && p.goal === 'mate' && isMated(after, 'b'));

    if (!ok) {
        pz.wrong++;
        pz.message = pick(explorer.value ? WRONG_EXPLORER : WRONG_GM);
        pz.mood = 'bad';
        pz.shake = true;
        later(() => (pz.shake = false), 450);
        sfx.oops();
        return;
    }

    const capture = isPiece(pz.board[to]);
    pz.history.push(san(pz.board, from, to));
    pz.board = after;
    pz.lastMove = [from, to];
    pz.step++;
    pz.correct++;
    pz.hintLevel = 0;
    pz.pop++;
    if (capture) sfx.capture();
    else sfx.move();

    if (pz.step >= p.solution.length) return solvePuzzle();

    pz.locked = true;
    pz.message = explorer.value ? 'Great move! Now Black moves…' : 'Correct. Black replies…';
    pz.mood = 'good';
    runEval();
    later(() => {
        const [rf, rt] = uci(p.solution[pz.step]);
        pz.history.push(san(pz.board, rf, rt));
        pz.board = applyMove(pz.board, rf, rt);
        pz.lastMove = [rf, rt];
        pz.step++;
        pz.locked = false;
        pz.message = explorer.value ? 'Your turn again. Finish it!' : 'Find the follow-up.';
        pz.mood = '';
        sfx.move();
        runEval();
    }, 750);
}

function solvePuzzle() {
    const p = puzzle.value;
    const firstTry = pz.wrong === 0;
    const prev = progress.solved[p.id];
    const xp = !prev ? (firstTry ? 30 : 15) : 5;
    progress.solved[p.id] = { firstTry: firstTry || !!prev?.firstTry };
    award(xp, 'puzzles');

    pz.solved = true;
    pz.locked = true;
    pz.mood = 'good';
    pz.message = explorer.value ? 'You did it!' : `Solved: ${p.theme}.`;
    sfx.win();
    if (explorer.value) confetti();
    runEval();
    later(() => (pz.result = { stars: pz.wrong === 0 ? 3 : pz.wrong === 1 ? 2 : 1, accuracy: accuracy.value, xp }), 450);
}

function showHint() {
    if (pz.locked) return;
    sfx.tap();
    pz.hintLevel = Math.min(pz.hintLevel + 1, 2);
    pz.message = puzzle.value.hint;
    pz.mood = '';
}
const hintSquares = computed(() => {
    if (tab.value !== 'arena' || pz.solved || pz.locked) return [];
    // Explorers see the piece straight away; grandmasters get the idea first, then the piece.
    if (pz.hintLevel >= (explorer.value ? 1 : 2)) return [uci(puzzle.value.solution[pz.step])[0]];
    return [];
});

function nextPuzzle() {
    sfx.tap();
    pz.result = null;
    if (puzzleIdx.value + 1 >= tierPuzzles.value.length) puzzleIdx.value = 0;
    else puzzleIdx.value++;
    if (tierPuzzles.value.length === 1) resetPuzzle();
}

// ------------------------------------------------------------------ lessons

const tierLessons = computed(() =>
    LESSONS.filter((l) => l.tier === tier.value).map((l) => ({ ...l, open: gradeIndex(l.from) <= gradeIndex(grade.value) })),
);
const lesson = ref<Lesson | null>(null);
const ls = reactive({
    step: 0,
    board: [] as Board,
    lastMove: null as number[] | null,
    done: false,
    locked: false,
    message: '',
    mood: '' as '' | 'good' | 'bad',
    shake: false,
    finished: false,
});
const lessonStep = computed(() => lesson.value?.steps[ls.step] ?? null);

function openLesson(l: Lesson) {
    sfx.tap();
    lesson.value = l;
    loadStep(0);
}
function loadStep(i: number) {
    const s = lesson.value!.steps[i];
    Object.assign(ls, { step: i, board: fromFen(s.fen), lastMove: null, done: !s.task, locked: false, message: s.task?.prompt ?? '', mood: '', shake: false, finished: false });
    selected.value = -1;
}
watch(tier, () => (lesson.value = null));

function lessonMove(from: number, to: number) {
    const task = lessonStep.value?.task;
    if (!task || ls.done) return;
    const [tf, tt] = uci(task.move);
    if (from !== tf || to !== tt) {
        ls.message = (explorer.value ? 'Not quite! ' : 'Not this one. ') + task.prompt;
        ls.mood = 'bad';
        ls.shake = true;
        later(() => (ls.shake = false), 450);
        sfx.oops();
        return;
    }
    if (isPiece(ls.board[to]) || ls.board[to] === '*') sfx.star();
    else sfx.move();
    ls.board = applyMove(ls.board, from, to);
    ls.lastMove = [from, to];
    ls.done = true;
    ls.message = explorer.value ? 'Yes! Well done!' : 'Correct.';
    ls.mood = 'good';
    if (task.reply) {
        ls.locked = true;
        const [rf, rt] = uci(task.reply);
        later(() => {
            ls.message += ` Black replies ${san(ls.board, rf, rt)}.`;
            ls.board = applyMove(ls.board, rf, rt);
            ls.lastMove = [rf, rt];
            ls.locked = false;
            sfx.move();
        }, 700);
    }
}

function nextStep() {
    sfx.tap();
    const l = lesson.value!;
    if (ls.step + 1 < l.steps.length) return loadStep(ls.step + 1);
    ls.finished = true;
    if (!progress.lessons.includes(l.id)) {
        progress.lessons.push(l.id);
        award(50, 'lessons');
    }
    if (explorer.value) confetti();
    sfx.win();
}

// ------------------------------------------------------------------ the board (shared by arena and lessons)

const selected = ref(-1);
watch(tab, () => (selected.value = -1));

const boardView = computed(() => {
    if (tab.value === 'lessons' && lesson.value) {
        const s = lessonStep.value!;
        return { board: ls.board, lastMove: ls.lastMove, locked: ls.locked || ls.finished, shake: ls.shake, highlight: (s.highlight ?? []).map(sq) };
    }
    return { board: pz.board, lastMove: pz.lastMove, locked: pz.locked, shake: pz.shake, highlight: [] as number[] };
});
const targets = computed(() => (selected.value >= 0 ? legalMoves(boardView.value.board, selected.value) : []));
const checkSquare = computed(() => {
    const b = boardView.value.board;
    for (const s of ['w', 'b'] as const) if (inCheck(b, s)) return kingSquare(b, s);
    return -1;
});
/** Grandmaster marker: black pieces attacked by White and not defended. */
const hanging = computed(() => {
    if (explorer.value || tab.value !== 'arena' || pz.solved) return new Set<number>();
    const b = pz.board;
    const out = new Set<number>();
    for (let i = 0; i < 64; i++) if (isBlack(b[i]) && b[i] !== 'k' && attackerOf(b, i, 'w') >= 0 && attackerOf(b, i, 'b') < 0) out.add(i);
    return out;
});

const PIECE_NAMES: Record<string, string> = { p: 'Pawn', n: 'Knight', b: 'Bishop', r: 'Rook', q: 'Queen', k: 'King' };
const squares = computed(() =>
    boardView.value.board.map((c, i) => {
        const r = i >> 3;
        const f = i & 7;
        let label = squareName(i);
        if (c === '*') label += ', star';
        else if (isPiece(c)) label += `, ${isWhite(c) ? 'white' : 'black'} ${PIECE_NAMES[c.toLowerCase()].toLowerCase()}`;
        if (targets.value.includes(i)) label += ', legal move';
        return { i, c, dark: (r + f) % 2 === 1, rank: f === 0 ? 8 - r : null, file: r === 7 ? 'abcdefgh'[f] : null, label };
    }),
);

function onSquare(i: number) {
    const v = boardView.value;
    if (v.locked) return;
    if (selected.value >= 0 && targets.value.includes(i)) {
        const from = selected.value;
        selected.value = -1;
        if (tab.value === 'arena') puzzleMove(from, i);
        else lessonMove(from, i);
        return;
    }
    if (isWhite(v.board[i])) {
        selected.value = selected.value === i ? -1 : i;
        sfx.tap();
    } else {
        selected.value = -1;
    }
}

/** Explorer piece-value tag for the selected piece, e.g. "Knight = 3 pts". */
const valueTag = computed(() => {
    const c = boardView.value.board[selected.value];
    if (!explorer.value || !c || !isPiece(c)) return null;
    const t = c.toLowerCase();
    return { piece: c, text: t === 'k' ? 'King = priceless!' : `${PIECE_NAMES[t]} = ${VALUES[t]} pt${VALUES[t] === 1 ? '' : 's'}` };
});
const evalPct = computed(() => (evalRes.value ? 50 + evalRes.value.score * 5 : 50));

// ------------------------------------------------------------------ roster & attendance

type Mark = 'P' | 'L' | 'A';
interface Member {
    id: number;
    name: string;
    grade: Grade | null;
    status: 'Registered' | 'Pending';
    attendance: Record<string, Mark>;
}

function seedRoster(): Member[] {
    const rows: [string, Grade | null, boolean][] = [
        // First name and last initial only: this file is public. Coaches can add full names on the page (kept in their browser).
        ['Jaden J.', 'MYP 3', true],
        ['Levi A.', 'PYP 5', true],
        ['Gabriel D.', 'MYP 3', true],
        ['Ayesha C.', 'MYP 2', true],
        ['Sean P.', 'MYP 3', true],
        ['Shaun M.', 'PYP 3', true],
        ['Ahmed C.', null, false],
        ['Maria C.', null, false],
        ['Zac R.', null, false],
        ['Asher A.', null, false],
    ];
    return rows.map(([name, g, present], i) => ({
        id: i + 1,
        name,
        grade: g,
        status: present ? 'Registered' : 'Pending',
        attendance: present ? { [THIS_WEEK]: 'P' } : {},
    }));
}
const club = reactive(load('chessclub.roster.v2', { members: seedRoster() }));
watch(club, (c) => save('chessclub.roster.v2', c), { deep: true });
// v1 seeded full names; don't leave them behind in the browser.
try {
    localStorage.removeItem('chessclub.roster.v1');
} catch {
    /* storage blocked */
}

const rosterQuery = ref('');
const rosterFilter = ref<'all' | Tier>('all');
const shownMembers = computed(() => {
    const q = rosterQuery.value.trim().toLowerCase();
    return club.members
        .filter((m) => !q || m.name.toLowerCase().includes(q))
        .filter((m) => rosterFilter.value === 'all' || (m.grade && tierOf(m.grade) === rosterFilter.value))
        .sort((a, b) => a.name.localeCompare(b.name));
});

const markLabel = (m?: Mark) => (m === 'P' ? 'present' : m === 'L' ? 'late' : m === 'A' ? 'absent' : 'not marked');
const NEXT_MARK: Record<string, Mark | undefined> = { '': 'P', P: 'L', L: 'A', A: undefined };
function cycleMark(m: Member, week: string) {
    const next = NEXT_MARK[m.attendance[week] ?? ''];
    if (next) m.attendance[week] = next;
    else delete m.attendance[week];
}

const newMember = reactive({ name: '', grade: grade.value as Grade });
function addMember() {
    const name = newMember.name.trim().replace(/\s+/g, ' ');
    if (!name) return;
    club.members.push({ id: Math.max(0, ...club.members.map((m) => m.id)) + 1, name, grade: newMember.grade, status: 'Pending', attendance: {} });
    newMember.name = '';
    toast(`Added ${name}.`);
}

const armed = ref<number | null>(null);
function removeMember(m: Member) {
    if (armed.value !== m.id) {
        armed.value = m.id;
        later(() => armed.value === m.id && (armed.value = null), 4000);
        return;
    }
    club.members = club.members.filter((x) => x.id !== m.id);
    armed.value = null;
}

function rateOf(m: Member) {
    const marks = WEEKS.map((w) => m.attendance[w.key]).filter(Boolean);
    return marks.length ? Math.round((marks.filter((x) => x !== 'A').length / marks.length) * 100) : null;
}
const rosterStats = computed(() => {
    const ms = club.members;
    const marked = ms.flatMap((m) => WEEKS.map((w) => m.attendance[w.key]).filter(Boolean));
    return {
        total: ms.length,
        registered: ms.filter((m) => m.status === 'Registered').length,
        presentNow: ms.filter((m) => m.attendance[THIS_WEEK] === 'P' || m.attendance[THIS_WEEK] === 'L').length,
        rate: marked.length ? Math.round((marked.filter((x) => x !== 'A').length / marked.length) * 100) : null,
    };
});

// ------------------------------------------------------------------ leaderboard

/** Consecutive weeks attended, counting back from this week (or last week if this week is not marked yet). */
function streakOf(m: Member) {
    let n = 0;
    for (let back = m.attendance[THIS_WEEK] ? 0 : 1; back < 52; back++) {
        const mark = m.attendance[ymd(mondayOf(new Date(), back))];
        if (mark === 'P' || mark === 'L') n++;
        else break;
    }
    return n;
}
function sessionsOf(m: Member) {
    return Object.values(m.attendance).filter((x) => x !== 'A').length;
}
const XP_RULES = { P: 50, L: 30, streak: 20 };
function memberXp(m: Member) {
    const a = Object.values(m.attendance);
    return a.filter((x) => x === 'P').length * XP_RULES.P + a.filter((x) => x === 'L').length * XP_RULES.L + streakOf(m) * XP_RULES.streak;
}
function memberBadges(m: Member) {
    const s = sessionsOf(m);
    return [
        s >= 1 && { name: 'First Session', icon: CalendarCheck },
        s >= 3 && { name: 'Regular: 3+ sessions', icon: Star },
        streakOf(m) >= 3 && { name: 'On Fire: 3-week streak', icon: Flame },
    ].filter(Boolean) as { name: string; icon: typeof Star }[];
}

const boardFilter = ref<'all' | Tier>('all');
const ranking = computed(() =>
    club.members
        .filter((m) => boardFilter.value === 'all' || (m.grade && tierOf(m.grade) === boardFilter.value))
        .map((m) => ({ ...m, xp: memberXp(m), streak: streakOf(m), badges: memberBadges(m) }))
        .sort((a, b) => b.xp - a.xp || a.name.localeCompare(b.name)),
);
const byGrade = computed(() =>
    GRADES.map((g) => {
        const ms = club.members.filter((m) => m.grade === g);
        return { grade: g, members: ms.length, badges: ms.reduce((n, m) => n + memberBadges(m).length, 0), xp: ms.reduce((n, m) => n + memberXp(m), 0) };
    }).filter((g) => g.members),
);

resetPuzzle();
</script>

<template>
    <Head title="Chess Club" />

    <div class="club" :class="explorer ? 'tier-explorer' : 'tier-gm'">
        <!-- Header -->
        <header class="top">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <Link href="/" class="flex min-w-0 items-center gap-3">
                    <span class="logo"><PieceIcon :piece="explorer ? 'N' : 'K'" /></span>
                    <span class="min-w-0">
                        <span class="title block truncate">Chess Club</span>
                        <span class="block truncate text-xs font-bold uppercase tracking-widest text-[var(--muted)]">
                            {{ explorer ? 'Explorer Mode' : 'Grandmaster Mode' }} · Year {{ yearOf(grade) }}
                        </span>
                    </span>
                </Link>

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <span class="chip" :title="`Level ${level}`"><Sparkles :size="16" /> {{ progress.xp }} XP</span>
                    <label class="grade-picker">
                        <GraduationCap :size="18" aria-hidden="true" />
                        <span class="sr-only">Grade level</span>
                        <select v-model="grade">
                            <optgroup label="Primary Years (PYP)">
                                <option v-for="g in GRADES.slice(0, 3)" :key="g" :value="g">{{ g }}</option>
                            </optgroup>
                            <optgroup label="Middle Years (MYP)">
                                <option v-for="g in GRADES.slice(3)" :key="g" :value="g">{{ g }}</option>
                            </optgroup>
                        </select>
                    </label>
                </div>
            </div>

            <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6" aria-label="Sections">
                <button
                    v-for="t in TABS"
                    :key="t.id"
                    type="button"
                    class="tab"
                    :class="{ on: tab === t.id }"
                    :aria-current="tab === t.id ? 'page' : undefined"
                    @click="(tab = t.id), sfx.tap()"
                >
                    <component :is="t.icon" :size="18" aria-hidden="true" /> {{ t.label }}
                </button>
            </nav>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            <!-- Lesson list -->
            <section v-if="tab === 'lessons' && !lesson" class="flex flex-col gap-4">
                <div>
                    <h1 class="heading">{{ explorer ? 'Learn with the Knight' : 'Study Plan' }}</h1>
                    <p class="text-[var(--muted)]">
                        {{ explorer ? `Lessons picked for ${grade}. Finish one to earn 50 XP!` : `Curriculum for ${grade}. Each lesson is worth 50 XP.` }}
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="l in tierLessons"
                        :key="l.id"
                        type="button"
                        class="panel lesson-card"
                        :disabled="!l.open"
                        @click="openLesson(l)"
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="text-lg font-extrabold leading-tight">{{ l.title }}</span>
                            <Check v-if="progress.lessons.includes(l.id)" class="shrink-0 text-[var(--good)]" :size="22" aria-label="Completed" />
                        </span>
                        <span class="text-sm text-[var(--muted)]">{{ l.blurb }}</span>
                        <span class="mt-auto flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[var(--muted)]">
                            {{ l.steps.length }} steps · {{ l.open ? `From ${l.from}` : `Unlocks in ${l.from}` }}
                        </span>
                    </button>
                </div>
            </section>

            <!-- Board screens: puzzle arena and an open lesson -->
            <section v-else-if="tab === 'arena' || tab === 'lessons'" class="play">
                <div class="board-col">
                    <div class="flex items-center justify-between gap-2">
                        <span class="turn" :class="{ black: tab === 'arena' && pz.step % 2 === 1 && !pz.solved }">
                            <span class="dot" /> {{ tab === 'arena' ? turnText : lesson!.title }}
                        </span>
                        <span v-if="valueTag" class="value-tag">
                            <span class="h-6 w-6"><PieceIcon :piece="valueTag.piece" /></span>{{ valueTag.text }}
                        </span>
                    </div>

                    <div class="board-row">
                        <div v-if="!explorer && tab === 'arena'" class="eval" :aria-label="`Evaluation ${evalRes?.label ?? 'calculating'}`">
                            <div class="eval-white" :style="{ height: evalPct + '%' }" />
                            <span class="eval-label" :class="evalPct >= 50 ? 'bottom' : 'top'">{{ evalRes?.label ?? '…' }}</span>
                        </div>

                        <div class="board-frame">
                            <div class="board" :class="{ shake: boardView.shake }" role="grid" aria-label="Chess board">
                                <button
                                    v-for="s in squares"
                                    :key="s.i"
                                    type="button"
                                    class="sq"
                                    :class="{
                                        dark: s.dark,
                                        last: boardView.lastMove?.includes(s.i),
                                        sel: s.i === selected,
                                        tgt: targets.includes(s.i) && !isPiece(s.c),
                                        cap: targets.includes(s.i) && isPiece(s.c),
                                        hl: boardView.highlight.includes(s.i),
                                        hint: tab === 'arena' && hintSquares.includes(s.i),
                                        check: s.i === checkSquare,
                                    }"
                                    :aria-label="s.label"
                                    @click="onSquare(s.i)"
                                >
                                    <span v-if="s.c === '*'" class="star"><StarIcon /></span>
                                    <span v-else-if="isPiece(s.c)" class="pc"><PieceIcon :piece="s.c" /></span>
                                    <span v-if="hanging.has(s.i)" class="marker" title="Undefended and attacked">!</span>
                                    <span v-if="s.rank" class="coord rank">{{ s.rank }}</span>
                                    <span v-if="s.file" class="coord file">{{ s.file }}</span>
                                </button>
                            </div>
                            <span v-if="explorer && tab === 'arena' && pz.pop" :key="pz.pop" class="pop-star"><Star :size="28" fill="currentColor" /> Nice!</span>
                        </div>
                    </div>
                </div>

                <!-- Arena side panel -->
                <aside v-if="tab === 'arena'" class="side">
                    <div class="panel flex flex-col gap-3 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="chip small">{{ puzzle.theme }}</span>
                            <span class="text-sm font-bold text-[var(--muted)]">Puzzle {{ (puzzleIdx % tierPuzzles.length) + 1 }} / {{ tierPuzzles.length }}</span>
                        </div>
                        <h1 class="heading">{{ puzzle.title }}</h1>
                        <p class="feedback" :class="pz.mood" aria-live="polite">{{ pz.message }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" class="btn-c" :disabled="pz.locked" @click="showHint"><Lightbulb :size="18" /> Hint</button>
                            <button type="button" class="btn-c" @click="(sfx.tap(), resetPuzzle())"><RotateCcw :size="18" /> Reset</button>
                            <button type="button" class="btn-c primary" @click="nextPuzzle">Next <ChevronRight :size="18" /></button>
                        </div>
                    </div>

                    <div v-if="explorer" class="panel flex flex-col gap-2 p-4">
                        <h2 class="subheading">Piece points</h2>
                        <div class="grid grid-cols-3 gap-2 text-sm font-bold">
                            <span v-for="p in ['P', 'N', 'B', 'R', 'Q', 'K']" :key="p" class="flex items-center gap-1">
                                <span class="h-7 w-7"><PieceIcon :piece="p" /></span>
                                {{ p === 'K' ? '∞' : VALUES[p.toLowerCase()] }}
                            </span>
                        </div>
                        <p class="text-sm text-[var(--muted)]">Tap a piece to see its points and where it can go.</p>
                    </div>

                    <template v-else>
                        <div class="panel grid grid-cols-3 gap-3 p-4 text-center">
                            <div>
                                <div class="stat-n">{{ evalRes?.label ?? '…' }}</div>
                                <div class="stat-l">Eval (d3)</div>
                            </div>
                            <div>
                                <div class="stat-n">{{ accuracy }}%</div>
                                <div class="stat-l">Accuracy</div>
                            </div>
                            <div>
                                <div class="stat-n">{{ Math.ceil(puzzle.solution.length / 2) }}</div>
                                <div class="stat-l">Moves deep</div>
                            </div>
                            <div class="acc-bar col-span-3" aria-hidden="true"><span :style="{ width: accuracy + '%' }" /></div>
                        </div>
                        <div class="panel p-4">
                            <h2 class="subheading mb-2 flex items-center gap-2"><Activity :size="16" /> Move history</h2>
                            <ol v-if="moveRows.length" class="moves">
                                <li v-for="r in moveRows" :key="r.n"><span class="n">{{ r.n }}.</span><span>{{ r.w }}</span><span>{{ r.b }}</span></li>
                            </ol>
                            <p v-else class="text-sm text-[var(--muted)]">No moves yet. White to play.</p>
                            <p v-if="hanging.size" class="mt-3 text-xs text-[var(--muted)]"><span class="marker static">!</span> marks an undefended black piece under attack.</p>
                        </div>
                    </template>
                </aside>

                <!-- Lesson side panel -->
                <aside v-else-if="lesson && lessonStep" class="side">
                    <div class="panel flex flex-col gap-3 p-4">
                        <button type="button" class="self-start text-sm font-bold text-[var(--muted)] hover:underline" @click="lesson = null">
                            <ChevronLeft :size="16" class="inline" /> All lessons
                        </button>
                        <h1 class="heading">{{ lesson.title }}</h1>
                        <div class="steps" aria-hidden="true">
                            <span v-for="(_, i) in lesson.steps" :key="i" :class="{ on: i <= ls.step }" />
                        </div>
                        <p class="text-lg leading-snug">{{ lessonStep.text }}</p>
                        <p v-if="ls.message" class="feedback" :class="ls.mood" aria-live="polite">{{ ls.message }}</p>

                        <div v-if="ls.finished" class="feedback good">
                            {{ explorer ? 'Lesson complete! +50 XP' : 'Lesson complete. +50 XP' }}
                        </div>
                        <div class="flex gap-2">
                            <button v-if="ls.step > 0 && !ls.finished" type="button" class="btn-c" @click="loadStep(ls.step - 1)"><ChevronLeft :size="18" /> Back</button>
                            <button v-if="!ls.finished" type="button" class="btn-c primary flex-1" :disabled="!ls.done || ls.locked" @click="nextStep">
                                {{ ls.step + 1 < lesson.steps.length ? 'Next step' : 'Finish lesson' }} <ChevronRight :size="18" />
                            </button>
                            <button v-else type="button" class="btn-c primary flex-1" @click="lesson = null">Choose another lesson</button>
                        </div>
                    </div>
                </aside>
            </section>

            <!-- Roster & attendance -->
            <section v-else-if="tab === 'roster'" class="flex flex-col gap-4">
                <div>
                    <h1 class="heading">Club Roster & Attendance</h1>
                    <p class="text-[var(--muted)]">Tap an attendance box to mark it: Present → Late → Absent → blank.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="panel p-4"><div class="stat-n">{{ rosterStats.total }}</div><div class="stat-l">Members</div></div>
                    <div class="panel p-4"><div class="stat-n">{{ rosterStats.registered }}</div><div class="stat-l">Registered</div></div>
                    <div class="panel p-4"><div class="stat-n">{{ rosterStats.presentNow }}</div><div class="stat-l">Present this week</div></div>
                    <div class="panel p-4"><div class="stat-n">{{ rosterStats.rate ?? '–' }}{{ rosterStats.rate === null ? '' : '%' }}</div><div class="stat-l">Attendance (4 weeks)</div></div>
                </div>

                <div class="panel flex flex-wrap items-end gap-3 p-4">
                    <label class="flex min-w-[200px] flex-1 flex-col gap-1">
                        <span class="stat-l">Search</span>
                        <span class="input-wrap"><Search :size="16" aria-hidden="true" /><input v-model="rosterQuery" type="search" placeholder="Name" /></span>
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="stat-l">Show</span>
                        <select v-model="rosterFilter" class="input">
                            <option value="all">All grades</option>
                            <option value="explorer">PYP 3–5</option>
                            <option value="grandmaster">MYP 1–5</option>
                        </select>
                    </label>
                    <form class="flex flex-wrap items-end gap-2" @submit.prevent="addMember">
                        <label class="flex flex-col gap-1">
                            <span class="stat-l">New member</span>
                            <input v-model="newMember.name" class="input" placeholder="First name + initial, e.g. Maya R." maxlength="60" />
                        </label>
                        <select v-model="newMember.grade" class="input" aria-label="New member's grade">
                            <option v-for="g in GRADES" :key="g" :value="g">{{ g }}</option>
                        </select>
                        <button type="submit" class="btn-c primary" :disabled="!newMember.name.trim()"><Plus :size="18" /> Add</button>
                    </form>
                </div>

                <div class="panel overflow-x-auto">
                    <table class="roster">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Grade / Year</th>
                                <th>Registration</th>
                                <th v-for="w in WEEKS" :key="w.key" class="text-center">{{ w.label }}</th>
                                <th class="text-right">Rate</th>
                                <th><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in shownMembers" :key="m.id">
                                <td class="font-bold">{{ m.name }}</td>
                                <td>
                                    <select v-model="m.grade" class="input compact" :aria-label="`${m.name}'s grade`">
                                        <option :value="null">Not set</option>
                                        <option v-for="g in GRADES" :key="g" :value="g">{{ g }} · Year {{ yearOf(g) }}</option>
                                    </select>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="status"
                                        :class="m.status === 'Registered' ? 'ok' : 'pending'"
                                        :aria-label="`${m.name}: ${m.status}. Tap to change.`"
                                        @click="m.status = m.status === 'Registered' ? 'Pending' : 'Registered'"
                                    >
                                        {{ m.status }}
                                    </button>
                                </td>
                                <td v-for="w in WEEKS" :key="w.key" class="text-center">
                                    <button
                                        type="button"
                                        class="mark"
                                        :class="m.attendance[w.key]"
                                        :aria-label="`${m.name}, ${w.label}: ${markLabel(m.attendance[w.key])}. Tap to change.`"
                                        @click="cycleMark(m, w.key)"
                                    >
                                        <Check v-if="m.attendance[w.key] === 'P'" :size="16" />
                                        <span v-else-if="m.attendance[w.key] === 'L'">L</span>
                                        <X v-else-if="m.attendance[w.key] === 'A'" :size="16" />
                                    </button>
                                </td>
                                <td class="text-right font-bold tabular-nums">{{ rateOf(m) === null ? '–' : rateOf(m) + '%' }}</td>
                                <td class="text-right">
                                    <button type="button" class="link-danger" @click="removeMember(m)">{{ armed === m.id ? 'Tap to remove' : 'Remove' }}</button>
                                </td>
                            </tr>
                            <tr v-if="!shownMembers.length">
                                <td :colspan="WEEKS.length + 5" class="py-6 text-center text-[var(--muted)]">No members match.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-[var(--muted)]">The register is saved in this browser only.</p>
            </section>

            <!-- Leaderboard & XP -->
            <section v-else class="grid gap-4 lg:grid-cols-[1fr_380px] lg:items-start">
                <div class="panel overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <h1 class="heading">Club Leaderboard</h1>
                        <div class="seg" role="group" aria-label="Filter by grade band">
                            <button v-for="f in (['all', 'explorer', 'grandmaster'] as const)" :key="f" type="button" :class="{ on: boardFilter === f }" :aria-pressed="boardFilter === f" @click="boardFilter = f">
                                {{ f === 'all' ? 'All' : f === 'explorer' ? 'PYP' : 'MYP' }}
                            </button>
                        </div>
                    </div>
                    <ol class="rank-list">
                        <li v-for="(m, i) in ranking" :key="m.id">
                            <span class="pos" :class="{ podium: i < 3 }">{{ i + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-bold">{{ m.name }}</span>
                                <span class="text-xs text-[var(--muted)]">{{ m.grade ?? 'Grade not set' }}</span>
                            </span>
                            <span class="flex gap-1">
                                <span v-for="b in m.badges" :key="b.name" class="mini-badge" :title="b.name"><component :is="b.icon" :size="14" /></span>
                            </span>
                            <span class="flex w-14 items-center justify-end gap-1 text-sm font-bold" :title="`${m.streak}-week streak`">
                                <Flame :size="16" :class="m.streak ? 'text-orange-500' : 'text-[var(--muted)]'" />{{ m.streak }}
                            </span>
                            <span class="w-20 text-right font-extrabold tabular-nums">{{ m.xp }} XP</span>
                        </li>
                    </ol>
                    <p class="border-t border-[var(--line)] p-4 text-xs text-[var(--muted)]">
                        Club XP comes from the register: {{ XP_RULES.P }} per session present, {{ XP_RULES.L }} if late, plus {{ XP_RULES.streak }} for each week in a row.
                    </p>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="panel flex flex-col gap-3 p-4">
                        <h2 class="subheading">Your progress on this device</h2>
                        <div class="flex items-end justify-between">
                            <span class="stat-n">Level {{ level }}</span>
                            <span class="font-bold text-[var(--muted)]">{{ progress.xp }} XP</span>
                        </div>
                        <div class="acc-bar" aria-hidden="true"><span :style="{ width: (progress.xp % 100) + '%' }" /></div>
                        <span class="text-xs text-[var(--muted)]">{{ 100 - (progress.xp % 100) }} XP to level {{ level + 1 }}</span>
                    </div>

                    <div class="panel flex flex-col gap-3 p-4">
                        <h2 class="subheading">Weekly challenges</h2>
                        <div v-for="c in CHALLENGES" :key="c.label" class="flex flex-col gap-1">
                            <div class="flex justify-between text-sm font-bold">
                                <span>{{ c.label }}</span>
                                <span class="tabular-nums">{{ Math.min(c.have, c.need) }}/{{ c.need }}</span>
                            </div>
                            <div class="acc-bar" aria-hidden="true"><span :style="{ width: Math.min(100, (c.have / c.need) * 100) + '%' }" /></div>
                        </div>
                    </div>

                    <div class="panel flex flex-col gap-3 p-4">
                        <h2 class="subheading">{{ explorer ? 'Explorer badges' : 'Grandmaster badges' }}</h2>
                        <ul class="grid grid-cols-1 gap-2">
                            <li v-for="b in tierBadges" :key="b.id" class="badge" :class="{ earned: b.earned }">
                                <span class="badge-icon"><component :is="b.icon" :size="20" /></span>
                                <span>
                                    <span class="block font-bold">{{ b.name }}</span>
                                    <span class="text-xs text-[var(--muted)]">{{ b.desc }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="byGrade.length" class="panel p-4">
                        <h2 class="subheading mb-2">By grade level</h2>
                        <table class="w-full text-sm">
                            <thead class="text-left text-[var(--muted)]">
                                <tr><th class="py-1">Grade</th><th class="text-right">Members</th><th class="text-right">Badges</th><th class="text-right">XP</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="g in byGrade" :key="g.grade" class="border-t border-[var(--line)]">
                                    <td class="py-1 font-bold">{{ g.grade }}</td>
                                    <td class="text-right tabular-nums">{{ g.members }}</td>
                                    <td class="text-right tabular-nums">{{ g.badges }}</td>
                                    <td class="text-right tabular-nums">{{ g.xp }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>

        <!-- Puzzle result -->
        <div v-if="pz.result && tab === 'arena'" class="modal" role="dialog" aria-modal="true" aria-labelledby="result-title">
            <div class="panel result">
                <template v-if="explorer">
                    <h2 id="result-title" class="heading text-4xl">{{ pz.result.stars === 3 ? 'Amazing!' : 'You did it!' }}</h2>
                    <div class="flex justify-center gap-2">
                        <span v-for="n in 3" :key="n" class="big-star" :style="{ animationDelay: n * 0.15 + 's' }"><StarIcon :filled="n <= pz.result.stars" /></span>
                    </div>
                    <p class="font-bold">{{ pz.result.stars === 3 ? 'First try! You are a chess star.' : 'Solve it on the first try for 3 stars.' }}</p>
                </template>
                <template v-else>
                    <h2 id="result-title" class="heading">Combination complete</h2>
                    <p class="font-mono text-sm">{{ moveRows.map((r) => `${r.n}. ${r.w} ${r.b}`).join(' ') }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div><div class="stat-n">{{ pz.result.accuracy }}%</div><div class="stat-l">Accuracy</div></div>
                        <div><div class="stat-n">{{ puzzle.theme }}</div><div class="stat-l">Motif</div></div>
                    </div>
                </template>
                <p class="chip mx-auto"><Sparkles :size="16" /> +{{ pz.result.xp }} XP</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <button type="button" class="btn-c" @click="(sfx.tap(), resetPuzzle())"><RotateCcw :size="18" /> Try again</button>
                    <button type="button" class="btn-c primary" @click="nextPuzzle">Next puzzle <ChevronRight :size="18" /></button>
                </div>
            </div>
        </div>

        <div class="toasts" aria-live="polite">
            <div v-for="t in toasts" :key="t.id" class="toast"><Trophy :size="18" /> {{ t.text }}</div>
        </div>
    </div>
</template>

<style scoped>
/* ---------- tier themes ---------- */
.tier-explorer {
    --bg: #ecfdf5;
    --bg2: #d1fae5;
    --panel: #ffffff;
    --text: #1c2541;
    --muted: #4a5a7a;
    --line: #d7e3dc;
    --accent: #f59e0b;
    --accent-ink: #1c2541;
    --good: #059669;
    --bad: #e11d48;
    --sky: #38bdf8;
    --radius: 22px;
    --shadow: 0 6px 18px rgba(16, 94, 72, 0.14);
    --sq-light: #fef3c7;
    --sq-dark: #34b28a;
    --frame: #065f46;
    --font-head: 'Baloo 2', 'Nunito', sans-serif;
}
.tier-gm {
    --bg: #0b1120;
    --bg2: #111a2e;
    --panel: #151e33;
    --text: #e2e8f0;
    --muted: #94a3b8;
    --line: #26324d;
    --accent: #818cf8;
    --accent-ink: #0b1120;
    --good: #34d399;
    --bad: #fb7185;
    --sky: #22d3ee;
    --radius: 8px;
    --shadow: 0 0 0 1px #26324d;
    --sq-light: #d5dcea;
    --sq-dark: #6b7a9f;
    --frame: #26324d;
    --font-head: 'Nunito', system-ui, sans-serif;
}

.club {
    min-height: 100dvh;
    background: var(--bg);
    color: var(--text);
    transition: background-color 0.3s;
}
.tier-explorer.club {
    background-image: radial-gradient(var(--bg2) 2px, transparent 2px);
    background-size: 28px 28px;
}

/* ---------- header ---------- */
.top {
    background: var(--panel);
    border-bottom: 1px solid var(--line);
    box-shadow: var(--shadow);
    position: sticky;
    top: 0;
    z-index: 20;
}
.logo {
    display: grid;
    width: 44px;
    height: 44px;
    padding: 4px;
    border-radius: calc(var(--radius) * 0.7);
    background: var(--accent);
    flex: none;
}
.title {
    font-family: var(--font-head);
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.1;
}
.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--bg2);
    font-weight: 800;
    font-size: 0.9rem;
    width: fit-content;
}
.chip.small {
    padding: 3px 10px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--accent);
    background: color-mix(in srgb, var(--accent) 15%, transparent);
}
.grade-picker {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding-left: 12px;
    border-radius: calc(var(--radius) * 0.6);
    border: 2px solid var(--accent);
    background: var(--panel);
}
.grade-picker select {
    border: 0;
    background: transparent;
    color: var(--text);
    font-weight: 800;
    padding: 8px 32px 8px 4px;
}
.grade-picker select:focus {
    box-shadow: none;
}
.grade-picker:focus-within {
    outline: 3px solid var(--sky);
    outline-offset: 2px;
}
.grade-picker option,
.grade-picker optgroup {
    background: var(--panel);
    color: var(--text);
}
.tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    padding: 10px 14px;
    font-weight: 800;
    color: var(--muted);
    border-bottom: 3px solid transparent;
}
.tab.on {
    color: var(--text);
    border-bottom-color: var(--accent);
}
.tier-explorer .tab {
    font-size: 1.05rem;
}

/* ---------- common ---------- */
.panel {
    background: var(--panel);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}
.heading {
    font-family: var(--font-head);
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.15;
}
.tier-gm .heading {
    font-size: 1.35rem;
    letter-spacing: -0.01em;
}
.subheading {
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
}
.stat-n {
    font-family: var(--font-head);
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}
.stat-l {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
}
.btn-c {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 14px;
    border-radius: calc(var(--radius) * 0.6);
    border: 2px solid var(--line);
    background: var(--panel);
    color: var(--text);
    font-weight: 800;
    transition: transform 0.08s;
}
.btn-c:active:not(:disabled) {
    transform: translateY(2px);
}
.btn-c:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-c.primary {
    background: var(--accent);
    border-color: var(--accent);
    color: var(--accent-ink);
}
.btn-c:focus-visible,
.tab:focus-visible,
.lesson-card:focus-visible,
.mark:focus-visible,
.status:focus-visible {
    outline: 3px solid var(--sky);
    outline-offset: 2px;
}
.tier-explorer .btn-c {
    min-height: 54px;
    font-size: 1.05rem;
    box-shadow: 0 4px 0 var(--line);
}
.tier-explorer .btn-c.primary {
    box-shadow: 0 4px 0 #b45309;
}
.feedback {
    padding: 10px 12px;
    border-radius: calc(var(--radius) * 0.6);
    background: var(--bg2);
    font-weight: 700;
}
.feedback.good {
    background: color-mix(in srgb, var(--good) 18%, var(--panel));
}
.feedback.bad {
    background: color-mix(in srgb, var(--bad) 18%, var(--panel));
}
.tier-gm .feedback {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.85rem;
    font-weight: 500;
    border-left: 3px solid var(--accent);
}
.tier-gm .feedback.good {
    border-left-color: var(--good);
}
.tier-gm .feedback.bad {
    border-left-color: var(--bad);
}
.input,
.input-wrap {
    border-radius: calc(var(--radius) * 0.5);
    border: 2px solid var(--line);
    background: var(--panel);
    color: var(--text);
    font-weight: 700;
}
.input.compact {
    padding: 4px 28px 4px 8px;
    font-size: 0.85rem;
}
.input-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    padding-left: 10px;
    color: var(--muted);
}
.input-wrap input {
    flex: 1;
    min-width: 0;
    border: 0;
    background: transparent;
    color: var(--text);
    font-weight: 700;
}
.input-wrap input:focus {
    box-shadow: none;
}
.acc-bar {
    height: 10px;
    border-radius: 999px;
    background: var(--bg2);
    overflow: hidden;
}
.acc-bar span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, var(--accent), var(--good));
    transition: width 0.4s;
}

/* ---------- play layout ---------- */
.play {
    display: grid;
    gap: 20px;
    grid-template-columns: minmax(0, 1fr);
}
@media (min-width: 1024px) {
    .play {
        grid-template-columns: minmax(0, 620px) minmax(300px, 1fr);
        align-items: start;
    }
}
.board-col {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}
.side {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.turn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
}
.turn .dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--text);
}
.turn.black .dot {
    background: #111;
    animation: pulse 1s infinite;
}
.value-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px 4px 6px;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
    font-weight: 800;
    animation: pop 0.25s ease-out;
}
.board-row {
    display: flex;
    gap: 10px;
    align-items: stretch;
}
.eval {
    position: relative;
    width: 22px;
    flex: none;
    border-radius: 4px;
    background: #1e1e1e;
    overflow: hidden;
}
.eval-white {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    background: #f1f5f9;
    transition: height 0.5s;
}
.eval-label {
    position: absolute;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 9px;
    font-weight: 800;
}
.eval-label.bottom {
    bottom: 4px;
    color: #0b1120;
}
.eval-label.top {
    top: 4px;
    color: #f1f5f9;
}
.board-frame {
    position: relative;
    flex: 1;
    min-width: 0;
    padding: 8px;
    border-radius: var(--radius);
    background: var(--frame);
}
.board {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    aspect-ratio: 1;
    border-radius: calc(var(--radius) * 0.5);
    overflow: hidden;
    container-type: inline-size;
    user-select: none;
}
.sq {
    position: relative;
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    background: var(--sq-light);
}
.sq.dark {
    background: var(--sq-dark);
}
.sq::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.sq.last::before {
    background: rgba(250, 204, 21, 0.45);
}
.sq.sel::before {
    background: rgba(245, 158, 11, 0.6);
}
.tier-gm .sq.last::before {
    background: rgba(129, 140, 248, 0.4);
}
.tier-gm .sq.sel::before {
    background: rgba(129, 140, 248, 0.65);
}
.sq.hl::before {
    background: rgba(56, 189, 248, 0.5);
    box-shadow: inset 0 0 0 3px var(--sky);
}
.sq.hint::before {
    background: rgba(16, 185, 129, 0.75);
    animation: blink 0.6s 3;
}
.sq.check::before {
    background: radial-gradient(circle, #ef4444 0 40%, transparent 72%);
}
.sq.tgt::after {
    content: '';
    position: absolute;
    width: 28%;
    height: 28%;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.35);
    pointer-events: none;
}
.tier-explorer .sq.tgt::after {
    width: 34%;
    height: 34%;
    background: #fde047;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.8), 0 0 14px 4px rgba(253, 224, 71, 0.9);
    animation: glow 1.2s ease-in-out infinite;
}
.sq.cap::after {
    content: '';
    position: absolute;
    inset: 5%;
    border-radius: 50%;
    border: 0.9cqw solid rgba(15, 23, 42, 0.45);
    pointer-events: none;
}
.tier-explorer .sq.cap::after {
    border-color: #f59e0b;
    box-shadow: 0 0 12px 2px rgba(245, 158, 11, 0.8);
}
.sq:focus-visible {
    outline: 3px solid var(--sky);
    outline-offset: -3px;
    z-index: 3;
}
.pc {
    position: relative;
    z-index: 1;
    width: 90%;
    height: 90%;
    pointer-events: none;
}
.star {
    position: relative;
    z-index: 1;
    width: 60%;
    height: 60%;
    animation: twinkle 1.6s ease-in-out infinite;
}
.marker {
    position: absolute;
    z-index: 2;
    top: 4%;
    right: 4%;
    display: grid;
    place-items: center;
    width: 26%;
    height: 26%;
    border-radius: 50%;
    background: #f43f5e;
    color: #fff;
    font-weight: 900;
    font-size: max(10px, 2.4cqw);
}
.marker.static {
    position: static;
    display: inline-grid;
    width: 16px;
    height: 16px;
    font-size: 10px;
    vertical-align: middle;
}
.coord {
    position: absolute;
    z-index: 2;
    font-size: max(9px, 2.2cqw);
    font-weight: 800;
    color: rgba(15, 23, 42, 0.55);
    pointer-events: none;
}
.rank {
    top: 2px;
    left: 4px;
}
.file {
    bottom: 2px;
    right: 4px;
}
.shake {
    animation: shake 0.15s 3;
}
.pop-star {
    position: absolute;
    left: 50%;
    top: 40%;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
    font-family: var(--font-head);
    font-size: 1.4rem;
    font-weight: 800;
    pointer-events: none;
    animation: float-up 1.1s ease-out forwards;
    z-index: 5;
}
.moves {
    display: grid;
    grid-template-columns: auto 1fr 1fr;
    gap: 2px 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.9rem;
}
.moves li {
    display: contents;
}
.moves .n {
    color: var(--muted);
}

/* ---------- lessons ---------- */
.lesson-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 140px;
    padding: 16px;
    text-align: left;
    transition: transform 0.12s;
}
.lesson-card:hover:not(:disabled) {
    transform: translateY(-3px);
}
.lesson-card:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.tier-explorer .lesson-card {
    border: 3px solid var(--bg2);
}
.tier-gm .lesson-card {
    border-left: 3px solid var(--accent);
}
.steps {
    display: flex;
    gap: 6px;
}
.steps span {
    flex: 1;
    height: 6px;
    border-radius: 99px;
    background: var(--bg2);
}
.steps span.on {
    background: var(--accent);
}

/* ---------- roster ---------- */
.roster {
    width: 100%;
    min-width: 860px;
    text-align: left;
    font-size: 0.92rem;
}
.roster th {
    padding: 10px 12px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    border-bottom: 1px solid var(--line);
    white-space: nowrap;
}
.roster td {
    padding: 8px 12px;
    border-top: 1px solid var(--line);
}
.status {
    padding: 3px 10px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.8rem;
}
.status.ok {
    background: color-mix(in srgb, var(--good) 20%, transparent);
    color: var(--good);
}
.status.pending {
    background: color-mix(in srgb, var(--accent) 22%, transparent);
    color: var(--text);
}
.mark {
    display: inline-grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 2px dashed var(--line);
    font-weight: 900;
}
.mark.P {
    border: 0;
    background: var(--good);
    color: #fff;
}
.mark.L {
    border: 0;
    background: #f59e0b;
    color: #1c2541;
}
.mark.A {
    border: 0;
    background: var(--bad);
    color: #fff;
}
.link-danger {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--bad);
}

/* ---------- leaderboard ---------- */
.seg {
    display: inline-flex;
    border: 2px solid var(--line);
    border-radius: calc(var(--radius) * 0.6);
    overflow: hidden;
}
.seg button {
    padding: 6px 14px;
    font-weight: 800;
    color: var(--muted);
}
.seg button.on {
    background: var(--accent);
    color: var(--accent-ink);
}
.rank-list li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-top: 1px solid var(--line);
}
.pos {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    flex: none;
    border-radius: 50%;
    background: var(--bg2);
    font-weight: 900;
}
.pos.podium {
    background: var(--accent);
    color: var(--accent-ink);
}
.mini-badge {
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--accent) 22%, transparent);
    color: var(--accent);
}
.badge {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: calc(var(--radius) * 0.6);
    opacity: 0.45;
    filter: grayscale(1);
}
.badge.earned {
    opacity: 1;
    filter: none;
    background: color-mix(in srgb, var(--accent) 12%, transparent);
}
.badge-icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    flex: none;
    border-radius: 50%;
    background: var(--accent);
    color: var(--accent-ink);
}

/* ---------- modal & toasts ---------- */
.modal {
    position: fixed;
    inset: 0;
    z-index: 40;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(2, 6, 23, 0.6);
}
.result {
    width: min(440px, 100%);
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 24px;
    text-align: center;
    animation: pop 0.3s ease-out;
}
.tier-gm .result {
    border: 1px solid var(--accent);
}
.big-star {
    width: 64px;
    height: 64px;
    animation: pop 0.45s ease-out backwards;
}
.big-star:nth-child(2) {
    transform: translateY(-10px);
}
.toasts {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 50;
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: calc(100vw - 32px);
}
.toast {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: calc(var(--radius) * 0.6);
    background: var(--accent);
    color: var(--accent-ink);
    font-weight: 800;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    animation: pop 0.3s ease-out;
}

/* ---------- motion ---------- */
@keyframes pop {
    0% {
        transform: scale(0.6);
        opacity: 0;
    }
    70% {
        transform: scale(1.08);
        opacity: 1;
    }
    100% {
        transform: scale(1);
    }
}
@keyframes glow {
    50% {
        transform: scale(1.2);
    }
}
@keyframes twinkle {
    50% {
        transform: scale(1.12) rotate(8deg);
    }
}
@keyframes blink {
    50% {
        opacity: 0.25;
    }
}
@keyframes pulse {
    50% {
        opacity: 0.4;
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
@keyframes float-up {
    0% {
        transform: translate(-50%, 0) scale(0.6);
        opacity: 0;
    }
    25% {
        transform: translate(-50%, -10px) scale(1.1);
        opacity: 1;
    }
    100% {
        transform: translate(-50%, -80px) scale(1);
        opacity: 0;
    }
}
</style>
