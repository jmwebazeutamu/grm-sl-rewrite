<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    resource: string;
    grievanceTypes: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    name: '',
    description: '',
    grievance_type_id: props.grievanceTypes[0]?.id ?? null,
});

function submit(): void {
    form.post(route(`admin.reference.${props.resource}.store`));
}
</script>

<template>
    <Head title="New case concept" />
    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-4">New case concept</h1>

        <form class="bg-white rounded shadow-sm p-6 space-y-4 max-w-xl" @submit.prevent="submit">
            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Grievance type</span>
                <select
                    v-model="form.grievance_type_id"
                    required
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                >
                    <option v-for="gt in grievanceTypes" :key="gt.id" :value="gt.id">{{ gt.name }}</option>
                </select>
                <span v-if="form.errors.grievance_type_id" class="text-sm text-rose-600">{{ form.errors.grievance_type_id }}</span>
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Name</span>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    maxlength="150"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                />
                <span v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</span>
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Description</span>
                <textarea
                    v-model="form.description"
                    rows="4"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                />
                <span v-if="form.errors.description" class="text-sm text-rose-600">{{ form.errors.description }}</span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800 disabled:opacity-50"
            >
                Save
            </button>
        </form>
    </AppLayout>
</template>
