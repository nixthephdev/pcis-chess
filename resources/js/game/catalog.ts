import data from './levels.json';
import { Board, solvePar } from './engine';

export type Goal = 'stars' | 'capture' | 'check' | 'mate';
export type SectionColor = 'sun' | 'tang' | 'berry';

export interface Section {
    title: string;
    subtitle: string;
    color: SectionColor;
}

export interface Level {
    id: string;
    worldId: string;
    index: number;
    goal: Goal;
    board: Board;
    tip: string;
}

export interface World {
    id: string;
    section: number;
    name: string;
    piece: string;
    blurb: string;
    levels: Level[];
}

/** Ranks are keyed "8".."1"; missing ranks are empty. */
function parseBoard(rows: Partial<Record<string, string>>): Board {
    let s = '';
    for (let r = 8; r >= 1; r--) s += (rows[String(r)] ?? '').padEnd(8, '.');
    return s.split('');
}

export const SECTIONS = data.sections as Section[];

export const WORLDS: World[] = data.worlds.map((w) => ({
    id: w.id,
    section: w.section,
    name: w.name,
    piece: w.piece,
    blurb: w.blurb,
    levels: w.levels.map((l, index) => ({
        id: `${w.id}-${index + 1}`,
        worldId: w.id,
        index,
        goal: l.goal as Goal,
        board: parseBoard(l.board),
        tip: l.tip,
    })),
}));

export const TOTAL_STARS = WORLDS.reduce((n, w) => n + w.levels.length * 3, 0);

export const isPuzzle = (l: Level) => l.goal === 'check' || l.goal === 'mate';

const parCache = new Map<string, number | null>();

/** Moves needed for 3 stars; puzzles are always one move. */
export function parFor(level: Level): number | null {
    if (isPuzzle(level)) return 1;
    if (!parCache.has(level.id)) parCache.set(level.id, solvePar(level.board, level.goal as 'stars' | 'capture'));
    return parCache.get(level.id)!;
}

export function worldById(id: string): World | undefined {
    return WORLDS.find((w) => w.id === id);
}

/** The level after this one, crossing into the next world at the end. */
export function nextLevel(level: Level): Level | null {
    const wi = WORLDS.findIndex((w) => w.id === level.worldId);
    const w = WORLDS[wi];
    if (level.index + 1 < w.levels.length) return w.levels[level.index + 1];
    return WORLDS[wi + 1]?.levels[0] ?? null;
}

export const RANKS: { min: number; title: string; piece: string }[] = [
    { min: 0, title: 'Pawn Rookie', piece: 'P' },
    { min: 0.1, title: 'Knight Explorer', piece: 'N' },
    { min: 0.3, title: 'Bishop Scout', piece: 'B' },
    { min: 0.5, title: 'Rook Guardian', piece: 'R' },
    { min: 0.75, title: 'Queen Champion', piece: 'Q' },
    { min: 1, title: 'King of the Board', piece: 'K' },
];

export function rankFor(stars: number) {
    const pct = stars / TOTAL_STARS;
    return [...RANKS].reverse().find((r) => pct >= r.min)!;
}
