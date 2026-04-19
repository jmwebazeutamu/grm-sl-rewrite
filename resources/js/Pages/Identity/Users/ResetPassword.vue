<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, required: true },
});

const u = props.user.data;
const hasEmail = !!u.email;

const emailForm = useForm({});
const passwordForm = useForm({ password: '', password_confirmation: '' });

function sendEmail() {
    emailForm.post(route('admin.org.users.password.email', u.id));
}

function setPassword() {
    passwordForm.post(route('admin.org.users.password.set', u.id), {
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head :title="`Reset password — ${u.name}`" />
    <AppLayout>
        <div class="max-w-2xl">
            <Link :href="route('admin.org.users.show', u.id)" class="text-sm text-slate-500 hover:text-slate-700 mb-4 inline-block">← Back to profile</Link>

            <h1 class="text-2xl font-semibold text-slate-900 mb-2">Reset password</h1>
            <p class="text-sm text-slate-500 mb-6">{{ u.name }} · <span class="font-mono">{{ u.username }}</span></p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Card A — Email reset -->
                <div class="bg-white border border-slate-200 rounded-lg p-5 space-y-3">
                    <h2 class="text-sm font-semibold text-slate-700">Send reset email</h2>
                    <p class="text-xs text-slate-500">
                        Send {{ u.name }} a password-reset link by email. They set their own password.
                    </p>

                    <div v-if="hasEmail" class="text-sm text-slate-700">
                        Email: <strong>{{ u.email }}</strong>
                    </div>
                    <div v-else class="text-sm text-amber-700 bg-amber-50 rounded p-2">
                        This user has no email address. Use the direct method.
                    </div>

                    <button
                        type="button"
                        :disabled="!hasEmail || emailForm.processing"
                        class="w-full px-3 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50"
                        @click="sendEmail"
                    >
                        Send reset email
                    </button>
                </div>

                <!-- Card B — Direct set -->
                <div class="bg-white border border-slate-200 rounded-lg p-5 space-y-3">
                    <h2 class="text-sm font-semibold text-slate-700">Set password directly</h2>
                    <p class="text-xs text-slate-500">
                        Set a new password on behalf of this user. They can change it after logging in.
                    </p>

                    <form class="space-y-3" @submit.prevent="setPassword">
                        <label class="block">
                            <span class="block text-xs font-medium text-slate-700">New password</span>
                            <input v-model="passwordForm.password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 block w-full rounded border-slate-300 text-sm" />
                            <span v-if="passwordForm.errors.password" class="text-xs text-rose-600">{{ passwordForm.errors.password }}</span>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-slate-700">Confirm password</span>
                            <input v-model="passwordForm.password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-1 block w-full rounded border-slate-300 text-sm" />
                        </label>
                        <button type="submit" :disabled="passwordForm.processing" class="w-full px-3 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50">
                            Set password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
