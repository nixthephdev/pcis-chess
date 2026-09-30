<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StudentAvatar from '@/Components/Game/StudentAvatar.vue';

const props = defineProps<{
    player: { username: string; avatar: string };
    avatars: string[];
}>();

const look = useForm({ avatar: props.player.avatar });
const pass = useForm({ current_password: '', password: '', password_confirmation: '' });
const del = useForm({ password: '' });
const confirmingDelete = ref(false);

function saveAvatar() {
    look.patch(route('player.update'), { preserveScroll: true, onSuccess: () => look.defaults() });
}

function savePassword() {
    pass.put(route('player.password'), {
        preserveScroll: true,
        onSuccess: () => pass.reset(),
        onError: () => pass.reset('password', 'password_confirmation'),
    });
}

function deleteAccount() {
    del.delete(route('player.destroy'), { preserveScroll: true, onFinish: () => del.reset() });
}
</script>

<template>
    <Head title="Settings" />

    <div class="quest-bg px-4 py-8">
        <main class="mx-auto flex max-w-xl flex-col gap-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-display text-4xl font-extrabold">Settings</h1>
                <div class="flex gap-2">
                    <Link :href="route('player.show', player.username)" class="btn btn-sm">My profile</Link>
                    <Link :href="route('quest')" class="btn btn-sm">Play</Link>
                </div>
            </header>

            <p v-if="$page.props.flash.status" class="chunky bg-[#e6f7ee] px-4 py-3 font-bold" role="status">{{ $page.props.flash.status }}</p>

            <form class="chunky flex flex-col gap-4 p-6" @submit.prevent="saveAvatar">
                <h2 class="font-display text-2xl font-extrabold">Avatar</h2>
                <fieldset>
                    <legend class="sr-only">Pick your piece</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="a in avatars" :key="a" class="cursor-pointer rounded-full p-1" :class="look.avatar === a ? 'ring-[3px] ring-tang' : ''">
                            <input v-model="look.avatar" type="radio" name="avatar" :value="a" class="sr-only" />
                            <StudentAvatar :avatar="a" :size="52" />
                            <span class="sr-only">{{ a }}</span>
                        </label>
                    </div>
                </fieldset>
                <button type="submit" class="btn btn-go self-start" :disabled="look.processing || !look.isDirty">Save avatar</button>
            </form>

            <form class="chunky flex flex-col gap-4 p-6" @submit.prevent="savePassword">
                <h2 class="font-display text-2xl font-extrabold">Change password</h2>
                <div>
                    <label for="current_password" class="label-caps">Current password</label>
                    <input id="current_password" v-model="pass.current_password" type="password" class="field" autocomplete="current-password" required />
                    <p v-if="pass.errors.current_password" class="mt-1 font-bold text-berry" role="alert">{{ pass.errors.current_password }}</p>
                </div>
                <div>
                    <label for="new_password" class="label-caps">New password</label>
                    <input id="new_password" v-model="pass.password" type="password" class="field" autocomplete="new-password" required />
                    <p v-if="pass.errors.password" class="mt-1 font-bold text-berry" role="alert">{{ pass.errors.password }}</p>
                </div>
                <div>
                    <label for="new_password_confirmation" class="label-caps">New password again</label>
                    <input id="new_password_confirmation" v-model="pass.password_confirmation" type="password" class="field" autocomplete="new-password" required />
                </div>
                <button type="submit" class="btn btn-go self-start" :disabled="pass.processing">Change password</button>
            </form>

            <section class="chunky flex flex-col gap-4 p-6">
                <h2 class="font-display text-2xl font-extrabold">Close account</h2>
                <p class="font-bold text-ink-soft">This deletes your account and all your stars. It can't be undone.</p>
                <button v-if="!confirmingDelete" type="button" class="btn self-start" @click="confirmingDelete = true">Close my account</button>
                <form v-else class="flex flex-col gap-3" @submit.prevent="deleteAccount">
                    <div>
                        <label for="delete_password" class="label-caps">Type your password to confirm</label>
                        <input id="delete_password" v-model="del.password" type="password" class="field" autocomplete="current-password" required />
                        <p v-if="del.errors.password" class="mt-1 font-bold text-berry" role="alert">{{ del.errors.password }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn bg-berry text-white" :disabled="del.processing">Delete forever</button>
                        <button type="button" class="btn" @click="(confirmingDelete = false), del.reset(), del.clearErrors()">Cancel</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</template>
