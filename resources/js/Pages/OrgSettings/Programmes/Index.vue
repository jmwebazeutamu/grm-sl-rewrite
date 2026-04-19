<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    programmes: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    is_super_admin: { type: Boolean, default: false },
    actor_organization_id: { type: [Number, null], default: null },
    can_manage: { type: Boolean, default: false },
});

const editingId = ref(null);
const editForm = useForm({ name: '', acronym: '', status: 'active' });
const showAdd = ref(false);

const addForm = useForm({
    name: '',
    acronym: '',
    status: 'active',
    organization_id: props.actor_organization_id,
});

const addOrgId = computed(() => (props.is_super_admin ? addForm.organization_id : props.actor_organization_id));

function startEdit(p) {
    editingId.value = p.id;
    editForm.name = p.name;
    editForm.acronym = p.acronym ?? '';
    editForm.status = p.status;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(p) {
    editForm.patch(route('admin.organizations.programmes.update', [p.organization_id, p.id]), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function submitAdd() {
    if (!addOrgId.value) return;
    addForm.post(route('admin.organizations.programmes.store', addOrgId.value), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            addForm.status = 'active';
            addForm.organization_id = props.actor_organization_id;
            showAdd.value = false;
        },
    });
}

function destroy(p) {
    if (!confirm(`Delete "${p.name}"?`)) return;
    router.delete(route('admin.organizations.programmes.destroy', [p.organization_id, p.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Programmes" />
    <AppLayout>
        <div class="max-w-5xl">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 flex items-center gap-2">
                        Programmes &amp; Projects
                        <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-100 text-xs text-slate-600">
                            {{ programmes.length }}
                        </span>
                    </h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Active programmes appear on the public grievance submission form so complainants
                        can link their case to a specific programme.
                    </p>
                </div>
                <button
                    v-if="can_manage"
                    type="button"
                    class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
                    @click="showAdd = !showAdd"
                >
                    {{ showAdd ? 'Cancel' : 'Add programme' }}
                </button>
            </div>

            <table class="w-full bg-white border border-slate-200 rounded-lg">
                <thead class="text-left text-sm text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3 w-32">Acronym</th>
                        <th v-if="is_super_admin" class="px-4 py-3">Organisation</th>
                        <th class="px-4 py-3 w-32">Status</th>
                        <th class="px-4 py-3 w-40 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in programmes" :key="p.id" class="border-b border-slate-100 last:border-0">
                        <template v-if="editingId === p.id">
                            <td class="px-4 py-3">
                                <input v-model="editForm.name" type="text" maxlength="255" class="w-full rounded border-slate-300 text-sm" />
                                <span v-if="editForm.errors.name" class="text-xs text-rose-600">{{ editForm.errors.name }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <input v-model="editForm.acronym" type="text" maxlength="20" class="w-full rounded border-slate-300 text-sm" />
                            </td>
                            <td v-if="is_super_admin" class="px-4 py-3 text-sm text-slate-600">{{ p.organization?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <select v-model="editForm.status" class="w-full rounded border-slate-300 text-sm">
                                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button
                                    type="button"
                                    :disabled="editForm.processing"
                                    class="px-2.5 py-1 rounded bg-slate-900 text-white text-xs disabled:opacity-50"
                                    @click="saveEdit(p)"
                                >
                                    Save
                                </button>
                                <button type="button" class="text-xs text-slate-600 underline" @click="cancelEdit">
                                    Cancel
                                </button>
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ p.name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 font-mono">{{ p.acronym || '—' }}</td>
                            <td v-if="is_super_admin" class="px-4 py-3 text-sm text-slate-600">{{ p.organization?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'inline-block px-2 py-0.5 rounded text-xs font-medium',
                                        p.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700',
                                    ]"
                                >
                                    {{ p.status === 'active' ? 'Active' : 'Closed' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <template v-if="can_manage">
                                    <button
                                        type="button"
                                        class="text-xs text-slate-600 hover:text-slate-900 underline"
                                        @click="startEdit(p)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs text-rose-600 hover:text-rose-800 underline"
                                        @click="destroy(p)"
                                    >
                                        Delete
                                    </button>
                                </template>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                        </template>
                    </tr>

                    <!-- Inline add row -->
                    <tr v-if="showAdd" class="bg-slate-50 border-t border-slate-200">
                        <td class="px-4 py-3">
                            <input
                                v-model="addForm.name"
                                type="text"
                                placeholder="Programme name"
                                maxlength="255"
                                class="w-full rounded border-slate-300 text-sm"
                            />
                            <span v-if="addForm.errors.name" class="text-xs text-rose-600">{{ addForm.errors.name }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <input
                                v-model="addForm.acronym"
                                type="text"
                                placeholder="e.g. FHI"
                                maxlength="20"
                                class="w-full rounded border-slate-300 text-sm"
                            />
                        </td>
                        <td v-if="is_super_admin" class="px-4 py-3">
                            <select v-model="addForm.organization_id" class="w-full rounded border-slate-300 text-sm">
                                <option :value="null">— Select —</option>
                                <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <select v-model="addForm.status" class="w-full rounded border-slate-300 text-sm">
                                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button
                                type="button"
                                :disabled="addForm.processing || !addOrgId"
                                class="px-2.5 py-1 rounded bg-slate-900 text-white text-xs disabled:opacity-50"
                                @click="submitAdd"
                            >
                                Save
                            </button>
                            <button type="button" class="text-xs text-slate-600 underline" @click="showAdd = false">
                                Cancel
                            </button>
                        </td>
                    </tr>

                    <tr v-if="programmes.length === 0 && !showAdd">
                        <td :colspan="is_super_admin ? 5 : 4" class="px-4 py-6 text-center text-sm text-slate-500">
                            No programmes yet. Use "Add programme" to create one.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
