<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({
    email: props.email,
    token: props.token,
    password: '',
    password_confirmation: '',
});

const show = ref(false);

function submit() {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Reset password — GRM Sierra Leone" />

    <div class="min-h-screen flex flex-col" style="background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);">
        <div class="flex-1 flex items-center justify-center px-6 py-16">
            <div class="w-full max-w-md">
                <div class="text-center mb-8">
                    <h1 class="text-white text-xl font-bold leading-tight">Reset your password</h1>
                    <p class="text-sm mt-1" style="color: var(--color-gold-light);">Enter a new password for your account.</p>
                </div>

                <form class="bg-white rounded-2xl p-8 space-y-5" style="box-shadow: 0 20px 60px rgba(0,0,0,0.3);" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="username"
                            class="block w-full rounded-lg border-slate-300 px-3 py-2 text-sm"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">New password</label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="show ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                class="block w-full rounded-lg border-slate-300 px-3 py-2 text-sm pr-16"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-2 px-2 text-xs font-medium text-slate-500 hover:text-slate-700"
                                @click="show = !show"
                            >
                                {{ show ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
                        <input
                            v-model="form.password_confirmation"
                            :type="show ? 'text' : 'password'"
                            required
                            autocomplete="new-password"
                            class="block w-full rounded-lg border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                        style="background: var(--color-navy);"
                    >
                        {{ form.processing ? 'Resetting…' : 'Reset password' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
