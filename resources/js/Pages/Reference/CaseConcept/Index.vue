<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface CaseConcept {
    id: number;
    name: string;
    description: string | null;
    grievance_type?: { id: number; name: string };
}

defineProps<{
    items: { data: CaseConcept[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    resource: string;
    grievanceTypes: Array<{ id: number; name: string }>;
}>();
</script>

<template>
    <Head title="Case concepts" />
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">Case concepts</h1>
            <Link
                :href="route(`admin.reference.${resource}.create`)"
                class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
            >
                Add case concept
            </Link>
        </div>

        <table class="w-full bg-white rounded shadow-sm">
            <thead class="text-left text-sm text-slate-600">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Grievance type</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in items.data" :key="item.id" class="border-t">
                    <td class="px-4 py-3">{{ item.name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ item.grievance_type?.name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <Link
                            :href="route(`admin.reference.${resource}.show`, item.id)"
                            class="text-sm text-slate-700 hover:text-slate-900"
                        >
                            View
                        </Link>
                    </td>
                </tr>
                <tr v-if="items.data.length === 0">
                    <td colspan="3" class="px-4 py-6 text-center text-slate-500">None yet.</td>
                </tr>
            </tbody>
        </table>
    </AppLayout>
</template>
