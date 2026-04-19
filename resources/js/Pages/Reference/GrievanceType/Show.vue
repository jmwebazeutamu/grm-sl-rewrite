<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    item: { id: number; name: string };
    resource: string;
    usage: { grievances: number; caseConcepts: number };
    otherTypes: Array<{ id: number; name: string }>;
}>();

const inUse = computed(() => props.usage.grievances + props.usage.caseConcepts > 0);

const form = useForm<{ target_id: number | null }>({
    target_id: props.otherTypes[0]?.id ?? null,
});

function plainDestroy() {
    if (!confirm(`Delete "${props.item.name}"? This cannot be undone.`)) return;
    router.delete(route(`admin.reference.${props.resource}.destroy`, props.item.id));
}

function reassignAndDestroy() {
    if (form.target_id === null) return;
    const target = props.otherTypes.find((t) => t.id === form.target_id);
    const msg = `Reassign ${props.usage.grievances} grievance(s) and ${props.usage.caseConcepts} case concept(s) to "${target?.name}", then delete "${props.item.name}"?`;
    if (!confirm(msg)) return;
    form.post(route('admin.reference.grievance-types.reassign-and-destroy', props.item.id));
}
</script>

<template>
    <Head :title="item.name" />
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-slate-900">{{ item.name }}</h1>
            <div class="flex gap-2">
                <Link
                    :href="route(`admin.reference.${resource}.edit`, item.id)"
                    class="px-3 py-2 rounded border border-slate-300 text-sm hover:bg-slate-50"
                >
                    Edit
                </Link>
                <button
                    v-if="!inUse"
                    type="button"
                    @click="plainDestroy"
                    class="px-3 py-2 rounded border border-red-300 bg-red-50 text-sm text-red-700 hover:bg-red-100"
                >
                    Delete
                </button>
            </div>
        </div>

        <div v-if="inUse" class="bg-white rounded shadow-sm p-4">
            <h2 class="text-sm font-semibold text-slate-700 mb-2">This type is in use</h2>
            <ul class="text-sm text-slate-600 mb-4 space-y-1">
                <li v-if="usage.grievances > 0">
                    {{ usage.grievances }} grievance{{ usage.grievances === 1 ? '' : 's' }} reference this type
                </li>
                <li v-if="usage.caseConcepts > 0">
                    {{ usage.caseConcepts }} case concept{{ usage.caseConcepts === 1 ? '' : 's' }} reference this type
                </li>
            </ul>

            <div v-if="otherTypes.length === 0" class="text-sm text-amber-700">
                No other grievance type exists to reassign to. Create another type first.
            </div>
            <div v-else class="flex items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Reassign to
                    </label>
                    <select
                        v-model="form.target_id"
                        class="border border-slate-300 rounded px-3 py-2 text-sm"
                    >
                        <option v-for="t in otherTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <button
                    type="button"
                    :disabled="form.processing || form.target_id === null"
                    @click="reassignAndDestroy"
                    class="px-3 py-2 rounded border border-red-300 bg-red-50 text-sm text-red-700 hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Reassign &amp; delete
                </button>
            </div>
            <p v-if="form.errors.target_id" class="text-xs text-red-600 mt-2">{{ form.errors.target_id }}</p>
        </div>
    </AppLayout>
</template>
