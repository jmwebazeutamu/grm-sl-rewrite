<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organization: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    usage: {
        type: Object,
        default: () => ({
            offices: 0,
            programmes: 0,
            employees: 0,
            users: 0,
            grievancesClassified: 0,
            grievancesImplementing: 0,
            children: 0,
        }),
    },
});

const cascadeLines = computed(() => {
    const lines = [];
    if (props.usage.offices > 0) lines.push(`${props.usage.offices} office(s) will be DELETED`);
    if (props.usage.programmes > 0) lines.push(`${props.usage.programmes} programme(s) will be DELETED`);
    if (props.usage.employees > 0) lines.push(`${props.usage.employees} employee record(s) will be DELETED`);
    if (props.usage.children > 0) lines.push(`${props.usage.children} child organisation(s) will be orphaned (parent cleared)`);
    if (props.usage.users > 0) lines.push(`${props.usage.users} user(s) will keep their account but lose this organisation link`);
    if (props.usage.grievancesClassified > 0) lines.push(`${props.usage.grievancesClassified} grievance(s) classified to this org will lose that classification`);
    if (props.usage.grievancesImplementing > 0) lines.push(`${props.usage.grievancesImplementing} grievance(s) implementing-org will be cleared`);
    return lines;
});

function destroy() {
    const name = props.organization.data.name;
    const warning = cascadeLines.value.length
        ? `Delete "${name}"?\n\nThis will cascade:\n• ${cascadeLines.value.join('\n• ')}\n\nContinue?`
        : `Delete "${name}"? This cannot be undone.`;
    if (!confirm(warning)) return;
    router.delete(route('admin.organizations.destroy', props.organization.data.id));
}
</script>

<template>
    <Head :title="organization.data.name" />
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">{{ organization.data.name }}</h1>
            <div class="flex gap-2">
                <Link
                    :href="route('admin.organizations.edit', organization.data.id)"
                    class="px-3 py-2 rounded border border-slate-300 text-sm hover:bg-slate-50"
                >
                    Edit
                </Link>
                <button
                    type="button"
                    @click="destroy"
                    class="px-3 py-2 rounded border border-red-300 bg-red-50 text-sm text-red-700 hover:bg-red-100"
                >
                    Delete
                </button>
            </div>
        </div>

        <dl class="bg-white rounded shadow-sm p-6 grid grid-cols-2 gap-4 text-sm mb-6">
            <dt class="text-slate-500">Acronym</dt>
            <dd class="text-slate-900">{{ organization.data.acronym ?? '—' }}</dd>

            <dt class="text-slate-500">Parent</dt>
            <dd class="text-slate-900">{{ organization.data.parent?.name ?? '—' }}</dd>

            <dt class="text-slate-500">Description</dt>
            <dd class="text-slate-900" style="white-space: pre-wrap">{{ organization.data.description ?? '—' }}</dd>

            <dt class="text-slate-500">Grievance types</dt>
            <dd class="text-slate-900">
                <span
                    v-for="gt in organization.data.grievance_types ?? []"
                    :key="gt.id"
                    class="inline-block px-2 py-0.5 mr-1 mb-1 rounded bg-slate-100 text-xs"
                >
                    {{ gt.name }}
                </span>
                <span v-if="!organization.data.grievance_types?.length">—</span>
            </dd>
        </dl>

        <!-- Users section -->
        <section class="bg-white rounded shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-900">
                    Users
                    <span class="ml-2 text-sm font-normal text-slate-500">({{ users.length }})</span>
                </h2>
            </div>

            <table v-if="users.length" class="w-full text-sm">
                <thead class="text-left text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Username</th>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">Role</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id" class="border-b border-slate-100 last:border-0">
                        <td class="py-2 pr-4">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-900">{{ u.name }}</span>
                                <span v-if="u.is_admin" class="inline-block px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-semibold uppercase">Admin</span>
                            </div>
                        </td>
                        <td class="py-2 pr-4 font-mono text-slate-600">{{ u.username }}</td>
                        <td class="py-2 pr-4 text-slate-600">{{ u.email ?? '—' }}</td>
                        <td class="py-2 pr-4">
                            <span class="inline-block px-2 py-0.5 rounded bg-slate-100 text-xs text-slate-700">{{ u.role }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-else class="text-sm text-slate-500 italic">No users assigned to this organisation yet.</p>

            <p class="mt-4 text-xs text-slate-500">
                To add users to this organisation, go to
                <Link :href="route('admin.org.users.index')" class="text-indigo-600 hover:underline">User Management</Link>
                and create or invite a user with this organisation.
            </p>
        </section>

        <!-- Usage / cascade summary (visible for super-admins planning deletion) -->
        <section v-if="cascadeLines.length" class="bg-amber-50 border border-amber-200 rounded p-4 text-sm">
            <h3 class="font-semibold text-amber-900 mb-2">Deletion impact</h3>
            <ul class="list-disc list-inside space-y-1 text-amber-900">
                <li v-for="line in cascadeLines" :key="line">{{ line }}</li>
            </ul>
        </section>
    </AppLayout>
</template>
