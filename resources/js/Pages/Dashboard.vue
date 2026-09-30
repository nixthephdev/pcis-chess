<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    classrooms: { id: number; name: string; code: string; students_count: number }[];
}>();

const form = useForm({ name: '' });

function create() {
    form.post(route('classrooms.store'));
}
</script>

<template>
    <Head title="My classes" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-display text-2xl font-extrabold leading-tight text-ink">My classes</h2>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-col gap-6">
                <form class="chunky flex flex-col gap-3 p-5 sm:flex-row sm:items-end" @submit.prevent="create">
                    <div class="flex-1">
                        <label for="class-name" class="label-caps">New class</label>
                        <input
                            id="class-name"
                            v-model="form.name"
                            type="text"
                            maxlength="60"
                            placeholder="e.g. Grade 4 Chess Club"
                            class="mt-1 w-full rounded-xl border-2 border-ink/30 text-lg focus:border-tang focus:ring-tang"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm font-bold text-berry">{{ form.errors.name }}</p>
                    </div>
                    <button type="submit" class="btn btn-go" :disabled="form.processing || !form.name.trim()">Create class</button>
                </form>

                <p v-if="!classrooms.length" class="text-center font-bold text-ink-soft">
                    Create your first class. Each class gets a code that students type to join.
                </p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="c in classrooms"
                        :key="c.id"
                        :href="route('classrooms.show', c.id)"
                        class="chunky flex flex-col gap-2 p-5 transition-transform hover:-translate-y-0.5"
                    >
                        <span class="font-display text-2xl font-extrabold leading-tight">{{ c.name }}</span>
                        <span class="flex items-center justify-between text-sm font-bold text-ink-soft">
                            <span>{{ c.students_count }} student{{ c.students_count === 1 ? '' : 's' }}</span>
                            <span class="rounded-lg bg-ink px-2 py-0.5 font-display text-base tracking-widest text-sun">{{ c.code }}</span>
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
