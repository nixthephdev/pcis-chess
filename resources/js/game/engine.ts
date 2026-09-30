/**
 * Lesson rules engine.
 *
 * A board is 64 single-character cells, index 0 = a8 and 63 = h1.
 * Uppercase = white (the student), lowercase = black, '.' = empty, '*' = star.
 * Black pieces never move in star and capture levels; they only guard squares.
 */
export type Board = string[];
export type Side = 'w' | 'b';

const LINES: Record<'R' | 'B', [number, number][]> = {
    R: [[1, 0], [-1, 0], [0, 1], [0, -1]],
    B: [[1, 1], [1, -1], [-1, 1], [-1, -1]],
};
const ALL_DIRS = [...LINES.R, ...LINES.B];
const KNIGHT: [number, number][] = [[1, 2], [2, 1], [-1, 2], [-2, 1], [1, -2], [2, -1], [-1, -2], [-2, -1]];

export const isWhite = (c: string) => c >= 'A' && c <= 'Z';
export const isBlack = (c: string) => c >= 'a' && c <= 'z';
export const isPiece = (c: string) => isWhite(c) || isBlack(c);
export const sideOf = (c: string): Side | null => (isWhite(c) ? 'w' : isBlack(c) ? 'b' : null);
export const squareName = (i: number) => 'abcdefgh'[i & 7] + (8 - (i >> 3));

const onBoard = (r: number, f: number) => r >= 0 && r < 8 && f >= 0 && f < 8;

export function attacks(b: Board, sq: number): number[] {
    const p = b[sq];
    const t = p.toUpperCase();
    const r = sq >> 3;
    const f = sq & 7;
    const out: number[] = [];
    const add = (rr: number, ff: number) => {
        if (onBoard(rr, ff)) out.push(rr * 8 + ff);
    };

    if (t === 'P') {
        const dr = isWhite(p) ? -1 : 1;
        add(r + dr, f - 1);
        add(r + dr, f + 1);
    } else if (t === 'N') {
        KNIGHT.forEach(([a, c]) => add(r + a, f + c));
    } else if (t === 'K') {
        ALL_DIRS.forEach(([a, c]) => add(r + a, f + c));
    } else {
        const dirs = t === 'Q' ? ALL_DIRS : LINES[t as 'R' | 'B'];
        for (const [a, c] of dirs) {
            let rr = r + a;
            let ff = f + c;
            while (onBoard(rr, ff)) {
                out.push(rr * 8 + ff);
                if (isPiece(b[rr * 8 + ff])) break;
                rr += a;
                ff += c;
            }
        }
    }
    return out;
}

export function pseudoMoves(b: Board, sq: number): number[] {
    const p = b[sq];
    const me = sideOf(p);
    const out: number[] = [];

    if (p.toUpperCase() === 'P') {
        const r = sq >> 3;
        const f = sq & 7;
        const dr = isWhite(p) ? -1 : 1;
        const startRow = isWhite(p) ? 6 : 1;
        const one = (r + dr) * 8 + f;
        if (r + dr >= 0 && r + dr < 8 && !isPiece(b[one])) {
            out.push(one);
            const two = (r + 2 * dr) * 8 + f;
            if (r === startRow && !isPiece(b[two])) out.push(two);
        }
        for (const s of attacks(b, sq)) if (isPiece(b[s]) && sideOf(b[s]) !== me) out.push(s);
        return out;
    }

    for (const s of attacks(b, sq)) if (sideOf(b[s]) !== me) out.push(s);
    return out;
}

/** Index of a `side` piece attacking `sq`, or -1. */
export function attackerOf(b: Board, sq: number, side: Side): number {
    for (let i = 0; i < 64; i++) {
        if (sideOf(b[i]) === side && attacks(b, i).includes(sq)) return i;
    }
    return -1;
}

/** Pawns reaching the last row become queens. */
export function applyMove(b: Board, from: number, to: number): Board {
    const n = b.slice();
    let p = n[from];
    const row = to >> 3;
    if (p.toUpperCase() === 'P' && (row === 0 || row === 7)) p = isWhite(p) ? 'Q' : 'q';
    n[to] = p;
    n[from] = '.';
    return n;
}

export const kingSquare = (b: Board, side: Side) => b.indexOf(side === 'w' ? 'K' : 'k');

export function inCheck(b: Board, side: Side): boolean {
    const k = kingSquare(b, side);
    return k >= 0 && attackerOf(b, k, side === 'w' ? 'b' : 'w') >= 0;
}

export function legalMoves(b: Board, sq: number): number[] {
    const side = sideOf(b[sq]);
    if (!side) return [];
    return pseudoMoves(b, sq).filter((t) => !inCheck(applyMove(b, sq, t), side));
}

export function hasLegalMove(b: Board, side: Side): boolean {
    for (let i = 0; i < 64; i++) {
        if (sideOf(b[i]) === side && legalMoves(b, i).length) return true;
    }
    return false;
}

export const isMate = (b: Board) => inCheck(b, 'b') && !hasLegalMove(b, 'b');

/** Every square a black piece guards. */
export function guardedSquares(b: Board): Set<number> {
    const d = new Set<number>();
    for (let i = 0; i < 64; i++) if (isBlack(b[i])) attacks(b, i).forEach((s) => d.add(s));
    return d;
}

/** Fewest safe moves to finish a star or capture level, or null if it cannot be done. */
export function solvePar(start: Board, goal: 'stars' | 'capture'): number | null {
    const done = (s: string) => (goal === 'stars' ? !s.includes('*') : !/[a-z]/.test(s));
    let queue = [start.join('')];
    const seen = new Set(queue);

    for (let depth = 0; depth < 40 && queue.length; depth++) {
        const next: string[] = [];
        for (const s of queue) {
            if (done(s)) return depth;
            const b = s.split('');
            for (let i = 0; i < 64; i++) {
                if (!isWhite(b[i])) continue;
                for (const t of pseudoMoves(b, i)) {
                    const nb = applyMove(b, i, t);
                    if (attackerOf(nb, t, 'b') >= 0) continue;
                    const key = nb.join('');
                    if (!seen.has(key)) {
                        seen.add(key);
                        next.push(key);
                    }
                }
            }
        }
        queue = next;
    }
    return null;
}

/** A winning first move for a check or mate puzzle, as [from, to]. */
export function findWinningMove(b: Board, goal: 'check' | 'mate'): [number, number] | null {
    for (let i = 0; i < 64; i++) {
        if (!isWhite(b[i])) continue;
        for (const t of legalMoves(b, i)) {
            const nb = applyMove(b, i, t);
            if (goal === 'check' ? inCheck(nb, 'b') : isMate(nb)) return [i, t];
        }
    }
    return null;
}
