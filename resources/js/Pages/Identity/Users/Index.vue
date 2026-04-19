<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    users: { type: Object, required: true },
    organization_id: { type: [Number, null], default: null },
    is_super_admin: { type: Boolean, default: false },
});

function primaryRole(roles) {
    if (!roles || roles.length === 0) return null;
    const rank = ['super-admin', 'org-admin', 'acc-reviewer', 'grm-data-operator', 'grm-officer', 'organization-officer', 'complainant'];
    return [...roles].sort((a, b) => rank.indexOf(a) - rank.indexOf(b))[0];
}

function roleStyle(role) {
    const map = {
        'super-admin': 'bg-purple-100 text-purple-800',
        'org-admin': 'bg-indigo-100 text-indigo-800',
        'acc-reviewer': 'bg-blue-100 text-blue-800',
        'grm-data-operator': 'bg-cyan-100 text-cyan-800',
        'grm-officer': 'bg-emerald-100 text-emerald-800',
        'organization-officer': 'bg-slate-100 text-slate-700',
        'complainant': 'bg-gray-100 text-gray-600',
    };
    return map[role] ?? 'bg-slate-100 text-slate-700';
}

function status(u) {
    if (u.is_active === false) return 'Deactivated';
    return u.email_verified ? 'Active' : 'Invited';
}

function statusStyle(u) {
    if (u.is_active === false) return 'bg-red-100 text-red-700';
    return u.email_verified ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
}

const openMenu = ref(null);

function toggleMenu(id) {
    openMenu.value = openMenu.value === id ? null : id;
}

function deactivate(u) {
    if (!confirm(`Deactivate ${u.name}? They will be logged out immediately.`)) return;
    router.post(route('admin.org.users.deactivate', u.id));
    openMenu.value = null;
}

function reactivate(u) {
    router.post(route('admin.org.users.reactivate', u.id));
    openMenu.value = null;
}
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--color-navy);">Users</h1>
                <p class="text-sm mt-0.5" style="color: var(--color-slate);">
                    {{ organization_id ? 'Staff in your organisation.' : 'All users across organisations.' }}
                </p>
            </div>
            <Link :href="route('admin.org.users.create')" class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center gap-2 hover:opacity-90 transition" style="background: var(--color-navy);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add user
            </Link>
        </div>

        <div class="bg-white rounded-xl overflow-hidden" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider" style="background: var(--color-surface); color: var(--color-slate); border-bottom: 2px solid var(--color-border);">
                        <th class="px-4 py-3">User</th>
                        <th v-if="organization_id === null" class="px-4 py-3">Organisation</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Position</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(u, i) in users.data"
                        :key="u.id"
                        :class="['row-hover border-b transition-colors', u.is_active === false ? 'opacity-60' : '', i % 2 === 0 ? '' : 'bg-slate-50/30']"
                        style="border-color: var(--color-border);"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0" style="background: var(--color-navy);">
                                    {{ u.name?.split(' ').filter(Boolean).slice(0, 2).map((n) => n[0].toUpperCase()).join('') || '?' }}
                                </div>
                                <div class="min-w-0">
                                    <Link :href="route('admin.org.users.show', u.id)" class="font-semibold hover:underline truncate block" :style="{ color: u.is_active === false ? 'var(--color-slate)' : 'var(--color-navy)' }">
                                        {{ u.name }}
                                    </Link>
                                    <span v-if="u.email" class="block text-xs truncate" style="color: var(--color-slate);">{{ u.email }}</span>
                                </div>
                            </div>
                        </td>
                        <td v-if="organization_id === null" class="px-4 py-3">
                            <span v-if="u.organization" class="text-slate-700">{{ u.organization.acronym || u.organization.name }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ u.username }}</td>
                        <td class="px-4 py-3">
                            <span v-if="primaryRole(u.roles)" :class="['state-badge inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold leading-none', roleStyle(primaryRole(u.roles))]">
                                {{ primaryRole(u.roles) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ u.position ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span :class="['state-badge inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold leading-none', statusStyle(u)]">
                                {{ status(u) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 relative">
                            <button type="button" class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition" @click="toggleMenu(u.id)">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="2" /><circle cx="10" cy="10" r="2" /><circle cx="10" cy="16" r="2" /></svg>
                            </button>
                            <Transition
                                enter-active-class="transition ease-out duration-100"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div v-if="openMenu === u.id" class="absolute right-4 top-12 w-48 bg-white rounded-lg shadow-lg py-1 z-50" style="border: 1px solid var(--color-border);">
                                    <Link :href="route('admin.org.users.show', u.id)" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="openMenu = null">View profile</Link>
                                    <Link :href="route('admin.org.users.edit', u.id)" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="openMenu = null">Edit details</Link>
                                    <Link :href="route('admin.org.users.editRole', u.id)" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="openMenu = null">Change role</Link>
                                    <Link v-if="is_super_admin" :href="route('admin.org.users.edit', u.id)" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="openMenu = null">Change organisation</Link>
                                    <Link :href="route('admin.org.users.password', u.id)" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="openMenu = null">Reset password</Link>
                                    <div class="border-t my-1" style="border-color: var(--color-border);" />
                                    <button v-if="u.is_active !== false" type="button" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50" @click="deactivate(u)">Deactivate</button>
                                    <button v-else type="button" class="w-full text-left px-4 py-2 text-sm text-emerald-600 hover:bg-emerald-50" @click="reactivate(u)">Reactivate</button>
                                </div>
                            </Transition>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="users.data.length === 0" class="py-16 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                <p class="text-sm font-medium text-slate-500">No users yet</p>
                <p class="text-xs text-slate-400 mt-1">Click "Add user" to create the first one.</p>
            </div>
        </div>
    </AppLayout>
</template>
