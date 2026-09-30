<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StudentAvatar from '@/Components/Game/StudentAvatar.vue';

defineProps<{
    classroom: { id: number; name: string; code: string };
    students: { id: number; name: string; avatar: string; pin: string }[];
    joinUrl: string;
}>();

const print = () => window.print();
</script>

<template>
    <Head :title="`Login cards · ${classroom.name}`" />

    <div class="min-h-screen bg-white p-4 text-ink sm:p-8">
        <div class="no-print mx-auto mb-6 flex max-w-5xl flex-wrap items-center justify-between gap-3">
            <Link :href="route('classrooms.show', classroom.id)" class="btn btn-sm">← Back to class</Link>
            <p class="font-bold text-ink-soft">Cut along the dashed lines and hand each student their card.</p>
            <button type="button" class="btn btn-sm btn-go" @click="print">Print cards</button>
        </div>

        <p v-if="!students.length" class="text-center font-bold">Add students to the class first.</p>

        <div class="mx-auto grid max-w-5xl grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3">
            <article v-for="s in students" :key="s.id" class="card flex flex-col gap-2 border-2 border-dashed border-ink/40 p-4">
                <div class="flex items-center gap-2">
                    <StudentAvatar :avatar="s.avatar" :size="36" />
                    <span class="font-display text-xl font-extrabold leading-tight">{{ s.name }}</span>
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 text-sm">
                    <dt class="font-bold text-ink-soft">Website</dt>
                    <dd class="break-all font-bold">{{ joinUrl }}</dd>
                    <dt class="font-bold text-ink-soft">Class code</dt>
                    <dd class="font-display text-lg font-extrabold tracking-widest">{{ classroom.code }}</dd>
                    <dt class="font-bold text-ink-soft">My PIN</dt>
                    <dd class="font-display text-lg font-extrabold tracking-widest">{{ s.pin }}</dd>
                </dl>
                <p class="text-xs font-bold text-ink-soft">Keep your PIN secret!</p>
            </article>
        </div>
    </div>
</template>

<style scoped>
.card {
    break-inside: avoid;
}
@media print {
    .no-print {
        display: none;
    }
}
</style>
