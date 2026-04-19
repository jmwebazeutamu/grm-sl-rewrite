<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Role {
    id: number;
    name: string;
    permission_count: number;
    user_count: number;
    is_protected: boolean;
}

defineProps<{ roles: Role[] }>();

function destroy(role: Role): void {
    if (!confirm(`Delete the role "${role.name}"?`)) return;
    router.delete(route('admin.roles.destroy', role.id));
}
</script>

<template>
    <Head title="Roles" />
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">Roles</h1>
            <Link :href="route('admin.roles.create')" class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800">
                New role
            </Link>
        </div>

        <table class="w-full bg-white rounded shadow-sm">
            <thead class="text-left text-sm text-slate-600">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3 text-right">Permissions</th>
                    <th class="px-4 py-3 text-right">Users</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in roles" :key="r.id" class="border-t">
                    <td class="px-4 py-3 font-medium text-slate-900">
                        {{ r.name }}
                        <span v-if="r.is_protected" class="ml-2 text-xs text-slate-500">(protected)</span>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ r.permission_count }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ r.user_count }}</td>
                    <td class="px-4 py-3 text-right space-x-3">
                        <Link
                            v-if="!r.is_protected || r.name !== 'super-admin'"
                            :href="route('admin.roles.edit', r.id)"
                            class="text-sm text-slate-700 hover:text-slate-900"
                        >
                            Edit
                        </Link>
                        <button
                            v-if="!r.is_protected"
                            type="button"
                            class="text-sm text-rose-700 hover:text-rose-900"
                            @click="destroy(r)"
                        >
                            Delete
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </AppLayout>
</template>
