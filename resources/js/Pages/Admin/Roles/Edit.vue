<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import PermissionTree from '@/Components/PermissionTree.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface Group {
    resource: string;
    permissions: string[];
}

const props = defineProps<{
    role: { id: number; name: string; is_protected: boolean } | null;
    selected: string[];
    grouped_permissions: Group[];
}>();

const form = useForm({
    name: props.role?.name ?? '',
    permissions: [...props.selected],
});

function save(): void {
    if (props.role) {
        form.put(route('admin.roles.update', props.role.id));
    } else {
        form.post(route('admin.roles.store'));
    }
}
</script>

<template>
    <Head :title="role ? `Edit ${role.name}` : 'New role'" />
    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-4">
            {{ role ? `Edit ${role.name}` : 'New role' }}
        </h1>

        <form class="space-y-6 max-w-3xl" @submit.prevent="save">
            <div class="bg-white border border-slate-200 rounded p-5">
                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Role name</span>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        pattern="[a-z][a-z0-9_\-]*"
                        :disabled="role?.is_protected"
                        maxlength="100"
                        class="mt-1 block w-full rounded border-slate-300 disabled:bg-slate-50"
                    />
                    <p class="mt-1 text-xs text-slate-500">Lowercase letters, digits, dash, underscore.</p>
                    <span v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</span>
                </label>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Permissions</h2>
                <PermissionTree
                    v-model="form.permissions"
                    :groups="grouped_permissions"
                    :disabled="role?.is_protected"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50"
            >
                Save role
            </button>
        </form>
    </AppLayout>
</template>
