<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    g_number: string;
    state_label: string;
    received_at: string;
}>();
</script>

<template>
    <Head title="Grievance submitted" />

    <div class="min-h-screen bg-slate-50 py-10">
        <div class="max-w-xl mx-auto px-4 bg-white rounded shadow-sm p-8 text-center">
            <h1 class="text-2xl font-semibold text-slate-900">Your grievance has been submitted.</h1>

            <p class="mt-4 text-slate-700">
                Please keep your reference number safe — you'll need it to check on progress.
            </p>

            <div class="mt-4 inline-block px-4 py-2 rounded bg-slate-900 text-white font-mono text-lg">
                {{ g_number }}
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-3 text-sm text-left">
                <dt class="text-slate-500">Current state</dt>
                <dd class="text-slate-900">{{ state_label }}</dd>

                <dt class="text-slate-500">Received at</dt>
                <dd class="text-slate-900">{{ new Date(received_at).toLocaleString() }}</dd>
            </dl>

            <Link
                :href="route('grievances.public.status', g_number)"
                class="mt-6 inline-block text-sm text-slate-700 underline"
            >
                Check the status of this grievance
            </Link>

            <div class="mt-8 pt-6 border-t border-slate-200 flex justify-center gap-3">
                <Link
                    :href="route('grievances.public.create')"
                    class="px-4 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
                >
                    Submit another grievance
                </Link>
                <Link
                    :href="route('home')"
                    class="px-4 py-2 rounded border border-slate-300 text-sm text-slate-700 hover:bg-slate-50"
                >
                    Return to home
                </Link>
            </div>
        </div>
    </div>
</template>
