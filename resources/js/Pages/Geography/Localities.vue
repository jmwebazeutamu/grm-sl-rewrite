<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    region: { type: Object, required: true },
    district: { type: Object, required: true },
    chiefdom: { type: Object, required: true },
    section: { type: Object, required: true },
    localities: { type: Array, default: () => [] },
});

const level = 'localities';
const editingId = ref(null);
const editForm = useForm({ name: '' });
const showAdd = ref(false);
const addForm = useForm({ name: '', parent_id: props.section.id });

function startEdit(row) { editingId.value = row.id; editForm.name = row.name; }
function cancelEdit() { editingId.value = null; editForm.reset(); }
function saveEdit(row) {
    editForm.patch(route('admin.geography.update', [level, row.id]), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}
function submitAdd() {
    addForm.post(route('admin.geography.store', level), {
        preserveScroll: true,
        onSuccess: () => { addForm.name = ''; showAdd.value = false; },
    });
}
function destroy(row) {
    if (!confirm(`Delete "${row.name}"?`)) return;
    router.delete(route('admin.geography.destroy', [level, row.id]), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${section.name} — Localities`" />
    <AppLayout>
        <div class="max-w-4xl">
            <nav class="text-sm text-slate-500 mb-3">
                <Link :href="route('admin.geography.index')" class="hover:underline">Geography</Link>
                <span class="mx-1">›</span>
                <Link :href="route('admin.geography.regions.show', region.id)" class="hover:underline">{{ region.name }}</Link>
                <span class="mx-1">›</span>
                <Link :href="route('admin.geography.districts.show', district.id)" class="hover:underline">{{ district.name }}</Link>
                <span class="mx-1">›</span>
                <Link :href="route('admin.geography.chiefdoms.show', chiefdom.id)" class="hover:underline">{{ chiefdom.name }}</Link>
                <span class="mx-1">›</span>
                <span class="text-slate-700">{{ section.name }}</span>
            </nav>
            <div class="flex items-start justify-between mb-6">
                <h1 class="text-2xl font-semibold text-slate-900">Localities in {{ section.name }}</h1>
                <button type="button" class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800" @click="showAdd = !showAdd">
                    {{ showAdd ? 'Cancel' : 'Add locality' }}
                </button>
            </div>

            <table class="w-full bg-white border border-slate-200 rounded-lg">
                <thead class="text-left text-sm text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3 w-40 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in localities" :key="l.id" class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3">
                            <template v-if="editingId === l.id">
                                <input v-model="editForm.name" type="text" class="w-full rounded border-slate-300 text-sm" />
                            </template>
                            <template v-else>
                                <span class="text-slate-900">{{ l.name }}</span>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <template v-if="editingId === l.id">
                                <button type="button" class="px-2.5 py-1 rounded bg-slate-900 text-white text-xs" @click="saveEdit(l)">Save</button>
                                <button type="button" class="text-xs text-slate-600 underline" @click="cancelEdit">Cancel</button>
                            </template>
                            <template v-else>
                                <button type="button" class="text-xs text-slate-600 hover:text-slate-900 underline" @click="startEdit(l)">Edit</button>
                                <button type="button" class="text-xs text-rose-600 hover:text-rose-800 underline" @click="destroy(l)">Delete</button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="showAdd" class="bg-slate-50">
                        <td class="px-4 py-3"><input v-model="addForm.name" type="text" placeholder="Locality name" class="w-full rounded border-slate-300 text-sm" /></td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="px-2.5 py-1 rounded bg-slate-900 text-white text-xs" @click="submitAdd">Save</button>
                        </td>
                    </tr>
                    <tr v-if="localities.length === 0 && !showAdd">
                        <td colspan="2" class="px-4 py-6 text-center text-slate-500 text-sm">No localities.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
