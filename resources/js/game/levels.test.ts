import { describe, expect, it } from 'vitest';
import { findWinningMove, inCheck, isBlack, isWhite } from './engine';
import { isPuzzle, parFor, WORLDS } from './catalog';

const levels = WORLDS.flatMap((w) => w.levels);

describe('levels.json', () => {
    it.each(levels.map((l) => [l.id, l] as const))('%s is well formed and solvable', (_id, level) => {
        expect(level.board).toHaveLength(64);
        expect(level.board.every((c) => /^[.*KQRBNPkqrbnp]$/.test(c))).toBe(true);
        expect(level.board.some(isWhite)).toBe(true);

        if (isPuzzle(level)) {
            expect(level.board.filter((c) => c === 'k')).toHaveLength(1);
            expect(inCheck(level.board, 'b')).toBe(false);
            expect(findWinningMove(level.board, level.goal as 'check' | 'mate')).not.toBeNull();
        } else {
            if (level.goal === 'stars') expect(level.board).toContain('*');
            if (level.goal === 'capture') expect(level.board.some(isBlack)).toBe(true);
            expect(parFor(level)).toBeGreaterThan(0);
        }
    });

    it('has unique world ids', () => {
        const ids = WORLDS.map((w) => w.id);
        expect(new Set(ids).size).toBe(ids.length);
    });
});
