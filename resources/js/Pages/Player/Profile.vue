<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { rankFor, TOTAL_STARS, WORLDS } from '@/game/catalog';
import { StarMap } from '@/game/progress';
import PieceIcon from '@/Components/Game/PieceIcon.vue';
import StarIcon from '@/Components/Game/StarIcon.vue';
import StudentAvatar from '@/Components/Game/StudentAvatar.vue';

const props = defineProps<{
    player: { username: string; avatar: string; joined: string; lastPlayed: string | null };
    progress: StarMap;
    isMe: boolean;
}>();

const total = computed(() => Object.values(props.progress).reduce((a, b) => a + b, 0));
const rank = computed(() => rankFor(total.value));

const worlds = computed(() =>
    WORLDS.map((w) => ({
        name: w.name,
        piece: w.piece.toUpperCase(),
        stars: w.levels.reduce((n, l) => n + (props.progress[l.id] ?? 0), 0),
        max: w.levels.length * 3,
        cleared: w.levels.every((l) => (props.progress[l.id] ?? 0) > 0),
    })),
);
const cleared = computed(() => worlds.value.filter((w) => w.cleared).length);

const date = (iso: string) => new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
</script>

<template>
    <Head :title="player.username" />

    <div class="quest-bg px-4 py-8">
        <main class="mx-auto flex max-w-3xl flex-col gap-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="route('quest')" class="btn btn-sm">← Back to the quest</Link>
                <Link v-if="isMe" :href="route('player.edit')" class="btn btn-sm">Settings</Link>
            </header>

            <section class="chunky flex flex-wrap items-center gap-5 p-6">
                <StudentAvatar :avatar="player.avatar" :size="88" />
                <div class="min-w-0 flex-1">
                    <h1 class="break-words font-display text-4xl font-extrabold">{{ player.username }}</h1>
                    <span class="mt-1 inline-flex items-center gap-2 rounded-full bg-ink py-1 pl-2 pr-4 font-display font-extrabold text-sun">
                        <span class="h-6 w-6"><PieceIcon :piece="rank.piece" /></span>{{ rank.title }}
                    </span>
                    <p class="mt-2 text-sm font-bold text-ink-soft">
                        Joined {{ date(player.joined) }}<template v-if="player.lastPlayed"> · Last played {{ date(player.lastPlayed) }}</template>
                    </p>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-4">
                <div class="chunky p-5 text-center">
                    <div class="flex items-center justify-center gap-2 font-display text-4xl font-extrabold"><StarIcon class="h-8 w-8" />{{ total }}</div>
                    <span class="text-sm font-bold text-ink-soft">of {{ TOTAL_STARS }} stars</span>
                </div>
                <div class="chunky p-5 text-center">
                    <div class="font-display text-4xl font-extrabold">{{ cleared }}</div>
                    <span class="text-sm font-bold text-ink-soft">of {{ worlds.length }} worlds cleared</span>
                </div>
            </section>

            <section class="chunky p-5">
                <h2 class="mb-3 font-display text-2xl font-extrabold">Worlds</h2>
                <ul class="flex flex-col gap-3">
                    <li v-for="w in worlds" :key="w.name" class="grid grid-cols-[36px_1fr_auto] items-center gap-3">
                        <span class="h-9 w-9"><PieceIcon :piece="w.piece" /></span>
                        <div class="min-w-0">
                            <span class="font-bold">{{ w.name }}</span>
                            <div class="mt-1 h-3 overflow-hidden rounded-full border-2 border-ink bg-white" aria-hidden="true">
                                <div class="h-full bg-sun" :style="{ width: (w.stars / w.max) * 100 + '%' }" />
                            </div>
                        </div>
                        <span class="flex items-center gap-1 font-display font-extrabold tabular-nums"><StarIcon class="h-4 w-4" />{{ w.stars }}/{{ w.max }}</span>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
