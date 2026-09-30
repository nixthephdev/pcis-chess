<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StudentAvatar from '@/Components/Game/StudentAvatar.vue';

const props = defineProps<{
    classroom: { name: string; code: string };
    students: { id: number; name: string; avatar: string }[];
}>();

const chosen = ref<(typeof props.students)[number] | null>(null);
const form = useForm({ student_id: 0, pin: '' });

function choose(s: (typeof props.students)[number]) {
    chosen.value = s;
    form.student_id = s.id;
    form.pin = '';
    form.clearErrors();
}

function press(d: string) {
    if (form.processing || form.pin.length >= 4) return;
    form.pin += d;
    if (form.pin.length === 4) submit();
}

function back() {
    form.pin = form.pin.slice(0, -1);
}

function submit() {
    form.post(route('join.login', props.classroom.code), {
        onError: () => (form.pin = ''),
    });
}

function onKey(e: KeyboardEvent) {
    if (/^\d$/.test(e.key)) press(e.key);
    else if (e.key === 'Backspace') back();
}

const dots = computed(() => [0, 1, 2, 3].map((i) => i < form.pin.length));
</script>

<template>
    <Head :title="classroom.name" />

    <div class="quest-bg px-4 py-8">
        <main class="mx-auto flex max-w-4xl flex-col gap-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="label-caps">Class {{ classroom.code }}</p>
                    <h1 class="font-display text-4xl font-extrabold">{{ classroom.name }}</h1>
                </div>
                <Link :href="route('join')" class="btn btn-sm">← Different code</Link>
            </header>

            <template v-if="!chosen">
                <h2 class="font-display text-2xl font-extrabold">Tap your name</h2>
                <p v-if="!students.length" class="chunky p-5 font-bold">
                    Your coach hasn't added any students to this class yet.
                </p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <button
                        v-for="s in students"
                        :key="s.id"
                        type="button"
                        class="chunky flex min-h-[64px] items-center gap-3 p-3 text-left transition-transform hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-tang"
                        @click="choose(s)"
                    >
                        <StudentAvatar :avatar="s.avatar" :size="44" />
                        <span class="min-w-0 break-words font-display text-lg font-extrabold leading-tight">{{ s.name }}</span>
                    </button>
                </div>
            </template>

            <section v-else class="chunky mx-auto flex w-full max-w-sm flex-col items-center gap-5 p-6" tabindex="-1" @keydown="onKey">
                <StudentAvatar :avatar="chosen.avatar" :size="72" />
                <div class="text-center">
                    <h2 class="font-display text-3xl font-extrabold">Hi, {{ chosen.name }}!</h2>
                    <p class="font-bold text-ink-soft">Type your secret 4-number PIN.</p>
                </div>

                <div class="flex gap-3" aria-live="polite" :aria-label="`${form.pin.length} of 4 numbers typed`">
                    <span
                        v-for="(on, i) in dots"
                        :key="i"
                        class="h-6 w-6 rounded-full border-[3px] border-ink transition-colors"
                        :class="on ? 'bg-ink' : 'bg-white'"
                    />
                </div>
                <p v-if="form.errors.pin" class="text-center font-bold text-berry" role="alert">{{ form.errors.pin }}</p>

                <div class="grid w-full grid-cols-3 gap-3">
                    <button
                        v-for="d in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                        :key="d"
                        type="button"
                        class="btn h-16 text-3xl"
                        :disabled="form.processing"
                        @click="press(d)"
                    >
                        {{ d }}
                    </button>
                    <button type="button" class="btn h-16 text-base" @click="chosen = null">Not me</button>
                    <button type="button" class="btn h-16 text-3xl" :disabled="form.processing" @click="press('0')">0</button>
                    <button type="button" class="btn h-16 text-2xl" aria-label="Delete" @click="back">⌫</button>
                </div>
            </section>
        </main>
    </div>
</template>
