<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    preferences: { email_enabled: boolean; sms_enabled: boolean; in_app_enabled: boolean };
    has_phone: boolean;
}>();

const form = useForm({
    email_enabled: props.preferences.email_enabled,
    sms_enabled: props.preferences.sms_enabled,
    in_app_enabled: props.preferences.in_app_enabled,
});

function submit(): void {
    form.put(route('settings.notifications.update'));
}
</script>

<template>
    <Head title="Notification preferences" />
    <AppLayout>
        <div class="max-w-xl">
            <h1 class="text-2xl font-semibold text-slate-900 mb-2">Notifications</h1>
            <p class="text-sm text-slate-600 mb-6">
                Choose how we reach you about cases you're assigned to and other activity.
                Toggles are all-or-nothing per channel; we don't spam.
            </p>

            <form class="bg-white border border-slate-200 rounded p-5 space-y-5" @submit.prevent="submit">
                <label class="flex items-start gap-3">
                    <input v-model="form.in_app_enabled" type="checkbox" class="mt-1 rounded border-slate-300" />
                    <span>
                        <span class="block text-sm font-medium text-slate-900">In-app</span>
                        <span class="block text-xs text-slate-500">Notifications appear in the bell icon when you're signed in.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input v-model="form.email_enabled" type="checkbox" class="mt-1 rounded border-slate-300" />
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Email</span>
                        <span class="block text-xs text-slate-500">Sent to your account email.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input
                        v-model="form.sms_enabled"
                        type="checkbox"
                        :disabled="!has_phone"
                        class="mt-1 rounded border-slate-300 disabled:opacity-50"
                    />
                    <span>
                        <span class="block text-sm font-medium text-slate-900">SMS</span>
                        <span class="block text-xs text-slate-500">
                            <template v-if="has_phone">Sent to your phone on record.</template>
                            <template v-else>Add a phone number on your profile to enable this.</template>
                        </span>
                    </span>
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50"
                >
                    Save preferences
                </button>
            </form>
        </div>
    </AppLayout>
</template>
