<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Sign in — GRM Sierra Leone" />

    <div class="min-h-screen flex flex-col" style="background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);">
        <div class="p-6">
            <Link href="/" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                Back to home
            </Link>
        </div>

        <div class="flex-1 flex items-center justify-center px-6 pb-16">
            <div class="w-full max-w-md">
                <div class="text-center mb-8">
                    <div class="flex justify-center items-center gap-3 mb-4">
                        <img src="/images/sl-coat-of-arms.svg" class="h-14" alt="Sierra Leone" onerror="this.style.display='none'" />
                        <img src="/images/acc-logo.png" class="h-12" alt="ACC" onerror="this.style.display='none'" />
                    </div>
                    <h1 class="text-white text-xl font-bold leading-tight">Grievance Redress Mechanism</h1>
                    <p class="text-sm mt-1" style="color: var(--color-gold-light);">Staff Portal</p>
                </div>

                <form class="bg-white rounded-2xl p-8 space-y-5" style="box-shadow: 0 20px 60px rgba(0,0,0,0.3);" @submit.prevent="submit">
                    <div>
                        <h2 class="text-lg font-bold" style="color: var(--color-navy);">Sign in to your account</h2>
                        <p class="text-sm mt-0.5" style="color: var(--color-slate);">Enter your credentials to access the system.</p>
                    </div>

                    <div v-if="status" class="rounded-lg px-4 py-2 text-sm font-medium" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                        {{ status }}
                    </div>

                    <div v-if="form.errors.username || form.errors.password" class="rounded-lg px-4 py-2 text-sm font-medium" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;">
                        {{ form.errors.username || form.errors.password }}
                    </div>

                    <label class="block">
                        <span class="block text-sm font-medium mb-1" style="color: var(--color-navy);">Username</span>
                        <input v-model="form.username" type="text" autocomplete="username" required placeholder="Enter your username" class="block w-full rounded-lg px-4 py-2.5 text-sm" style="border: 1px solid var(--color-border);" />
                    </label>

                    <label class="block">
                        <span class="block text-sm font-medium mb-1" style="color: var(--color-navy);">Password</span>
                        <div class="relative">
                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Enter your password" class="block w-full rounded-lg px-4 py-2.5 pr-10 text-sm" style="border: 1px solid var(--color-border);" />
                            <button type="button" class="absolute inset-y-0 right-0 flex items-center pr-3 transition" style="color: var(--color-slate);" @click="showPassword = !showPassword">
                                <svg v-if="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                    </label>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm cursor-pointer" style="color: var(--color-slate);">
                            <input v-model="form.remember" type="checkbox" class="rounded" style="border-color: var(--color-border); color: var(--color-gold);" />
                            Remember me
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing" class="w-full px-4 py-2.5 rounded-lg text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50 flex items-center justify-center gap-2" style="background: var(--color-navy);">
                        <span v-if="form.processing" class="inline-block h-4 w-4 rounded-full border-2 border-white border-t-transparent animate-spin" />
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>

                <p class="text-center text-xs mt-6" style="color: var(--color-slate);">
                    &copy; {{ new Date().getFullYear() }} Anti-Corruption Commission, Sierra Leone
                </p>
            </div>
        </div>
    </div>
</template>
