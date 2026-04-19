<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    default_role: { type: String, required: true },
    allowed_roles: { type: Array, required: true },
    offices: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    is_super_admin: { type: Boolean, default: false },
    actor_organization_id: { type: [Number, null], default: null },
});

const mode = ref('invite');

const inviteForm = useForm({
    name: '',
    username: '',
    email: '',
    phone_number: '',
    position: '',
    office_id: null,
    organization_id: props.actor_organization_id,
    role: props.default_role,
});

const createForm = useForm({
    name: '',
    username: '',
    email: '',
    phone_number: '',
    position: '',
    office_id: null,
    organization_id: props.actor_organization_id,
    role: props.default_role,
    password: '',
    password_confirmation: '',
});

function submit() {
    if (mode.value === 'invite') {
        inviteForm.post(route('admin.org.users.invite'));
    } else {
        createForm.post(route('admin.org.users.store'));
    }
}

const form = () => (mode.value === 'invite' ? inviteForm : createForm);
</script>

<template>
    <Head title="Add user" />
    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-2">Add user</h1>
        <p class="text-sm text-slate-500 mb-6">
            {{ is_super_admin ? 'Create a user for any organisation.' : 'The new user joins your organization.' }}
            Choose how they get their first password.
        </p>

        <div class="inline-flex bg-white border border-slate-200 rounded p-1 text-sm mb-6" role="tablist">
            <button type="button" :class="['px-4 py-1.5 rounded', mode === 'invite' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100']" @click="mode = 'invite'">
                Invite by email
            </button>
            <button type="button" :class="['px-4 py-1.5 rounded', mode === 'create' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100']" @click="mode = 'create'">
                Create with password
            </button>
        </div>

        <p v-if="mode === 'invite'" class="text-xs text-slate-500 mb-4">
            We'll send a password-reset email. The new user sets their own password.
        </p>
        <p v-else class="text-xs text-slate-500 mb-4">
            You'll set the password. Use this when the person has no email or needs immediate access.
        </p>

        <form class="bg-white rounded shadow-sm p-6 space-y-4 max-w-xl" @submit.prevent="submit">
            <label v-if="is_super_admin" class="block">
                <span class="block text-sm font-medium text-slate-700">Organisation</span>
                <select v-model="form().organization_id" required class="mt-1 block w-full rounded border-slate-300">
                    <option :value="null">— Select organisation —</option>
                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <span v-if="form().errors.organization_id" class="text-sm text-rose-600">{{ form().errors.organization_id }}</span>
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Full name</span>
                <input v-model="form().name" type="text" required maxlength="150" class="mt-1 block w-full rounded border-slate-300" />
                <span v-if="form().errors.name" class="text-sm text-rose-600">{{ form().errors.name }}</span>
            </label>

            <div class="grid grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Username</span>
                    <input v-model="form().username" type="text" required maxlength="50" pattern="[a-zA-Z0-9_\-\.]+" class="mt-1 block w-full rounded border-slate-300" />
                    <span v-if="form().errors.username" class="text-sm text-rose-600">{{ form().errors.username }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Role</span>
                    <select v-model="form().role" required class="mt-1 block w-full rounded border-slate-300">
                        <option v-for="r in allowed_roles" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <span v-if="form().errors.role" class="text-sm text-rose-600">{{ form().errors.role }}</span>
                </label>
            </div>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">
                    Email
                    <span v-if="mode === 'invite'" class="text-rose-600">*</span>
                    <span v-else class="text-slate-400">(optional)</span>
                </span>
                <input v-model="form().email" type="email" :required="mode === 'invite'" maxlength="150" class="mt-1 block w-full rounded border-slate-300" />
                <span v-if="form().errors.email" class="text-sm text-rose-600">{{ form().errors.email }}</span>
            </label>

            <div class="grid grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Phone</span>
                    <input v-model="form().phone_number" type="tel" maxlength="30" class="mt-1 block w-full rounded border-slate-300" />
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Position / title</span>
                    <input v-model="form().position" type="text" maxlength="150" placeholder="e.g. Senior Case Officer" class="mt-1 block w-full rounded border-slate-300" />
                </label>
            </div>

            <label v-if="offices.length > 0" class="block">
                <span class="block text-sm font-medium text-slate-700">Office (optional)</span>
                <select v-model="form().office_id" class="mt-1 block w-full rounded border-slate-300">
                    <option :value="null">— No specific office —</option>
                    <option v-for="o in offices" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
            </label>

            <template v-if="mode === 'create'">
                <div class="border-t pt-4">
                    <h2 class="text-sm font-semibold text-slate-700 mb-2">Set password</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block">
                            <span class="block text-sm font-medium text-slate-700">Password</span>
                            <input v-model="createForm.password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 block w-full rounded border-slate-300" />
                            <span v-if="createForm.errors.password" class="text-sm text-rose-600">{{ createForm.errors.password }}</span>
                        </label>
                        <label class="block">
                            <span class="block text-sm font-medium text-slate-700">Confirm password</span>
                            <input v-model="createForm.password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-1 block w-full rounded border-slate-300" />
                        </label>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Minimum 8 characters.</p>
                </div>
            </template>

            <button type="submit" :disabled="form().processing" class="px-4 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800 disabled:opacity-50">
                {{ mode === 'invite' ? 'Send invite' : 'Create user' }}
            </button>
        </form>
    </AppLayout>
</template>
