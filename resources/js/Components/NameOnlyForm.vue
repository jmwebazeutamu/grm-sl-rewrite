<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    initial?: { id?: number; name?: string };
    resource: string;
    title: string;
}>();

const form = useForm({
    name: props.initial?.name ?? '',
});

function submit(): void {
    if (props.initial?.id) {
        form.put(route(`admin.reference.${props.resource}.update`, props.initial.id));
    } else {
        form.post(route(`admin.reference.${props.resource}.store`));
    }
}
</script>

<template>
    <h1 class="text-2xl font-semibold text-slate-900 mb-4">{{ title }}</h1>

    <form class="bg-white rounded shadow-sm p-6 space-y-4 max-w-xl" @submit.prevent="submit">
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

        <button
            type="submit"
            :disabled="form.processing"
            class="px-4 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800 disabled:opacity-50"
        >
            Save
        </button>
    </form>
</template>
