<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, required: true },
    allowed_roles: { type: Array, required: true },
    is_self: { type: Boolean, required: true },
    is_super_admin: { type: Boolean, default: false },
});

const u = props.user.data;
const currentRole = (u.roles && u.roles.length > 0) ? u.roles[0] : props.allowed_roles[0];

const form = useForm({ role: currentRole });

function submit() {
    form.put(route('admin.org.users.updateRole', u.id));
}
</script>

<template>
    <Head :title="`Change role — ${u.name}`" />
    <AppLayout>
        <div class="max-w-xl">
            <Link :href="route('admin.org.users.show', u.id)" class="text-sm text-slate-500 hover:text-slate-700 mb-4 inline-block">← Back to profile</Link>

            <h1 class="text-2xl font-semibold text-slate-900 mb-2">Change role</h1>
            <p class="text-sm text-slate-500 mb-4">{{ u.name }} · <span class="font-mono">{{ u.username }}</span></p>

            <div v-if="is_self" class="mb-4 px-4 py-2 rounded bg-amber-50 border border-amber-200 text-amber-900 text-sm">
                <strong>You cannot change your own role.</strong>
            </div>

            <form class="bg-white rounded shadow-sm p-6 space-y-4" @submit.prevent="submit">
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Role</span>
                    <select v-model="form.role" :disabled="is_self" required class="mt-1 block w-full rounded border-slate-300 disabled:bg-slate-50">
                        <option v-for="r in allowed_roles" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <p v-if="!is_super_admin" class="mt-1 text-xs text-slate-500">Org-admins cannot grant super-admin, ACC-reviewer, or org-admin roles.</p>
                    <span v-if="form.errors.role" class="text-sm text-rose-600">{{ form.errors.role }}</span>
                </label>

                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="form.processing || is_self" class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50">Update role</button>
                    <Link :href="route('admin.org.users.show', u.id)" class="px-4 py-2 rounded border border-slate-300 text-sm">Cancel</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
