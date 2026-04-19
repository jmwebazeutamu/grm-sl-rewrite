<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    g_number: string;
    state_label: string;
    received_at: string;
    resolved_at: string | null;
    closed_at: string | null;
}>();
</script>

<template>
    <Head :title="`Status of ${g_number}`" />

    <div class="min-h-screen bg-slate-50 py-10">
        <div class="max-w-xl mx-auto px-4 bg-white rounded shadow-sm p-8">
            <h1 class="text-xl font-semibold text-slate-900">Grievance {{ g_number }}</h1>

            <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">Current state</dt>
                <dd class="text-slate-900 font-medium">{{ state_label }}</dd>

                <dt class="text-slate-500">Received</dt>
                <dd class="text-slate-900">{{ new Date(received_at).toLocaleString() }}</dd>

                <dt v-if="resolved_at" class="text-slate-500">Resolved</dt>
                <dd v-if="resolved_at" class="text-slate-900">{{ new Date(resolved_at).toLocaleString() }}</dd>

                <dt v-if="closed_at" class="text-slate-500">Closed</dt>
                <dd v-if="closed_at" class="text-slate-900">{{ new Date(closed_at).toLocaleString() }}</dd>
            </dl>
        </div>
    </div>
</template>
