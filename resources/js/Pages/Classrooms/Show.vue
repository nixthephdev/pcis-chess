<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StudentAvatar from '@/Components/Game/StudentAvatar.vue';
import StarIcon from '@/Components/Game/StarIcon.vue';
import type { PageProps } from '@/types';

interface StudentRow {
    id: number;
    name: string;
    avatar: string;
    pin: string;
    stars: number;
    levels: number;
    worlds: Record<string, number>;
    last_played_at: string | null;
}

const props = defineProps<{
    classroom: { id: number; name: string; code: string };
    students: StudentRow[];
    worlds: { id: string; name: string; section: number; levels: number }[];
    maxStars: number;
    joinUrl: string;
}>();

const page = usePage<PageProps>();
const status = computed(() => page.props.flash.status);

const addForm = useForm({ names: '' });
function addStudents() {
    addForm.post(route('classrooms.students.store', props.classroom.id), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
}

const renameForm = useForm({ name: props.classroom.name });
const renaming = ref(false);
function rename() {
    renameForm.patch(route('classrooms.update', props.classroom.id), {
        preserveScroll: true,
        onSuccess: () => (renaming.value = false),
    });
}

const shownPins = ref(new Set<number>());
function togglePin(id: number) {
    const s = new Set(shownPins.value);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    shownPins.value = s;
}

// Two-tap confirmation: the first tap arms the button, the second within 4 seconds acts.
const armed = ref<string | null>(null);
let armTimer: number | undefined;
function confirmThen(key: string, action: () => void) {
    if (armed.value === key) {
        armed.value = null;
        action();
        return;
    }
    armed.value = key;
    clearTimeout(armTimer);
    armTimer = window.setTimeout(() => (armed.value = null), 4000);
}

const resetPin = (s: StudentRow) =>
    confirmThen('pin' + s.id, () => router.post(route('classrooms.students.pin', [props.classroom.id, s.id]), {}, { preserveScroll: true }));
const removeStudent = (s: StudentRow) =>
    confirmThen('rm' + s.id, () => router.delete(route('classrooms.students.destroy', [props.classroom.id, s.id]), { preserveScroll: true }));
const deleteClass = () => confirmThen('class', () => router.delete(route('classrooms.destroy', props.classroom.id)));

const projecting = ref(false);

const sort = ref<'name' | 'stars' | 'recent'>('name');
const sorted = computed(() => {
    const rows = [...props.students];
    if (sort.value === 'stars') rows.sort((a, b) => b.stars - a.stars || a.name.localeCompare(b.name));
    else if (sort.value === 'recent') rows.sort((a, b) => (b.last_played_at ?? '').localeCompare(a.last_played_at ?? ''));
    return rows;
});

const classStars = computed(() => props.students.reduce((n, s) => n + s.stars, 0));
const activeThisWeek = computed(() => {
    const weekAgo = Date.now() - 7 * 864e5;
    return props.students.filter((s) => s.last_played_at && Date.parse(s.last_played_at) > weekAgo).length;
});

function ago(iso: string | null) {
    if (!iso) return 'Not yet';
    const mins = Math.round((Date.now() - Date.parse(iso)) / 60000);
    if (mins < 2) return 'Just now';
    if (mins < 60) return `${mins} min ago`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs} h ago`;
    const days = Math.round(hrs / 24);
    return days === 1 ? 'Yesterday' : `${days} days ago`;
}

const worldMax = (w: { levels: number }) => w.levels * 3;
</script>

<template>
    <Head :title="classroom.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="route('dashboard')" class="text-sm font-bold text-ink-soft hover:underline">← My classes</Link>
                    <form v-if="renaming" class="mt-1 flex flex-wrap gap-2" @submit.prevent="rename">
                        <input
                            v-model="renameForm.name"
                            maxlength="60"
                            aria-label="Class name"
                            class="rounded-xl border-2 border-ink/30 font-display text-xl font-extrabold focus:border-tang focus:ring-tang"
                        />
                        <button type="submit" class="btn btn-sm btn-go" :disabled="renameForm.processing">Save</button>
                        <button type="button" class="btn btn-sm" @click="renaming = false">Cancel</button>
                    </form>
                    <h2 v-else class="flex items-center gap-2 font-display text-3xl font-extrabold leading-tight text-ink">
                        {{ classroom.name }}
                        <button type="button" class="text-sm font-bold text-ink-soft underline" @click="renaming = true">Rename</button>
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-xl bg-ink px-3 py-1 font-display text-2xl font-extrabold tracking-[0.25em] text-sun">{{ classroom.code }}</span>
                    <button type="button" class="btn btn-sm" @click="projecting = true">Show code on screen</button>
                    <Link :href="route('classrooms.cards', classroom.id)" class="btn btn-sm">Login cards</Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-6">
                <p v-if="status" class="chunky bg-[#e3f8ec] px-4 py-3 font-bold" role="status">{{ status }}</p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="chunky p-4">
                        <div class="label-caps">Students</div>
                        <div class="font-display text-4xl font-extrabold tabular-nums">{{ students.length }}</div>
                    </div>
                    <div class="chunky p-4">
                        <div class="label-caps">Played this week</div>
                        <div class="font-display text-4xl font-extrabold tabular-nums">{{ activeThisWeek }}</div>
                    </div>
                    <div class="chunky p-4">
                        <div class="label-caps">Stars earned by the class</div>
                        <div class="flex items-center gap-2 font-display text-4xl font-extrabold tabular-nums"><StarIcon class="h-8 w-8" />{{ classStars }}</div>
                    </div>
                </div>

                <form class="chunky flex flex-col gap-3 p-5" @submit.prevent="addStudents">
                    <label for="names" class="font-display text-xl font-extrabold">Add students</label>
                    <p class="text-sm font-bold text-ink-soft">
                        One name per line. Use a first name and last initial (for example "Maya R.") so you don't store full names. Each student gets a 4-digit PIN.
                    </p>
                    <textarea
                        id="names"
                        v-model="addForm.names"
                        rows="4"
                        class="rounded-xl border-2 border-ink/30 focus:border-tang focus:ring-tang"
                        placeholder="Maya R.&#10;Liam T.&#10;Aiko S."
                    />
                    <p v-if="addForm.errors.names" class="text-sm font-bold text-berry">{{ addForm.errors.names }}</p>
                    <div>
                        <button type="submit" class="btn btn-go" :disabled="addForm.processing || !addForm.names.trim()">Add students</button>
                    </div>
                </form>

                <section v-if="students.length" class="chunky overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b-[3px] border-ink p-4">
                        <h3 class="font-display text-xl font-extrabold">Progress</h3>
                        <div class="flex gap-1" role="group" aria-label="Sort students">
                            <button
                                v-for="opt in (['name', 'stars', 'recent'] as const)"
                                :key="opt"
                                type="button"
                                class="rounded-lg border-2 border-ink px-3 py-1 text-sm font-extrabold"
                                :class="sort === opt ? 'bg-ink text-white' : 'bg-white'"
                                :aria-pressed="sort === opt"
                                @click="sort = opt"
                            >
                                {{ opt === 'name' ? 'Name' : opt === 'stars' ? 'Most stars' : 'Recently played' }}
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left">
                            <thead class="bg-mint text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                <tr>
                                    <th class="px-4 py-2">Student</th>
                                    <th class="px-2 py-2">PIN</th>
                                    <th class="px-2 py-2 text-right">Stars</th>
                                    <th class="px-2 py-2">Worlds</th>
                                    <th class="px-2 py-2">Last played</th>
                                    <th class="px-4 py-2 text-right"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in sorted" :key="s.id" class="border-t-2 border-ink/10 align-middle">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <StudentAvatar :avatar="s.avatar" :size="36" />
                                            <span class="font-bold">{{ s.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3">
                                        <button type="button" class="rounded-lg border-2 border-ink/30 px-2 py-1 font-mono font-bold tabular-nums" @click="togglePin(s.id)">
                                            {{ shownPins.has(s.id) ? s.pin : '••••' }}
                                        </button>
                                    </td>
                                    <td class="px-2 py-3 text-right font-display text-lg font-extrabold tabular-nums">
                                        {{ s.stars }}<span class="text-sm text-ink-soft"> / {{ maxStars }}</span>
                                    </td>
                                    <td class="px-2 py-3">
                                        <div class="flex gap-1">
                                            <span
                                                v-for="w in worlds"
                                                :key="w.id"
                                                class="relative h-7 w-3 overflow-hidden rounded-sm border border-ink/40 bg-white"
                                                :title="`${w.name}: ${s.worlds[w.id]} of ${worldMax(w)} stars`"
                                            >
                                                <span
                                                    class="absolute inset-x-0 bottom-0"
                                                    :class="s.worlds[w.id] >= worldMax(w) ? 'bg-leaf' : 'bg-sun'"
                                                    :style="{ height: (s.worlds[w.id] / worldMax(w)) * 100 + '%' }"
                                                />
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 text-sm font-bold text-ink-soft">{{ ago(s.last_played_at) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="rounded-lg border-2 border-ink px-2 py-1 text-sm font-extrabold" @click="resetPin(s)">
                                                {{ armed === 'pin' + s.id ? 'Tap again' : 'New PIN' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg border-2 border-berry px-2 py-1 text-sm font-extrabold text-berry"
                                                @click="removeStudent(s)"
                                            >
                                                {{ armed === 'rm' + s.id ? 'Tap to remove' : 'Remove' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="border-t-2 border-ink/10 px-4 py-2 text-xs font-bold text-ink-soft">
                        Each bar is one world, in map order: {{ worlds.map((w) => w.name).join(', ') }}. Green means every star in that world.
                    </p>
                </section>

                <div class="flex justify-end">
                    <button type="button" class="rounded-xl border-2 border-berry px-4 py-2 font-extrabold text-berry" @click="deleteClass">
                        {{ armed === 'class' ? 'Tap again to delete this class and all its progress' : 'Delete class' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="projecting"
            class="fixed inset-0 z-50 grid place-items-center bg-ink p-6 text-center text-white"
            role="dialog"
            aria-modal="true"
            aria-label="Class code"
            @click="projecting = false"
        >
            <div class="flex flex-col items-center gap-6">
                <p class="font-display text-3xl font-extrabold text-sun sm:text-4xl">Join {{ classroom.name }}</p>
                <p class="text-xl font-bold sm:text-2xl">Go to <span class="text-sun">{{ joinUrl }}</span> and type:</p>
                <p class="font-display text-7xl font-extrabold tracking-[0.2em] sm:text-9xl">{{ classroom.code }}</p>
                <button type="button" class="btn btn-go">Close</button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
