<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ code: '' });

function submit() {
    form.post(route('join.lookup'));
}
</script>

<template>
    <Head title="Join your class" />

    <div class="quest-bg grid place-items-center px-4 py-10">
        <main class="chunky flex w-full max-w-md flex-col gap-5 p-6 text-center sm:p-8">
            <img src="/icon.svg" alt="" class="mx-auto h-16 w-16 -rotate-6" />
            <h1 class="font-display text-4xl font-extrabold">Join your class</h1>
            <p class="font-bold text-ink-soft">Type the class code your coach gave you.</p>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <label for="code" class="sr-only">Class code</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="text"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    maxlength="8"
                    placeholder="ABC123"
                    class="rounded-2xl border-[3px] border-ink py-4 text-center font-display text-4xl font-extrabold uppercase tracking-[0.3em] placeholder:text-ink/20 focus:border-tang focus:ring-tang"
                    autofocus
                />
                <p v-if="form.errors.code" class="font-bold text-berry" role="alert">{{ form.errors.code }}</p>
                <button type="submit" class="btn btn-go text-xl" :disabled="form.processing || form.code.trim().length < 4">Next →</button>
            </form>

            <Link :href="route('quest')" class="font-bold text-ink-soft underline underline-offset-4">No code? Play as a guest</Link>
        </main>
    </div>
</template>
