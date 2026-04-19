<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    roles: string[];
    organizations: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    username: '',
    name: '',
    email: '',
    phone_number: '',
    organization_id: null as number | null,
    roles: [] as string[],
});

function submit(): void {
    form.post(route('admin.users.store'));
}
</script>

<template>
    <Head title="Invite user" />
    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-4">Invite user</h1>

        <form class="bg-white rounded shadow-sm p-6 space-y-4 max-w-xl" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Username</span>
                    <input v-model="form.username" type="text" required maxlength="50" class="mt-1 block w-full rounded border-slate-300" />
                    <span v-if="form.errors.username" class="text-sm text-rose-600">{{ form.errors.username }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Full name</span>
                    <input v-model="form.name" type="text" required maxlength="150" class="mt-1 block w-full rounded border-slate-300" />
                </label>
            </div>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Email</span>
                <input v-model="form.email" type="email" required maxlength="150" class="mt-1 block w-full rounded border-slate-300" />
                <span v-if="form.errors.email" class="text-sm text-rose-600">{{ form.errors.email }}</span>
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Phone</span>
                <input v-model="form.phone_number" type="tel" maxlength="30" class="mt-1 block w-full rounded border-slate-300" />
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Primary organization</span>
                <select v-model="form.organization_id" class="mt-1 block w-full rounded border-slate-300">
                    <option :value="null">— None —</option>
                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
            </label>

            <fieldset>
                <legend class="text-sm font-medium text-slate-700 mb-2">Roles</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label v-for="r in roles" :key="r" class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.roles" :value="r" type="checkbox" class="rounded border-slate-300" />
                        {{ r }}
                    </label>
                </div>
            </fieldset>

            <p class="text-xs text-slate-500">A password-set email will be sent. The admin never sets the password directly.</p>

            <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50">
                Send invite
            </button>
        </form>
    </AppLayout>
</template>
