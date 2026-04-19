<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface User {
    id: number;
    username: string;
    name: string;
    email: string;
    roles: string[];
    email_verified: boolean;
}

defineProps<{
    users: { data: User[]; links: Array<{ url: string | null; label: string; active: boolean }> };
}>();
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">Users</h1>
            <Link :href="route('admin.users.create')" class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800">
                Invite user
            </Link>
        </div>

        <table class="w-full bg-white rounded shadow-sm">
            <thead class="text-left text-sm text-slate-600">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Roles</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="u in users.data" :key="u.id" class="border-t">
                    <td class="px-4 py-3 font-medium text-slate-900">
                        {{ u.name }}
                        <span v-if="!u.email_verified" class="ml-1 text-xs text-amber-600">(unverified)</span>
                    </td>
                    <td class="px-4 py-3 text-slate-600 font-mono text-sm">{{ u.username }}</td>
                    <td class="px-4 py-3 text-slate-600 text-sm">{{ u.email }}</td>
                    <td class="px-4 py-3">
                        <span
                            v-for="r in u.roles"
                            :key="r"
                            class="inline-block mr-1 px-2 py-0.5 rounded bg-slate-100 text-xs text-slate-700"
                        >
                            {{ r }}
                        </span>
                        <span v-if="u.roles.length === 0" class="text-xs text-slate-400">no roles</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <Link :href="route('admin.users.show', u.id)" class="text-sm text-slate-700 hover:text-slate-900">
                            Manage
                        </Link>
                    </td>
                </tr>
                <tr v-if="users.data.length === 0">
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">No users.</td>
                </tr>
            </tbody>
        </table>
    </AppLayout>
</template>
