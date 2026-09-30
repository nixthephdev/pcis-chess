import axios from 'axios';

export type StarMap = Record<string, number>;

export interface LeaderRow {
    name: string;
    avatar: string;
    stars: number;
    me: boolean;
}

export interface Leaderboard {
    rows: LeaderRow[];
    me: number | null;
}

export interface SaveResult {
    progress: StarMap;
    leaderboard: Leaderboard;
}

interface PendingSave {
    level_id: string;
    stars: number;
    moves: number | null;
}

const GUEST_KEY = 'chessquest.guest.v1';
const PENDING_KEY = 'chessquest.pending.v1';

function read<T>(key: string, fallback: T): T {
    try {
        return JSON.parse(localStorage.getItem(key) ?? '') ?? fallback;
    } catch {
        return fallback;
    }
}

function write(key: string, value: unknown) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        /* storage blocked: progress lasts for this visit only */
    }
}

export const loadGuestStars = (): StarMap => read<StarMap>(GUEST_KEY, {});
export const saveGuestStars = (stars: StarMap) => write(GUEST_KEY, stars);

async function post(save: PendingSave): Promise<SaveResult> {
    const { data } = await axios.post<SaveResult>(route('quest.progress'), save);
    return data;
}

/**
 * Saves a finished level for a signed-in student. If the network drops,
 * the result is kept on the device and sent with the next successful save.
 */
export async function saveStudentResult(save: PendingSave): Promise<SaveResult | null> {
    const queue = [...read<PendingSave[]>(PENDING_KEY, []), save];
    let last: SaveResult | null = null;

    while (queue.length) {
        try {
            last = await post(queue[0]);
            queue.shift();
        } catch (e) {
            // A 4xx means the server rejected this entry for good (bad level id, signed out); drop it.
            const status = axios.isAxiosError(e) ? e.response?.status : undefined;
            if (status && status >= 400 && status < 500 && status !== 429) {
                queue.shift();
                continue;
            }
            break;
        }
    }

    write(PENDING_KEY, queue);
    return last;
}
