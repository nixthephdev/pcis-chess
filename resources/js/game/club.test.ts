import { describe, expect, it } from 'vitest';
import { applyMove, inCheck, isPiece, legalMoves } from './engine';
import { evaluate, fromFen, isMated, LESSONS, PUZZLES, san, uci } from './club';

function play(fen: string, moves: string[]) {
    let b = fromFen(fen);
    const notation: string[] = [];
    let captured = '.';
    for (const m of moves) {
        const [f, t] = uci(m);
        expect(legalMoves(b, f), `${m} in ${fen}`).toContain(t);
        notation.push(san(b, f, t));
        captured = b[t];
        b = applyMove(b, f, t);
    }
    return { board: b, notation, captured };
}

describe('club puzzles', () => {
    it.each(PUZZLES.map((p) => [p.id, p] as const))('%s is legal and its solution works', (_, p) => {
        expect(inCheck(fromFen(p.fen), 'b'), 'side not to move is in check').toBe(false);
        expect(p.solution.length % 2).toBe(1);

        const { board, captured } = play(p.fen, p.solution);
        if (p.goal === 'mate') expect(isMated(board, 'b')).toBe(true);
        else expect(isPiece(captured)).toBe(true);
    });

    it('writes standard notation', () => {
        expect(play(PUZZLES.find((p) => p.id === 'gm-deflection')!.fen, ['e2e8', 'd8e8', 'e1e8']).notation).toEqual(['Re8+', 'Rxe8', 'Rxe8#']);
        expect(play('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w', ['e2e4', 'e7e5', 'g1f3']).notation).toEqual(['e4', 'e5', 'Nf3']);
    });

    it('sees the fork win material', () => {
        const fork = PUZZLES.find((p) => p.id === 'gm-royal-fork')!;
        // Knight against queen is -6 on material; winning the queen leaves White a knight up.
        expect(evaluate(fromFen(fork.fen), 'w').score).toBeGreaterThan(2);
    });

    it('sees mate', () => {
        expect(evaluate(fromFen('6k1/5ppp/8/8/8/8/8/R5K1 w'), 'w').label).toBe('+M');
    });
});

describe('club lessons', () => {
    it.each(LESSONS.flatMap((l) => l.steps.map((s, i) => [`${l.id} step ${i + 1}`, s] as const)))('%s is playable', (_, step) => {
        fromFen(step.fen);
        if (step.task) play(step.fen, step.task.reply ? [step.task.move, step.task.reply] : [step.task.move]);
    });
});
