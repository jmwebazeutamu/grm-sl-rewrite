<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    email: '',
});

const status = computed(() => usePage().props.status);

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <Head title="Forgot password — GRM Sierra Leone" />

    <div class="min-h-screen flex flex-col" style="background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);">
        <div class="p-6">
            <Link :href="route('login')" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                Back to sign in
            </Link>
        </div>

        <div class="flex-1 flex items-center justify-center px-6 pb-16">
            <div class="w-full max-w-md">
                <div class="text-center mb-8">
                    <h1 class="text-white text-xl font-bold leading-tight">Forgot your password?</h1>
                    <p class="text-sm mt-1" style="color: var(--color-gold-light);">We'll email you a reset link.</p>
                </div>

                <form class="bg-white rounded-2xl p-8 space-y-5" style="box-shadow: 0 20px 60px rgba(0,0,0,0.3);" @submit.prevent="submit">
                    <div v-if="status" class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">
                        {{ status }}
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="you@example.com"
                            class="block w-full rounded-lg border-slate-300 px-3 py-2 text-sm"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                        style="background: var(--color-navy);"
                    >
                        {{ form.processing ? 'Sending…' : 'Email me a reset link' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
