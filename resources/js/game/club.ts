/**
 * Chess Club: grade tiers, puzzles, lessons, notation and a small evaluator.
 * Boards use the same 64-cell format as engine.ts (index 0 = a8, 63 = h1; '*' = star).
 */
import data from './club.json';
import { applyMove, Board, hasLegalMove, inCheck, isBlack, isPiece, isWhite, legalMoves, Side, sideOf, squareName } from './engine';

export const GRADES = ['PYP 3', 'PYP 4', 'PYP 5', 'MYP 1', 'MYP 2', 'MYP 3', 'MYP 4', 'MYP 5'] as const;
export type Grade = (typeof GRADES)[number];
export type Tier = 'explorer' | 'grandmaster';

export const tierOf = (g: Grade): Tier => (GRADES.indexOf(g) <= 2 ? 'explorer' : 'grandmaster');
export const gradeIndex = (g: Grade) => GRADES.indexOf(g);
/** IB year: PYP n is Year n, MYP n is Year n + 5. */
export const yearOf = (g: Grade) => (g.startsWith('PYP') ? Number(g.slice(4)) : Number(g.slice(4)) + 5);

// ---------------------------------------------------------------- board helpers

export function sq(name: string): number {
    return (8 - Number(name[1])) * 8 + (name.charCodeAt(0) - 97);
}

export function fromFen(fen: string): Board {
    const b: string[] = [];
    for (const row of fen.split(' ')[0].split('/')) {
        for (const ch of row) {
            if (/\d/.test(ch)) b.push(...'.'.repeat(Number(ch)));
            else b.push(ch);
        }
    }
    if (b.length !== 64) throw new Error(`Bad FEN: ${fen}`);
    return b;
}

export const other = (s: Side): Side => (s === 'w' ? 'b' : 'w');
export const isMated = (b: Board, s: Side) => inCheck(b, s) && !hasLegalMove(b, s);

export function allMoves(b: Board, side: Side): [number, number][] {
    const out: [number, number][] = [];
    for (let i = 0; i < 64; i++) {
        if (sideOf(b[i]) === side) for (const t of legalMoves(b, i)) out.push([i, t]);
    }
    return out;
}

/** Parses "e2e4" into [from, to]. */
export const uci = (m: string): [number, number] => [sq(m.slice(0, 2)), sq(m.slice(2, 4))];

/** Standard algebraic notation for a legal move, e.g. "Nf3", "exd5", "Rxe8#", "a8=Q". */
export function san(b: Board, from: number, to: number): string {
    const p = b[from];
    const t = p.toUpperCase();
    const capture = isPiece(b[to]);
    let s: string;

    if (t === 'P') {
        s = (capture ? squareName(from)[0] + 'x' : '') + squareName(to);
        if (to >> 3 === 0 || to >> 3 === 7) s += '=Q';
    } else {
        s = t;
        const rivals: number[] = [];
        for (let i = 0; i < 64; i++) if (i !== from && b[i] === p && legalMoves(b, i).includes(to)) rivals.push(i);
        if (rivals.length) {
            const sameFile = rivals.some((r) => (r & 7) === (from & 7));
            const sameRank = rivals.some((r) => r >> 3 === from >> 3);
            s += !sameFile ? squareName(from)[0] : !sameRank ? squareName(from)[1] : squareName(from);
        }
        s += (capture ? 'x' : '') + squareName(to);
    }

    const after = applyMove(b, from, to);
    const them = other(sideOf(p)!);
    if (inCheck(after, them)) s += hasLegalMove(after, them) ? '+' : '#';
    return s;
}

// ---------------------------------------------------------------- evaluation

export const VALUES: Record<string, number> = { p: 1, n: 3, b: 3, r: 5, q: 9, k: 0 };
const CENTER = new Set([sq('d4'), sq('e4'), sq('d5'), sq('e5')]);
const MATE = 1000;

function material(b: Board): number {
    let v = 0;
    for (let i = 0; i < 64; i++) {
        const c = b[i];
        if (!isPiece(c)) continue;
        const worth = VALUES[c.toLowerCase()] + (CENTER.has(i) ? 0.15 : 0);
        v += isWhite(c) ? worth : -worth;
    }
    return v;
}

function search(b: Board, side: Side, depth: number, alpha: number, beta: number): number {
    if (depth === 0) return material(b);
    const moves = allMoves(b, side);
    if (!moves.length) return inCheck(b, side) ? (side === 'w' ? -MATE - depth : MATE + depth) : 0;
    // Captures first, so alpha-beta cuts more.
    moves.sort((m1, m2) => Number(isPiece(b[m2[1]])) - Number(isPiece(b[m1[1]])));

    if (side === 'w') {
        let best = -Infinity;
        for (const [f, t] of moves) {
            best = Math.max(best, search(applyMove(b, f, t), 'b', depth - 1, alpha, beta));
            alpha = Math.max(alpha, best);
            if (alpha >= beta) break;
        }
        return best;
    }
    let best = Infinity;
    for (const [f, t] of moves) {
        best = Math.min(best, search(applyMove(b, f, t), 'w', depth - 1, alpha, beta));
        beta = Math.min(beta, best);
        if (alpha >= beta) break;
    }
    return best;
}

export interface Evaluation {
    /** Pawns from White's point of view, clamped for the bar. */
    score: number;
    label: string;
}

/** A shallow look-ahead (3 plies), enough to see simple forks and short mates. */
export function evaluate(b: Board, toMove: Side, depth = 3): Evaluation {
    const v = search(b, toMove, depth, -Infinity, Infinity);
    if (Math.abs(v) >= MATE) return { score: v > 0 ? 10 : -10, label: v > 0 ? '+M' : '−M' };
    const r = Math.round(v * 10) / 10;
    return { score: Math.max(-10, Math.min(10, r)), label: (r > 0 ? '+' : r < 0 ? '−' : '') + Math.abs(r).toFixed(1) };
}

// ---------------------------------------------------------------- puzzles

export interface Puzzle {
    id: string;
    tier: Tier;
    title: string;
    theme: string;
    story: string;
    fen: string;
    /** Alternating moves, White first. White's are the student's; Black's are played automatically. */
    solution: string[];
    /** Any checkmating move also counts on the final move. */
    goal: 'mate' | 'material';
    hint: string;
}


// ---------------------------------------------------------------- lessons

export interface LessonStep {
    text: string;
    fen: string;
    highlight?: string[];
    /** A move the student must make to continue, with an optional automatic reply. */
    task?: { move: string; reply?: string; prompt: string };
}

export interface Lesson {
    id: string;
    tier: Tier;
    /** First grade the lesson is aimed at. */
    from: Grade;
    title: string;
    blurb: string;
    steps: LessonStep[];
}

// club.json is also read by the server (App\Support\ClubCatalog) to check puzzle and lesson ids.
export const PUZZLES = data.puzzles as Puzzle[];
export const LESSONS = data.lessons as Lesson[];

export { isBlack, isWhite };