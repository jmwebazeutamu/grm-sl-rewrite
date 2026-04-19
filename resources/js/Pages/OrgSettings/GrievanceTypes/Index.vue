<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    types: { type: Array, required: true },
});

const showAdd = ref(false);
const addForm = useForm({ label: '' });

function addType() {
    addForm.post(route('admin.org.grievance-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
        },
    });
}

function toggleActive(type) {
    router.patch(route('admin.org.grievance-types.update', type.id), {
        active: !type.active,
    }, { preserveScroll: true });
}

function remove(type) {
    if (!confirm(`Delete "${type.label}"?`)) return;
    router.delete(route('admin.org.grievance-types.destroy', type.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Sub-classifications" />
    <AppLayout>
        <div class="max-w-2xl">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-semibold text-slate-900">Sub-classifications</h1>
                <button
                    type="button"
                    class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
                    @click="showAdd = !showAdd"
                >
                    {{ showAdd ? 'Cancel' : 'Add sub-classification' }}
                </button>
            </div>

            <p class="text-sm text-slate-500 mb-6">
                These are your organisation's internal sub-classification types. Officers select one when a
                grievance is assigned to your org, before case work begins.
            </p>

            <table class="w-full bg-white rounded shadow-sm">
                <thead class="text-left text-sm text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Label</th>
                        <th class="px-4 py-3 w-24">Active</th>
                        <th class="px-4 py-3 w-24 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="type in types" :key="type.id" class="border-t">
                        <td class="px-4 py-3 text-sm text-slate-900">{{ type.label }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                :class="[
                                    'text-xs px-2 py-0.5 rounded',
                                    type.active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500',
                                ]"
                                @click="toggleActive(type)"
                            >
                                {{ type.active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="text-xs text-rose-700 hover:text-rose-900"
                                @click="remove(type)"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="types.length === 0 && !showAdd">
                        <td colspan="3" class="px-4 py-6 text-center text-slate-500 text-sm">
                            No sub-classifications configured yet. Click "Add sub-classification" to create one.
                        </td>
                    </tr>

                    <!-- Inline add row -->
                    <tr v-if="showAdd" class="border-t bg-slate-50">
                        <td class="px-4 py-3" colspan="2">
                            <input
                                v-model="addForm.label"
                                type="text"
                                required
                                maxlength="150"
                                placeholder="e.g. Payments, Inclusion Errors…"
                                class="block w-full rounded border-slate-300 text-sm"
                                @keydown.enter="addType"
                            />
                            <span v-if="addForm.errors.label" class="text-xs text-rose-600">{{ addForm.errors.label }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                :disabled="!addForm.label || addForm.processing"
                                class="px-3 py-1 rounded bg-slate-900 text-white text-xs disabled:opacity-50"
                                @click="addType"
                            >
                                Save
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
