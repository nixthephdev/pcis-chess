<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PieceIcon from '@/Components/Game/PieceIcon.vue';

defineProps<{ canRegister: boolean }>();
</script>

<template>
    <Head title="Welcome" />

    <div class="quest-bg grid place-items-center px-4 py-10">
        <main class="flex w-full max-w-3xl flex-col items-center gap-8 text-center">
            <div class="flex flex-col items-center gap-3">
                <img src="/icon.svg" alt="" class="h-24 w-24 -rotate-6" />
                <h1 class="font-display text-5xl font-extrabold leading-none sm:text-6xl">Chess Quest</h1>
                <p class="max-w-md text-lg font-bold text-ink-soft">
                    Learn how every piece moves, collect stars, capture pieces and checkmate the king.
                </p>
            </div>

            <div class="grid w-full gap-5 sm:grid-cols-2">
                <section class="chunky flex flex-col items-center gap-4 p-6">
                    <span class="grid h-20 w-20 place-items-center rounded-full border-[3px] border-ink bg-sun p-2">
                        <PieceIcon piece="N" />
                    </span>
                    <h2 class="font-display text-3xl font-extrabold">I'm a player</h2>
                    <Link v-if="$page.props.auth.student" :href="route('quest')" class="btn btn-go w-full text-xl">
                        Keep playing, {{ $page.props.auth.student.name }}
                    </Link>
                    <template v-else>
                        <div class="grid w-full grid-cols-2 gap-3">
                            <Link :href="route('player.register')" class="btn btn-go text-xl">Sign up</Link>
                            <Link :href="route('player.login')" class="btn text-xl">Sign in</Link>
                        </div>
                        <Link :href="route('join')" class="btn w-full text-xl">Join my class</Link>
                        <Link :href="route('quest')" class="font-bold text-ink-soft underline underline-offset-4">Play as a guest</Link>
                    </template>
                </section>

                <section class="chunky flex flex-col items-center gap-4 p-6">
                    <span class="grid h-20 w-20 place-items-center rounded-full border-[3px] border-ink bg-berry p-2">
                        <PieceIcon piece="K" />
                    </span>
                    <h2 class="font-display text-3xl font-extrabold">I'm a coach</h2>
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn w-full text-xl">My classes</Link>
                    <template v-else>
                        <Link :href="route('login')" class="btn w-full text-xl">Log in</Link>
                        <Link v-if="canRegister" :href="route('register')" class="font-bold text-ink-soft underline underline-offset-4">
                            Create a coach account
                        </Link>
                    </template>
                </section>
            </div>

            <Link :href="route('club')" class="btn btn-go text-xl">♟️ Chess Club: puzzles, lessons & register</Link>

            <footer class="text-xs font-bold text-ink-soft">
                Chess piece artwork by Cburnett,
                <a class="underline" href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank" rel="noopener">CC BY-SA 3.0</a>.
            </footer>
        </main>
    </div>
</template>
