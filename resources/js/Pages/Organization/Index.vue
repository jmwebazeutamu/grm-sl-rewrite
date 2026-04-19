<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    organizations: { type: Object, required: true },
    user_counts: { type: Object, default: () => ({}) },
    admins: { type: Object, default: () => ({}) },
});

function userCount(orgId) {
    return props.user_counts[orgId] ?? 0;
}

function adminNames(orgId) {
    const list = props.admins[orgId] ?? [];
    return list.length > 0 ? list.map((a) => a.name).join(', ') : '—';
}
</script>

<template>
    <Head title="Organizations" />
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">Organizations</h1>
            <Link
                :href="route('admin.organizations.create')"
                class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
            >
                Add organization
            </Link>
        </div>

        <table class="w-full bg-white rounded shadow-sm text-sm">
            <thead class="text-left text-slate-600">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Acronym</th>
                    <th class="px-4 py-3">Admin</th>
                    <th class="px-4 py-3 text-right">Users</th>
                    <th class="px-4 py-3 text-right">Offices</th>
                    <th class="px-4 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="org in organizations.data" :key="org.id" class="border-t">
                    <td class="px-4 py-3 font-medium text-slate-900">
                        <Link :href="route('admin.organizations.show', org.id)" class="hover:underline">
                            {{ org.name }}
                        </Link>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ org.acronym ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ adminNames(org.id) }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ userCount(org.id) }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ org.office_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">
                        <Link
                            :href="route('admin.organizations.show', org.id)"
                            class="text-slate-700 hover:text-slate-900 underline"
                        >
                            View
                        </Link>
                    </td>
                </tr>
                <tr v-if="organizations.data.length === 0">
                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">No organizations yet.</td>
                </tr>
            </tbody>
        </table>
    </AppLayout>
</template>
