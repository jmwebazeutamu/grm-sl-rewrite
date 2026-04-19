<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, Head } from '@inertiajs/vue3';

interface Locality {
    id: number;
    name: string;
    section?: { id: number; name: string };
}

interface PaginatedLocalities {
    data: Locality[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

defineProps<{
    localities: PaginatedLocalities;
}>();
</script>

<template>
    <Head title="Localities" />

    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">Localities</h1>
            <Link
                :href="route('admin.localities.create')"
                class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
            >
                Add locality
            </Link>
        </div>

        <table class="w-full bg-white rounded shadow-sm">
            <thead class="text-left text-sm text-slate-600">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Section</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="locality in localities.data" :key="locality.id" class="border-t">
                    <td class="px-4 py-3">{{ locality.name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ locality.section?.name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <Link
                            :href="route('admin.localities.show', locality.id)"
                            class="text-sm text-slate-700 hover:text-slate-900"
                        >
                            View
                        </Link>
                    </td>
                </tr>
                <tr v-if="localities.data.length === 0">
                    <td colspan="3" class="px-4 py-6 text-center text-slate-500">
                        No localities yet.
                    </td>
                </tr>
            </tbody>
        </table>

        <nav v-if="localities.links.length > 3" class="mt-4 flex gap-2">
            <Link
                v-for="(link, i) in localities.links"
                :key="i"
                :href="link.url ?? ''"
                :class="[
                    'px-3 py-1.5 text-sm rounded border',
                    link.active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200',
                    !link.url && 'opacity-40 pointer-events-none',
                ]"
                v-html="link.label"
            />
        </nav>
    </AppLayout>
</template>
