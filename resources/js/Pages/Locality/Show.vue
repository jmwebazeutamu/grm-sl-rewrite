<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    locality: {
        data: {
            id: number;
            name: string;
            section?: { id: number; name: string };
        };
    };
}>();
</script>

<template>
    <Head :title="locality.data.name" />

    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">{{ locality.data.name }}</h1>
            <Link
                :href="route('admin.localities.edit', locality.data.id)"
                class="px-3 py-2 rounded border border-slate-300 text-sm hover:bg-slate-50"
            >
                Edit
            </Link>
        </div>

        <dl class="bg-white rounded shadow-sm p-6 grid grid-cols-2 gap-4 text-sm">
            <dt class="text-slate-500">Section</dt>
            <dd class="text-slate-900">{{ locality.data.section?.name ?? '—' }}</dd>
        </dl>
    </AppLayout>
</template>
