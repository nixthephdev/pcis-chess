<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { loadGuestStars } from '@/game/progress';
import StarIcon from '@/Components/Game/StarIcon.vue';
import StudentAvatar from '@/Components/Game/StudentAvatar.vue';

const props = defineProps<{ avatars: string[] }>();

const guestStars = loadGuestStars();
const carried = Object.values(guestStars).reduce((a, b) => a + b, 0);

const form = useForm({
    username: '',
    password: '',
    password_confirmation: '',
    avatar: props.avatars[Math.floor(Math.random() * props.avatars.length)],
    stars: guestStars,
});

function submit() {
    form.post(route('player.register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Sign up" />

    <div class="quest-bg grid place-items-center px-4 py-10">
        <main class="chunky flex w-full max-w-md flex-col gap-5 p-6 sm:p-8">
            <div class="text-center">
                <img src="/icon.svg" alt="" class="mx-auto h-16 w-16 -rotate-6" />
                <h1 class="font-display text-4xl font-extrabold">Create your account</h1>
                <p class="font-bold text-ink-soft">Save your stars and climb the leaderboard.</p>
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <fieldset>
                    <legend class="label-caps mb-2">Pick your piece</legend>
                    <div class="flex flex-wrap justify-center gap-2">
                        <label v-for="a in avatars" :key="a" class="cursor-pointer rounded-full p-1" :class="form.avatar === a ? 'ring-[3px] ring-tang' : ''">
                            <input v-model="form.avatar" type="radio" name="avatar" :value="a" class="sr-only" />
                            <StudentAvatar :avatar="a" :size="48" />
                            <span class="sr-only">{{ a }}</span>
                        </label>
                    </div>
                </fieldset>

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
                        maxlength="20"
                        required
                        autofocus
                    />
                    <p v-if="form.errors.username" class="mt-1 font-bold text-berry" role="alert">{{ form.errors.username }}</p>
                    <p v-else class="mt-1 text-sm font-bold text-ink-soft">
                        Everyone can see it, so don't use your real full name.
                    </p>
                </div>

                <div>
                    <label for="password" class="label-caps">Password</label>
                    <input id="password" v-model="form.password" type="password" class="field" autocomplete="new-password" required />
                    <p v-if="form.errors.password" class="mt-1 font-bold text-berry" role="alert">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="label-caps">Password again</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="field" autocomplete="new-password" required />
                </div>

                <p v-if="carried" class="flex items-center gap-2 rounded-xl bg-sun/40 px-3 py-2 font-bold">
                    <StarIcon class="h-5 w-5 shrink-0" /> Your {{ carried }} guest stars will come with you.
                </p>

                <button type="submit" class="btn btn-go text-xl" :disabled="form.processing">Sign up</button>
            </form>

            <p class="text-center font-bold text-ink-soft">
                Already have an account?
                <Link :href="route('player.login')" class="text-ink underline underline-offset-4">Sign in</Link>
            </p>
        </main>
    </div>
</template>
