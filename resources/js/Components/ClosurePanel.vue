<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    grievance: { type: Object, required: true },
    canBegin: { type: Boolean, default: false },
    canAct: { type: Boolean, default: false },
});

const state = computed(() => props.grievance.state.value);

const form = useForm({
    closure_comment: '',
    outcome: '',
});

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function begin() {
    router.post(route('admin.grievances.closure.begin', props.grievance.id), {}, {
        preserveScroll: true,
    });
}

function close() {
    form.outcome = 'satisfied';
    form.post(route('admin.grievances.closure.close', props.grievance.id), {
        preserveScroll: true,
    });
}

function escalate() {
    form.outcome = 'dissatisfied';
    form.post(route('admin.grievances.closure.escalate', props.grievance.id), {
        preserveScroll: true,
    });
}

const submitEnabled = computed(
    () => form.outcome !== '' && form.closure_comment.trim().length >= 10,
);
</script>

<template>
    <!-- STATE: resolved — begin review -->
    <section v-if="state === 'resolved'" class="bg-white border border-slate-200 rounded-lg p-5">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Closure review</h2>
        <p class="text-sm text-slate-600 mb-3">
            The organisation has marked this case resolved. Review the case and record the complainant's satisfaction outcome to close or escalate.
        </p>
        <button
            v-if="canBegin"
            type="button"
            class="px-4 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800"
            @click="begin"
        >
            Begin closure review
        </button>
    </section>

    <!-- STATE: under_admin_review — main decision UI -->
    <section v-else-if="state === 'under_admin_review'" class="bg-white border border-amber-200 rounded-lg p-5">
        <h2 class="text-sm font-semibold text-amber-800 uppercase tracking-wide mb-3">Closure review — in progress</h2>

        <p v-if="grievance.closure_reviewed_by" class="text-xs text-slate-500 mb-4">
            Review started by {{ grievance.closure_reviewed_by.name }} on {{ fmtDate(grievance.closure_reviewed_at) }}
        </p>

        <label class="block mb-4">
            <span class="block text-sm font-medium text-slate-700">Closure notes</span>
            <textarea
                v-model="form.closure_comment"
                rows="4"
                maxlength="2000"
                placeholder="Record your closure notes and the complainant's feedback…"
                class="mt-1 block w-full rounded border-slate-300 text-sm"
            />
            <span v-if="form.errors.closure_comment" class="text-sm text-rose-600">
                {{ form.errors.closure_comment }}
            </span>
        </label>

        <p class="text-sm font-medium text-slate-700 mb-2">Complainant satisfaction</p>
        <div v-if="canAct" class="grid grid-cols-2 gap-3 mb-4">
            <button
                type="button"
                :class="[
                    'border-2 rounded-lg p-4 text-left transition',
                    form.outcome === 'satisfied'
                        ? 'border-emerald-500 bg-emerald-50'
                        : 'border-slate-200 bg-white hover:border-slate-300',
                ]"
                @click="form.outcome = 'satisfied'"
            >
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-emerald-100 text-emerald-700">✓</span>
                    <span class="font-semibold text-emerald-900">Complainant satisfied</span>
                </div>
                <p class="text-xs text-slate-600">Close the grievance — no further action needed.</p>
            </button>
            <button
                type="button"
                :class="[
                    'border-2 rounded-lg p-4 text-left transition',
                    form.outcome === 'dissatisfied'
                        ? 'border-rose-500 bg-rose-50'
                        : 'border-slate-200 bg-white hover:border-slate-300',
                ]"
                @click="form.outcome = 'dissatisfied'"
            >
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-rose-100 text-rose-700">!</span>
                    <span class="font-semibold text-rose-900">Complainant dissatisfied</span>
                </div>
                <p class="text-xs text-slate-600">Escalate and reopen so the org can resume work.</p>
            </button>
        </div>

        <div v-if="canAct" class="flex items-center gap-3">
            <button
                v-if="form.outcome === 'satisfied'"
                type="button"
                :disabled="!submitEnabled || form.processing"
                class="px-4 py-2 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700 disabled:opacity-50"
                @click="close"
            >
                Close grievance
            </button>
            <button
                v-if="form.outcome === 'dissatisfied'"
                type="button"
                :disabled="!submitEnabled || form.processing"
                class="px-4 py-2 rounded bg-rose-600 text-white text-sm hover:bg-rose-700 disabled:opacity-50"
                @click="escalate"
            >
                Escalate and reopen
            </button>
            <p v-if="!submitEnabled" class="text-xs text-slate-500">
                Enter at least 10 characters of notes and select an outcome to continue.
            </p>
        </div>

        <p class="mt-4 text-xs text-slate-500 border-t pt-3">
            Closing is final and cannot be undone. Escalating will reopen the case for the organisation.
        </p>
    </section>

    <!-- STATE: closed — read-only summary -->
    <section v-else-if="state === 'closed'" class="bg-white border border-slate-200 rounded-lg p-5">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3 flex items-center gap-2">
            Closed
            <span class="inline-block px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-medium">
                Complainant satisfied
            </span>
        </h2>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-slate-500">Closed by</dt>
                <dd class="text-slate-900">{{ grievance.closure_reviewed_by?.name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Closed at</dt>
                <dd class="text-slate-900">{{ fmtDate(grievance.closed_at) }}</dd>
            </div>
        </dl>
        <div v-if="grievance.closure_comment" class="mt-3 border-t pt-3 text-sm">
            <dt class="text-slate-500">Closure notes</dt>
            <dd class="text-slate-900 whitespace-pre-wrap mt-1">{{ grievance.closure_comment }}</dd>
        </div>
    </section>

    <!-- STATE: reopened — historical record of the escalation -->
    <section v-else-if="state === 'reopened'" class="bg-white border border-orange-200 rounded-lg p-5">
        <h2 class="text-sm font-semibold text-orange-800 uppercase tracking-wide mb-3 flex items-center gap-2">
            Previously escalated
            <span class="inline-block px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-xs font-medium">
                Complainant dissatisfied
            </span>
        </h2>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-slate-500">Escalated by</dt>
                <dd class="text-slate-900">{{ grievance.closure_reviewed_by?.name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Escalated at</dt>
                <dd class="text-slate-900">{{ fmtDate(grievance.reopened_at) }}</dd>
            </div>
        </dl>
        <div v-if="grievance.closure_comment" class="mt-3 border-t pt-3 text-sm">
            <dt class="text-slate-500">Reviewer notes</dt>
            <dd class="text-slate-900 whitespace-pre-wrap mt-1">{{ grievance.closure_comment }}</dd>
        </div>
        <p class="mt-4 text-xs text-slate-500 italic border-t pt-3">
            This case was reopened after the complainant indicated dissatisfaction. The organisation is now resuming work.
        </p>
    </section>
</template>
