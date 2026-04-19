<script setup lang="ts" generic="T extends { id: number; name: string }">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    resource: string;
    items: {
        data: T[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    extraColumns?: Array<{ key: keyof T; label: string }>;
}>();
</script>

<template>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold text-slate-900">{{ title }}</h1>
        <Link
            :href="route(`admin.reference.${resource}.create`)"
            class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
        >
            Add {{ title.toLowerCase().replace(/s$/, '') }}
        </Link>
    </div>

    <table class="w-full bg-white rounded shadow-sm">
        <thead class="text-left text-sm text-slate-600">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th v-for="col in extraColumns" :key="String(col.key)" class="px-4 py-3">{{ col.label }}</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in items.data" :key="item.id" class="border-t">
                <td class="px-4 py-3">{{ item.name }}</td>
                <td v-for="col in extraColumns" :key="String(col.key)" class="px-4 py-3 text-slate-600">
                    {{ item[col.key] ?? '—' }}
                </td>
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
                <td :colspan="(extraColumns?.length ?? 0) + 2" class="px-4 py-6 text-center text-slate-500">
                    None yet.
                </td>
            </tr>
        </tbody>
    </table>
</template>
