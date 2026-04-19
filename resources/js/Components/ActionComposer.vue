<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface ActionType {
    value: string;
    label: string;
}

const props = defineProps<{
    grievanceId: number;
    types: ActionType[];
    disabled?: boolean;
}>();

const form = useForm({
    type: props.types[0]?.value ?? '',
    body: '',
    assigned_to_id: null as number | null,
});

function submit(): void {
    form.post(route('admin.grievances.actions.store', props.grievanceId), {
        preserveScroll: true,
        onSuccess: () => {
            form.body = '';
        },
    });
}
</script>

<template>
    <form class="bg-white border border-slate-200 rounded p-4 space-y-3" @submit.prevent="submit">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-slate-700">Action</label>
            <select v-model="form.type" :disabled="disabled" class="rounded border-slate-300 text-sm">
                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
        </div>

        <textarea
            v-model="form.body"
            :disabled="disabled"
            rows="3"
            maxlength="10000"
            placeholder="Describe what was done, what you learned, or the resolution…"
            class="block w-full rounded border-slate-300 text-sm"
            required
        />
        <span v-if="form.errors.body" class="text-sm text-rose-600">{{ form.errors.body }}</span>

        <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">
                Posting a <em>Resolution</em> action moves the case to Resolved and sends the complainant a
                feedback link.
            </p>
            <button
                type="submit"
                :disabled="disabled || form.processing || !form.body"
                class="px-3 py-1.5 rounded bg-slate-900 text-white text-sm hover:bg-slate-800 disabled:opacity-50"
            >
                Post
            </button>
        </div>
    </form>
</template>
