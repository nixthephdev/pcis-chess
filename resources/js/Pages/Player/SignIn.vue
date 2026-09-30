<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ username: '', password: '' });

function submit() {
    form.post(route('player.login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <div class="quest-bg grid place-items-center px-4 py-10">
        <main class="chunky flex w-full max-w-md flex-col gap-5 p-6 sm:p-8">
            <div class="text-center">
                <img src="/icon.svg" alt="" class="mx-auto h-16 w-16 -rotate-6" />
                <h1 class="font-display text-4xl font-extrabold">Sign in</h1>
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div>
                    <label for="username" class="label-caps">Username</label>
                    <input
                        id="username"
                        v-model="form.username"
                        type="text"
                        class="field"
                        autocomplete="username"
                        autocapitalize="off"
                        spellcheck="false"
                        required
                        autofocus
                    />
                </div>
                <div>
                    <label for="password" class="label-caps">Password</label>
                    <input id="password" v-model="form.password" type="password" class="field" autocomplete="current-password" required />
                </div>
                <p v-if="form.errors.username" class="font-bold text-berry" role="alert">{{ form.errors.username }}</p>

                <button type="submit" class="btn btn-go text-xl" :disabled="form.processing">Sign in</button>
            </form>

            <div class="flex flex-col gap-1 text-center font-bold text-ink-soft">
                <p>
                    New here?
                    <Link :href="route('player.register')" class="text-ink underline underline-offset-4">Create an account</Link>
                </p>
                <p>
                    Got a class code?
                    <Link :href="route('join')" class="text-ink underline underline-offset-4">Join your class</Link>
                </p>
            </div>
        </main>
    </div>
</template>
