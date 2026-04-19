<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    sections: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    name: '',
    section_id: props.sections[0]?.id ?? null,
});

function submit(): void {
    form.post(route('admin.localities.store'));
}
</script>

<template>
    <Head title="New locality" />

    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-4">New locality</h1>

        <form class="bg-white rounded shadow-sm p-6 space-y-4 max-w-xl" @submit.prevent="submit">
            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Name</span>
                <input
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                    required
                />
                <span v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</span>
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Section</span>
                <select
                    v-model="form.section_id"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                    required
                >
                    <option v-for="section in sections" :key="section.id" :value="section.id">
                        {{ section.name }}
                    </option>
                </select>
                <span v-if="form.errors.section_id" class="text-sm text-rose-600">{{ form.errors.section_id }}</span>
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
