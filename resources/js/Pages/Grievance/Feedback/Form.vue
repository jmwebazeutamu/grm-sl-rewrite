<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
    g_number: string;
    summary: string;
    ratings: Array<{ value: number; label: string }>;
}>();

const form = useForm({
    rating: 0,
    comment: '',
});

function submit(): void {
    form.post(route('grievances.feedback.store', props.token));
}
</script>

<template>
    <Head title="Your feedback" />

    <div class="min-h-screen bg-slate-50 py-10 px-4">
        <div class="max-w-lg mx-auto bg-white rounded shadow-sm p-8">
            <p class="text-xs font-mono text-slate-500">{{ g_number }}</p>
            <h1 class="text-2xl font-semibold text-slate-900 mt-1">How did we do?</h1>
            <p class="mt-2 text-slate-700">
                Your grievance — <em>{{ summary }}</em> — has been resolved. Please let us know if you're
                satisfied with the outcome.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-5 gap-2">
                    <button
                        v-for="r in ratings"
                        :key="r.value"
                        type="button"
                        :class="[
                            'rounded border p-2 text-xs text-center transition',
                            form.rating === r.value
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-900'
                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                        ]"
                        @click="form.rating = r.value"
                    >
                        {{ r.label }}
                    </button>
                </div>
                <span v-if="form.errors.rating" class="text-sm text-rose-600">{{ form.errors.rating }}</span>

                <label class="block">
                    <span class="block text-sm font-medium text-slate-700">Comment (optional)</span>
                    <textarea
                        v-model="form.comment"
                        rows="4"
                        maxlength="2000"
                        class="mt-1 block w-full rounded border-slate-300"
                    />
                </label>

                <button
                    type="submit"
                    :disabled="!form.rating || form.processing"
                    class="w-full px-4 py-3 rounded bg-slate-900 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Sending…' : 'Send feedback' }}
                </button>
            </form>
        </div>
    </div>
</template>
