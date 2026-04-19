<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    org_sla_days: { type: Number, default: 30 },
    programmes: { type: Array, default: () => [] },
});

const orgForm = useForm({ sla_days: props.org_sla_days });

function saveOrgSla() {
    orgForm.patch(route('admin.org.sla.updateOrg'), { preserveScroll: true });
}

function saveProgrammeSla(programme, value) {
    router.patch(
        route('admin.org.sla.updateProgramme', programme.id),
        { sla_days: value },
        { preserveScroll: true },
    );
}

function clearProgrammeSla(programme) {
    router.patch(
        route('admin.org.sla.updateProgramme', programme.id),
        { sla_days: null },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="SLA Settings" />
    <AppLayout>
        <div class="max-w-3xl">
            <h1 class="text-2xl font-semibold text-slate-900 mb-1">SLA Settings</h1>
            <p class="text-sm text-slate-500 mb-6">
                Set the number of days within which grievances should be resolved.
                Grievances approaching or exceeding these limits are flagged on the dashboard.
            </p>

            <!-- Section 1 — Organisation default -->
            <section class="bg-white border border-slate-200 rounded-lg p-5 mb-6">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Organisation default SLA</h2>
                <form class="flex items-center gap-3" @submit.prevent="saveOrgSla">
                    <input
                        v-model.number="orgForm.sla_days"
                        type="number"
                        min="1"
                        max="365"
                        required
                        class="w-20 rounded border-slate-300 text-sm text-center"
                    />
                    <span class="text-sm text-slate-600">days</span>
                    <button
                        type="submit"
                        :disabled="orgForm.processing"
                        class="px-3 py-1.5 rounded bg-slate-900 text-white text-xs disabled:opacity-50"
                    >
                        Save
                    </button>
                    <span v-if="orgForm.errors.sla_days" class="text-xs text-rose-600">{{ orgForm.errors.sla_days }}</span>
                </form>
            </section>

            <!-- Section 2 — Per-programme overrides -->
            <section class="bg-white border border-slate-200 rounded-lg p-5">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Per-programme SLA overrides</h2>

                <p v-if="programmes.length === 0" class="text-sm text-slate-500 italic">
                    No programmes configured. Add programmes under Configuration → Programmes.
                </p>

                <table v-else class="w-full text-sm">
                    <thead class="text-left text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="py-2">Programme</th>
                            <th class="py-2 w-32">SLA days</th>
                            <th class="py-2 w-28"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in programmes" :key="p.id" class="border-b border-slate-100 last:border-0">
                            <td class="py-2 text-slate-900">{{ p.name }}</td>
                            <td class="py-2">
                                <input
                                    :id="`sla-${p.id}`"
                                    :value="p.sla_days"
                                    type="number"
                                    min="1"
                                    max="365"
                                    :placeholder="`Default (${org_sla_days})`"
                                    class="w-full rounded border-slate-300 text-sm text-center"
                                    @change="(e) => saveProgrammeSla(p, e.target.value === '' ? null : Number(e.target.value))"
                                />
                            </td>
                            <td class="py-2 text-right">
                                <button
                                    v-if="p.sla_days !== null"
                                    type="button"
                                    class="text-xs text-slate-600 hover:text-slate-900 underline"
                                    @click="clearProgrammeSla(p)"
                                >
                                    Clear
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="mt-4 text-xs text-slate-500">
                    If a programme has no SLA override, the organisation default applies.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
