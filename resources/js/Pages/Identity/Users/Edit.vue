<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, required: true },
    offices: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    is_super_admin: { type: Boolean, default: false },
});

const u = props.user.data;

const form = useForm({
    name: u.name ?? '',
    email: u.email ?? '',
    phone_number: u.phone_number ?? '',
    position: u.position ?? '',
    office_id: u.office_id ?? null,
    organization_id: u.organization_id ?? null,
});

function submit() {
    form.put(route('admin.org.users.update', u.id));
}
</script>

<template>
    <Head :title="`Edit ${u.name}`" />
    <AppLayout>
        <div class="max-w-xl">
            <Link :href="route('admin.org.users.show', u.id)" class="text-sm text-slate-500 hover:text-slate-700 mb-4 inline-block">← Back to profile</Link>

            <h1 class="text-2xl font-semibold text-slate-900 mb-2">Edit details</h1>

            <form class="bg-white rounded shadow-sm p-6 space-y-4" @submit.prevent="submit">
                <div class="text-sm text-slate-500 mb-4">
                    <span class="font-mono">{{ u.username }}</span> — Usernames cannot be changed.
                </div>

                <!-- Organisation (super-admin only) -->
                <label v-if="is_super_admin" class="block">
                    <span class="block text-sm font-medium text-slate-700">Organisation</span>
                    <select v-model="form.organization_id" class="mt-1 block w-full rounded border-slate-300">
                        <option :value="null">— No organisation —</option>
                        <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                    <span v-if="form.errors.organization_id" class="text-sm text-rose-600">{{ form.errors.organization_id }}</span>
                </label>

                <!-- Read-only org display for non-super-admin -->
                <div v-else-if="u.organization" class="text-sm text-slate-500 mb-2">
                    Organisation: <span class="text-slate-900 font-medium">{{ u.organization.name }}</span>
                </div>

                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Full name</span>
                    <input v-model="form.name" type="text" required maxlength="150" class="mt-1 block w-full rounded border-slate-300" />
                    <span v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Email</span>
                    <input v-model="form.email" type="email" maxlength="150" class="mt-1 block w-full rounded border-slate-300" />
                    <span v-if="form.errors.email" class="text-sm text-rose-600">{{ form.errors.email }}</span>
                </label>

                <div class="grid grid-cols-2 gap-4">
                    <label class="block">
                        <span class="block text-sm font-medium text-slate-700">Phone</span>
                        <input v-model="form.phone_number" type="tel" maxlength="30" class="mt-1 block w-full rounded border-slate-300" />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-slate-700">Position</span>
                        <input v-model="form.position" type="text" maxlength="150" class="mt-1 block w-full rounded border-slate-300" />
                    </label>
                </div>

                <label v-if="offices.length > 0" class="block">
                    <span class="block text-sm font-medium text-slate-700">Office</span>
                    <select v-model="form.office_id" class="mt-1 block w-full rounded border-slate-300">
                        <option :value="null">— None —</option>
                        <option v-for="o in offices" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </label>

                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50">Save changes</button>
                    <Link :href="route('admin.org.users.show', u.id)" class="px-4 py-2 rounded border border-slate-300 text-sm">Cancel</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
