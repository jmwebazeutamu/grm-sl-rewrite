<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, required: true },
    capabilities: { type: Object, required: true },
});

const u = props.user.data;

function primaryRole(roles) {
    if (!roles || roles.length === 0) return '—';
    return roles[0];
}

function status() {
    if (u.is_active === false) return 'Deactivated';
    return u.email_verified ? 'Active' : 'Invited';
}

function statusColor() {
    if (u.is_active === false) return 'bg-slate-200 text-slate-600';
    return u.email_verified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800';
}

function deactivate() {
    if (!confirm(`Deactivate ${u.name}? They will be logged out immediately and cannot log in until reactivated.`)) return;
    router.post(route('admin.org.users.deactivate', u.id));
}

function reactivate() {
    router.post(route('admin.org.users.reactivate', u.id));
}
</script>

<template>
    <Head :title="u.name" />
    <AppLayout>
        <div class="max-w-3xl">
            <Link :href="route('admin.org.users.index')" class="text-sm text-slate-500 hover:text-slate-700 mb-4 inline-block">← Back to users</Link>

            <div class="bg-white border border-slate-200 rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">{{ u.name }}</h1>
                        <p class="text-sm text-slate-500 font-mono mt-1">{{ u.username }}</p>
                    </div>
                    <div class="flex gap-2">
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700">{{ primaryRole(u.roles) }}</span>
                        <span :class="['inline-block px-2 py-0.5 rounded text-xs', statusColor()]">{{ status() }}</span>
                    </div>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-slate-500">Email</dt><dd class="text-slate-900">{{ u.email ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Phone</dt><dd class="text-slate-900">{{ u.phone_number ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Position</dt><dd class="text-slate-900">{{ u.position ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Office</dt><dd class="text-slate-900">{{ u.office?.name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Organization</dt><dd class="text-slate-900">{{ u.organization?.name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Member since</dt><dd class="text-slate-900">{{ u.created_at ? new Date(u.created_at).toLocaleDateString() : '—' }}</dd></div>
                </dl>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <Link v-if="capabilities.can_edit" :href="route('admin.org.users.edit', u.id)" class="px-3 py-1.5 rounded border border-slate-300 text-sm hover:bg-slate-50">Edit details</Link>
                <Link v-if="capabilities.can_assign_roles" :href="route('admin.org.users.editRole', u.id)" class="px-3 py-1.5 rounded border border-slate-300 text-sm hover:bg-slate-50">Change role</Link>
                <Link v-if="capabilities.can_reassign_org" :href="route('admin.org.users.edit', u.id)" class="px-3 py-1.5 rounded border border-slate-300 text-sm hover:bg-slate-50">Change organisation</Link>
                <Link v-if="capabilities.can_reset_password" :href="route('admin.org.users.password', u.id)" class="px-3 py-1.5 rounded border border-slate-300 text-sm hover:bg-slate-50">Reset password</Link>
                <button v-if="capabilities.can_deactivate && u.is_active !== false" type="button" class="px-3 py-1.5 rounded border border-rose-300 text-sm text-rose-700 hover:bg-rose-50" @click="deactivate">Deactivate</button>
                <button v-if="capabilities.can_deactivate && u.is_active === false" type="button" class="px-3 py-1.5 rounded border border-emerald-300 text-sm text-emerald-700 hover:bg-emerald-50" @click="reactivate">Reactivate</button>
            </div>
        </div>
    </AppLayout>
</template>
