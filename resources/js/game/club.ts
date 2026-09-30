/**
 * Chess Club: grade tiers, puzzles, lessons, notation and a small evaluator.
 * Boards use the same 64-cell format as engine.ts (index 0 = a8, 63 = h1; '*' = star).
 */
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

export const PUZZLES: Puzzle[] = [
    {
        id: 'ex-free-queen',
        tier: 'explorer',
        title: 'Free Lunch!',
        theme: 'Free piece',
        story: "The black queen wandered off without a bodyguard. Can your rook grab her?",
        fen: '6k1/q4ppp/8/8/8/8/5PPP/R5K1 w',
        solution: ['a1a7'],
        goal: 'material',
        hint: 'Rooks zoom in straight lines. Look up the a-file!',
    },
    {
        id: 'ex-knight-jump',
        tier: 'explorer',
        title: "The Knight's L-shaped Jump",
        theme: 'Free piece',
        story: 'Sir Knight spots a lonely bishop. Hop over in one L-shaped jump!',
        fen: '4k3/8/3b4/8/4N3/8/8/4K3 w',
        solution: ['e4d6'],
        goal: 'material',
        hint: 'Two squares up, one square to the side. Where does that land?',
    },
    {
        id: 'ex-long-diagonal',
        tier: 'explorer',
        title: "The Bishop's Secret Slide",
        theme: 'Free piece',
        story: 'A black rook is hiding in the far corner. Your bishop can slide all the way there!',
        fen: 'r3k3/8/8/8/8/8/8/4K2B w',
        solution: ['h1a8'],
        goal: 'material',
        hint: 'Bishops slide diagonally. Follow the long diagonal from corner to corner.',
    },
    {
        id: 'ex-back-rank',
        tier: 'explorer',
        title: 'Protect the Castle!',
        theme: 'Checkmate in 1',
        story: "The black king hid behind his pawns, but they've trapped him! Can your rook finish the game?",
        fen: '6k1/5ppp/8/8/8/8/8/R5K1 w',
        solution: ['a1a8'],
        goal: 'mate',
        hint: 'The king cannot move forward. Check him along the back row!',
    },
    {
        id: 'ex-queen-kiss',
        tier: 'explorer',
        title: "The Queen's Big Hug",
        theme: 'Checkmate in 1',
        story: 'Your king and queen work as a team. Put the queen right next to the black king, with your king guarding her!',
        fen: 'k7/7Q/1K6/8/8/8/8/8 w',
        solution: ['h7b7'],
        goal: 'mate',
        hint: 'Your king protects b7 and a7. Can the queen land on one of them?',
    },
    {
        id: 'ex-rook-roller',
        tier: 'explorer',
        title: 'The Rook Wall',
        theme: 'Checkmate in 1',
        story: 'One rook has built a wall on row 7. Bring the other rook to close the door!',
        fen: 'k7/1R6/2K5/8/8/8/8/7R w',
        solution: ['h1h8'],
        goal: 'mate',
        hint: 'The black king is stuck on the top row. Attack that whole row!',
    },
    {
        id: 'gm-royal-fork',
        tier: 'grandmaster',
        title: 'Royal Fork',
        theme: 'Fork',
        story: 'A knight check that hits two targets at once. Calculate the full sequence.',
        fen: '2q3k1/5ppp/8/3N4/8/8/5PPP/6K1 w',
        solution: ['d5e7', 'g8h8', 'e7c8'],
        goal: 'material',
        hint: 'Which knight check also attacks c8?',
    },
    {
        id: 'gm-skewer',
        tier: 'grandmaster',
        title: 'Long-Diagonal Skewer',
        theme: 'Skewer',
        story: 'King and rook are lined up on the same diagonal. Check the king, then collect what is behind it.',
        fen: '8/1k6/8/8/8/4K3/B7/7r w',
        solution: ['a2d5', 'b7b6', 'd5h1'],
        goal: 'material',
        hint: 'The a8–h1 diagonal runs through b7 and h1.',
    },
    {
        id: 'gm-discovered',
        tier: 'grandmaster',
        title: 'Discovered Check',
        theme: 'Discovered attack',
        story: 'Your rook is aimed at the king but your own bishop is in the way. Move it with a threat of its own.',
        fen: 'q3k3/8/8/8/4B3/8/8/4R1K1 w',
        solution: ['e4b7', 'e8d7', 'b7a8'],
        goal: 'material',
        hint: 'Unmask the rook on e1, and put the bishop where it attacks the queen.',
    },
    {
        id: 'gm-deflection',
        tier: 'grandmaster',
        title: 'Back-Rank Deflection',
        theme: 'Deflection · Mate in 2',
        story: "Black's only back-rank defender is the d8 rook. Overload it.",
        fen: '3r2k1/5ppp/8/8/8/8/4RPPP/4R1K1 w',
        solution: ['e2e8', 'd8e8', 'e1e8'],
        goal: 'mate',
        hint: 'Doubled rooks: sacrifice the front one.',
    },
    {
        id: 'gm-rook-roller',
        tier: 'grandmaster',
        title: 'Rook Roller',
        theme: 'Endgame · Mate in 2',
        story: 'Two rooks against a bare king. Cut it off, then deliver mate on the edge.',
        fen: '7k/8/8/8/8/8/R7/1R4K1 w',
        solution: ['a2a7', 'h8g8', 'b1b8'],
        goal: 'mate',
        hint: 'Seal the 7th rank first.',
    },
];

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

const START = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w';

export const LESSONS: Lesson[] = [
    {
        id: 'setup',
        tier: 'explorer',
        from: 'PYP 3',
        title: 'Setting Up the Board',
        blurb: 'Where every piece lives when the game begins.',
        steps: [
            { text: 'Every game starts like this. White is at the bottom. Remember: "light on the right!" The bottom-right square is always light.', fen: START, highlight: ['h1'] },
            { text: 'Queens love their own colour. The white queen starts on a light square, the black queen on a dark one.', fen: START, highlight: ['d1', 'd8'] },
            {
                text: 'Pawns march forward. On their very first move they may jump two squares.',
                fen: START,
                task: { move: 'e2e4', prompt: 'Move the pawn in front of the king two squares forward.' },
            },
        ],
    },
    {
        id: 'rook',
        tier: 'explorer',
        from: 'PYP 3',
        title: 'Protect the Castle! (The Rook)',
        blurb: 'The rook zooms in straight lines.',
        steps: [
            {
                text: 'The rook looks like a castle tower. It moves in straight lines, up, down, left or right, as far as it wants.',
                fen: '3*4/8/8/8/3R4/8/8/8 w',
                task: { move: 'd4d8', prompt: 'Slide the rook up to the star!' },
            },
            {
                text: 'A rook captures by landing on an enemy piece in its path.',
                fen: '8/8/8/8/3R2n1/8/8/8 w',
                task: { move: 'd4g4', prompt: 'Capture the black knight.' },
            },
        ],
    },
    {
        id: 'knight',
        tier: 'explorer',
        from: 'PYP 3',
        title: "The Knight's L-shaped Jump",
        blurb: 'The only piece that can jump over others.',
        steps: [
            {
                text: 'The knight moves in an L: two squares one way, then one square to the side.',
                fen: '8/8/4*3/8/3N4/8/8/8 w',
                task: { move: 'd4e6', prompt: 'Jump the knight onto the star.' },
            },
            {
                text: 'Knights can jump right over pieces, even their own pawns!',
                fen: '8/8/8/8/8/2*5/PPPPPPPP/RNBQKBNR w',
                task: { move: 'b1c3', prompt: 'Jump the b1 knight over the pawns to the star.' },
            },
        ],
    },
    {
        id: 'values',
        tier: 'explorer',
        from: 'PYP 4',
        title: 'Piece Points',
        blurb: 'Which pieces are worth the most?',
        steps: [
            {
                text: 'Pawn = 1 point, Knight = 3, Bishop = 3, Rook = 5, Queen = 9. The king is priceless: you can never trade him!',
                fen: '8/8/8/8/8/8/PNBRQK2/8 w',
            },
            {
                text: 'When you can capture two different pieces, pick the one worth more points.',
                fen: '4k3/8/8/2n1q3/3P4/8/8/K7 w',
                task: { move: 'd4e5', prompt: 'Take the biggest prize with your pawn.' },
            },
        ],
    },
    {
        id: 'mate',
        tier: 'explorer',
        from: 'PYP 5',
        title: 'Check and Checkmate',
        blurb: 'How to win the game.',
        steps: [
            {
                text: 'When a piece attacks the king, that is CHECK. The king must get out of danger straight away.',
                fen: '4k3/8/8/8/8/8/8/4K2R w',
                task: { move: 'h1h8', prompt: 'Give check with your rook.' },
            },
            {
                text: 'CHECKMATE means the king is in check and has no way to escape. That wins the game!',
                fen: '6k1/5ppp/8/8/8/8/8/R5K1 w',
                task: { move: 'a1a8', prompt: 'Find checkmate in one move.' },
            },
        ],
    },
    {
        id: 'center',
        tier: 'grandmaster',
        from: 'MYP 1',
        title: 'Control the Center',
        blurb: 'e4, d4, e5, d5: the squares that decide openings.',
        steps: [
            {
                text: 'The four central squares give pieces maximum scope. A pawn on e4 claims d5 and f5 and opens lines for the queen and bishop.',
                fen: START,
                highlight: ['d4', 'e4', 'd5', 'e5'],
                task: { move: 'e2e4', reply: 'e7e5', prompt: 'Play 1.e4.' },
            },
            {
                text: 'Develop with tempo: a knight on f3 attacks e5 and supports a later d4.',
                fen: 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w',
                task: { move: 'g1f3', reply: 'b8c6', prompt: 'Play 2.Nf3, hitting e5.' },
            },
        ],
    },
    {
        id: 'forks-pins',
        tier: 'grandmaster',
        from: 'MYP 2',
        title: 'Forks & Pins',
        blurb: 'Double attacks and pieces that cannot move.',
        steps: [
            {
                text: 'A fork attacks two targets at once. Knight forks are the hardest to see because the knight does not move in lines.',
                fen: '2q3k1/5ppp/8/3N4/8/8/5PPP/6K1 w',
                task: { move: 'd5e7', prompt: 'Fork the king and queen.' },
            },
            {
                text: 'An absolute pin: the pinned piece cannot move, because the king is behind it.',
                fen: '4k3/8/2n5/8/8/8/8/4KB2 w',
                task: { move: 'f1b5', prompt: 'Pin the c6 knight to the king.' },
            },
        ],
    },
    {
        id: 'pawn-structure',
        tier: 'grandmaster',
        from: 'MYP 3',
        title: 'Pawn Structure',
        blurb: 'Doubled, isolated and passed pawns.',
        steps: [
            { text: 'Doubled pawns: two pawns on one file. They cannot protect each other and are easy to block.', fen: '4k3/8/8/8/8/2P5/2P2PPP/4K3 w', highlight: ['c3', 'c2'] },
            {
                text: "Isolated pawn: no friendly pawns on the neighbouring files. Black's d5 pawn controls the center but must be defended by pieces.",
                fen: '4k3/pp3ppp/8/3p4/8/8/PP3PPP/4K3 w',
                highlight: ['d5'],
            },
            {
                text: 'Passed pawn: no enemy pawn can stop it. "Passed pawns must be pushed."',
                fen: '4k3/5ppp/8/1P6/8/8/5PPP/4K3 w',
                highlight: ['b5'],
                task: { move: 'b5b6', prompt: 'Push the passed pawn.' },
            },
        ],
    },
    {
        id: 'sicilian',
        tier: 'grandmaster',
        from: 'MYP 4',
        title: 'The Sicilian Defence',
        blurb: 'The most popular reply to 1.e4.',
        steps: [
            {
                text: 'Black answers 1.e4 with 1...c5, fighting for d4 from the side instead of mirroring White.',
                fen: START,
                task: { move: 'e2e4', reply: 'c7c5', prompt: 'Play 1.e4.' },
            },
            {
                text: 'White develops and prepares d4.',
                fen: 'rnbqkbnr/pp1ppppp/8/2p5/4P3/8/PPPP1PPP/RNBQKBNR w',
                task: { move: 'g1f3', reply: 'd7d6', prompt: 'Play 2.Nf3.' },
            },
            {
                text: '3.d4 opens the center. Black trades the c-pawn for the d-pawn: a half-open c-file for Black, a development lead for White.',
                fen: 'rnbqkbnr/pp2pppp/3p4/2p5/4P3/5N2/PPPP1PPP/RNBQKB1R w',
                task: { move: 'd2d4', reply: 'c5d4', prompt: 'Play 3.d4.' },
            },
            {
                text: 'Recapture with the knight: this is the Open Sicilian.',
                fen: 'rnbqkbnr/pp2pppp/3p4/8/3pP3/5N2/PPP2PPP/RNBQKB1R w',
                task: { move: 'f3d4', prompt: 'Play 4.Nxd4.' },
            },
        ],
    },
    {
        id: 'lucena',
        tier: 'grandmaster',
        from: 'MYP 5',
        title: 'Rook Endgames: Building a Bridge',
        blurb: 'The Lucena position, the most important rook ending.',
        steps: [
            {
                text: 'White wants to promote on b8 but the king is in the way. First, cut the black king off with a check on the d-file.',
                fen: '1K6/1P1k4/8/8/8/8/r7/5R2 w',
                task: { move: 'f1d1', reply: 'd7e7', prompt: 'Check the king from d1.' },
            },
            {
                text: 'Now the key idea: rook to the 4th rank. When the king steps out and the black rook checks from the side, your rook blocks on the 4th. That is the "bridge".',
                fen: '1K6/1P2k3/8/8/8/8/r7/3R4 w',
                task: { move: 'd1d4', prompt: 'Build the bridge.' },
            },
        ],
    },
];

export { isBlack, isWhite };
