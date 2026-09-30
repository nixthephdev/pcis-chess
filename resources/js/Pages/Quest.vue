<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Level, rankFor, SECTIONS, TOTAL_STARS, World, WORLDS } from '@/game/catalog';
import { Leaderboard, loadGuestStars, saveGuestStars, saveStudentResult, StarMap } from '@/game/progress';
import { muted, sfx } from '@/game/sound';
import LevelPlayer from '@/Components/Game/LevelPlayer.vue';
import PieceIcon from '@/Components/Game/PieceIcon.vue';
import StarIcon from '@/Components/Game/StarIcon.vue';
import StudentAvatar from '@/Components/Game/StudentAvatar.vue';

const props = defineProps<{
    student: { name: string; avatar: string; classroom: string } | null;
    progress: StarMap | null;
    leaderboard: Leaderboard | null;
}>();

const stars = ref<StarMap>(props.progress ? { ...props.progress } : loadGuestStars());
const board = ref<Leaderboard | null>(props.leaderboard);

// A coach can share a link like /quest?level=knight-2 to send students straight to a level.
const linked = new URLSearchParams(window.location.search).get('level');
const current = ref<Level | null>(WORLDS.flatMap((w) => w.levels).find((l) => l.id === linked) ?? null);
const saveProblem = ref(false);

const starsFor = (id: string) => stars.value[id] ?? 0;
const total = computed(() => Object.values(stars.value).reduce((a, b) => a + b, 0));
const rank = computed(() => rankFor(total.value));

const SECTION_BG: Record<string, string> = { sun: 'var(--sun)', tang: 'var(--tang)', berry: 'var(--berry)' };

function worldStars(w: World) {
    return w.levels.reduce((n, l) => n + starsFor(l.id), 0);
}
const worldCleared = (w: World) => w.levels.every((l) => starsFor(l.id) > 0);

function openWorld(w: World) {
    sfx.tap();
    open(w.levels.find((l) => starsFor(l.id) === 0) ?? w.levels[0]);
}

function open(level: Level) {
    current.value = level;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function onComplete(level: Level, earned: number, moves: number) {
    stars.value = { ...stars.value, [level.id]: Math.max(starsFor(level.id), earned) };

    if (!props.student) {
        saveGuestStars(stars.value);
        return;
    }

    const res = await saveStudentResult({ level_id: level.id, stars: earned, moves });
    saveProblem.value = !res;
    if (res) {
        const merged = { ...stars.value };
        for (const [id, s] of Object.entries(res.progress)) merged[id] = Math.max(merged[id] ?? 0, s);
        stars.value = merged;
        board.value = res.leaderboard;
    }
}
</script>

<template>
    <Head title="Quest" />

    <div class="quest-bg px-4 pb-10 pt-4 sm:px-6">
        <LevelPlayer
            v-if="current"
            :level="current"
            :stars-for="starsFor"
            @complete="onComplete"
            @open="open"
            @exit="current = null"
        />

        <div v-else class="mx-auto flex max-w-6xl flex-col gap-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <img src="/icon.svg" alt="" class="h-12 w-12 -rotate-6 sm:h-14 sm:w-14" />
                    <div>
                        <h1 class="font-display text-3xl font-extrabold leading-none sm:text-4xl">Chess Quest</h1>
                        <p class="font-bold text-ink-soft">Learn chess by playing. Collect every star!</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-sm" :aria-pressed="muted" @click="(muted = !muted), sfx.tap()">
                        Sound: {{ muted ? 'off' : 'on' }}
                    </button>
                    <Link v-if="student" :href="route('student.logout')" method="post" as="button" class="btn btn-sm">Log out</Link>
                    <Link v-else :href="route('join')" class="btn btn-sm btn-go">Join your class</Link>
                </div>
            </header>

            <p v-if="saveProblem" class="chunky bg-[#ffe6e7] px-4 py-3 font-bold" role="status">
                Your stars are safe on this device, but we couldn't reach the server. They'll be sent next time you finish a level.
            </p>

            <section class="chunky grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="flex items-center gap-4">
                    <StudentAvatar :avatar="student?.avatar ?? 'pawn'" :size="64" />
                    <div class="min-w-0">
                        <p class="label-caps">{{ student ? student.classroom : 'Playing as a guest' }}</p>
                        <h2 class="truncate font-display text-3xl font-extrabold">Hi, {{ student?.name ?? 'Explorer' }}!</h2>
                        <span class="mt-1 inline-flex items-center gap-2 rounded-full bg-ink py-1 pl-2 pr-4 font-display font-extrabold text-sun">
                            <span class="h-6 w-6"><PieceIcon :piece="rank.piece" /></span>{{ rank.title }}
                        </span>
                    </div>
                </div>
                <div class="text-center">
                    <div class="flex items-center justify-center gap-2 font-display text-5xl font-extrabold leading-none">
                        <StarIcon class="h-10 w-10" />{{ total }}
                    </div>
                    <span class="text-sm font-bold text-ink-soft">of {{ TOTAL_STARS }} stars</span>
                </div>
                <div class="h-4 overflow-hidden rounded-full border-[3px] border-ink bg-white sm:col-span-2" aria-hidden="true">
                    <div class="progress-fill h-full" :style="{ width: (total / TOTAL_STARS) * 100 + '%' }" />
                </div>
                <p v-if="!student" class="text-sm font-bold text-ink-soft sm:col-span-2">
                    Guest stars are saved on this device only. Join your class to save them to your account and appear on the leaderboard.
                </p>
            </section>

            <div class="grid gap-6 lg:grid-cols-[1fr_300px] lg:items-start">
                <div class="flex flex-col gap-6">
                    <section v-for="(sec, si) in SECTIONS" :key="sec.title">
                        <h2 class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 font-display text-2xl font-extrabold">
                            <span class="rounded-full border-2 border-ink px-3 font-sans text-xs uppercase tracking-widest" :style="{ background: SECTION_BG[sec.color] }">
                                {{ si + 1 }}
                            </span>
                            {{ sec.title }}
                            <small class="font-sans text-sm font-bold text-ink-soft">{{ sec.subtitle }}</small>
                        </h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <button
                                v-for="w in WORLDS.filter((w) => w.section === si)"
                                :key="w.id"
                                type="button"
                                class="world chunky grid grid-cols-[64px_1fr] items-center gap-x-4 gap-y-1 p-4 text-left"
                                :class="{ 'bg-[#fff9e0]': worldCleared(w) }"
                                @click="openWorld(w)"
                            >
                                <span class="row-span-2 grid h-16 w-16 place-items-center rounded-xl border-[3px] border-ink p-1" :style="{ background: SECTION_BG[sec.color] }">
                                    <PieceIcon :piece="w.piece.toUpperCase()" />
                                </span>
                                <span class="font-display text-xl font-extrabold leading-tight">{{ w.name }}</span>
                                <span class="text-sm font-bold leading-snug text-ink-soft">{{ w.blurb }}</span>
                                <span class="col-span-2 mt-2 flex items-center gap-2 text-sm font-extrabold text-ink-soft">
                                    <StarIcon :filled="worldStars(w) > 0" class="h-4 w-4" />
                                    {{ worldStars(w) }} / {{ w.levels.length * 3 }} · {{ w.levels.length }} levels
                                    <span v-if="worldCleared(w)" class="text-leaf">· cleared!</span>
                                </span>
                            </button>
                        </div>
                    </section>
                </div>

                <aside class="chunky p-5 lg:sticky lg:top-4">
                    <h2 class="font-display text-2xl font-extrabold">Class leaderboard</h2>
                    <template v-if="board">
                        <p v-if="board.me" class="mb-3 text-sm font-bold text-ink-soft">You are number {{ board.me }} in your class.</p>
                        <ol class="flex flex-col gap-2">
                            <li
                                v-for="(row, i) in board.rows"
                                :key="i"
                                class="flex items-center gap-3 rounded-xl px-2 py-1"
                                :class="{ 'bg-sun/40 ring-2 ring-ink': row.me }"
                            >
                                <span class="w-6 text-right font-display text-lg font-extrabold tabular-nums">{{ i + 1 }}</span>
                                <StudentAvatar :avatar="row.avatar" :size="34" />
                                <span class="min-w-0 flex-1 truncate font-bold">{{ row.name }}</span>
                                <span class="flex items-center gap-1 font-display font-extrabold tabular-nums"><StarIcon class="h-4 w-4" />{{ row.stars }}</span>
                            </li>
                        </ol>
                    </template>
                    <p v-else class="mt-2 font-bold text-ink-soft">
                        Join your class with the code from your coach to see how your stars compare with your classmates.
                    </p>
                </aside>
            </div>

            <footer class="text-center text-xs font-bold text-ink-soft">
                Chess piece artwork by Cburnett,
                <a class="underline" href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank" rel="noopener">CC BY-SA 3.0</a>.
            </footer>
        </div>
    </div>
</template>

<style scoped>
.world {
    transition: transform 0.12s;
}
.world:hover {
    transform: translateY(-3px) rotate(-0.5deg);
}
.world:focus-visible {
    outline: 3px solid var(--tang);
    outline-offset: 3px;
}
.progress-fill {
    background: repeating-linear-gradient(45deg, var(--sun) 0 10px, #ffc21a 10px 20px);
    transition: width 0.6s;
}
</style>
