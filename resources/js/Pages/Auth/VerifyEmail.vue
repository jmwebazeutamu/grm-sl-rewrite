<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({});
const status = computed(() => usePage().props.status);
const verificationSent = computed(() => status.value === 'verification-link-sent');

function resend() {
    form.post(route('verification.send'));
}

function logout() {
    useForm({}).post(route('logout'));
}
</script>

<template>
    <Head title="Verify email — GRM Sierra Leone" />

    <div class="min-h-screen flex flex-col" style="background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);">
        <div class="flex-1 flex items-center justify-center px-6 py-16">
            <div class="w-full max-w-md">
                <div class="text-center mb-8">
                    <h1 class="text-white text-xl font-bold leading-tight">Verify your email</h1>
                </div>

                <div class="bg-white rounded-2xl p-8 space-y-5" style="box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                    <p class="text-sm text-slate-700">
                        Thanks for signing up. Before getting started, please verify your email address by clicking the link we just sent you.
                        If you didn't receive it, we'll happily send another.
                    </p>

                    <div v-if="verificationSent" class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">
                        A new verification link has been sent to your email.
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            style="background: var(--color-navy);"
                            @click="resend"
                        >
                            {{ form.processing ? 'Sending…' : 'Resend verification email' }}
                        </button>
                        <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="logout">
                            Sign out
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
