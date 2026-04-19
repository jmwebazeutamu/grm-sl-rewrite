<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    grievances: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    states: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    grievanceTypes: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    canFilterByOrg: { type: Boolean, default: false },
});

const f = reactive({
    search: props.filters.search ?? '',
    ref: props.filters.ref ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    district_id: props.filters.district_id ?? '',
    grievance_type_id: props.filters.grievance_type_id ?? '',
    organization_id: props.filters.organization_id ?? '',
    state: props.filters.state ?? '',
    sla_status: props.filters.sla_status ?? '',
});

function applyFilters() {
    const params = {};
    Object.entries(f).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get(route('admin.grievances.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

function clearFilters() {
    Object.keys(f).forEach((k) => { f[k] = ''; });
    applyFilters();
}

const activeFilterCount = computed(() =>
    Object.values(f).filter((v) => v !== null && v !== '' && v !== undefined).length,
);

const filteredGrievances = computed(() => {
    const list = props.grievances.data ?? [];
    if (!f.sla_status) return list;
    return list.filter((g) => g.sla_status === f.sla_status);
});

function stateStyle(g) {
    const s = g.state.value;
    const map = {
        submitted: 'bg-blue-100 text-blue-800', under_review: 'bg-indigo-100 text-indigo-800',
        accepted: 'bg-blue-100 text-blue-700', categorized: 'bg-cyan-100 text-cyan-800',
        assigned: 'bg-violet-100 text-violet-800', org_classified: 'bg-purple-100 text-purple-800',
        in_progress: 'bg-amber-100 text-amber-800', reopened: 'bg-orange-100 text-orange-800',
        resolved: 'bg-emerald-100 text-emerald-800', under_admin_review: 'bg-amber-100 text-amber-700',
        closed: 'bg-green-100 text-green-800', rejected: 'bg-red-100 text-red-800',
        escalated: 'bg-orange-100 text-orange-800', trashed: 'bg-slate-200 text-slate-600',
    };
    return map[s] ?? 'bg-slate-100 text-slate-700';
}

function daysBadge(g) {
    const s = g.sla_status;
    if (s === 'red') return 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800';
    if (s === 'amber') return 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800';
    if (s === 'grey') return 'text-xs text-gray-300';
    return 'text-xs text-gray-500';
}

const total = computed(() => props.grievances.data?.length ?? 0);
</script>

<template>
    <Head title="Grievances" />
    <AppLayout>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--color-navy);">Grievances</h1>
                <p class="text-sm mt-0.5" style="color: var(--color-slate);">{{ total }} cases in current view</p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="bg-white rounded-xl p-4 mb-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
            <!-- Row 1 — primary -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[220px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    <input v-model="f.search" type="text" placeholder="Search by summary or reference number…" class="w-full pl-10 pr-3 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent" @keydown.enter="applyFilters" />
                </div>
                <select v-model="f.state" class="rounded-lg text-sm border border-gray-200 py-2 px-3 text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400" @change="applyFilters">
                    <option value="">All states</option>
                    <option v-for="s in states" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="f.sla_status" class="rounded-lg text-sm border border-gray-200 py-2 px-3 text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                    <option value="">All SLA</option>
                    <option value="green">Within SLA</option>
                    <option value="amber">Approaching</option>
                    <option value="red">Breached</option>
                </select>
                <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium text-white transition hover:opacity-90" style="background: var(--color-navy);" @click="applyFilters">Filter</button>
            </div>

            <!-- Row 2 — secondary -->
            <div class="flex flex-wrap items-end gap-3 mt-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">Ref number</label>
                    <input v-model="f.ref" type="text" placeholder="GRM-2026-..." class="w-44 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent" @keydown.enter="applyFilters" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">From</label>
                    <input v-model="f.date_from" type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">To</label>
                    <input v-model="f.date_to" type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">District</label>
                    <select v-model="f.district_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">All districts</option>
                        <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">Type</label>
                    <select v-model="f.grievance_type_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">All types</option>
                        <option v-for="t in grievanceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <div v-if="canFilterByOrg" class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">Organisation</label>
                    <select v-model="f.organization_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">All organisations</option>
                        <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col justify-end">
                    <button type="button" class="text-sm text-gray-400 hover:text-gray-600 underline py-2 whitespace-nowrap" @click="clearFilters">Clear filters</button>
                </div>
            </div>

            <!-- Active filter count -->
            <p v-if="activeFilterCount > 0" class="text-xs text-gray-500 mt-2">
                {{ activeFilterCount }} filter{{ activeFilterCount > 1 ? 's' : '' }} active
                <button type="button" class="ml-2 text-red-400 hover:text-red-600 underline" @click="clearFilters">Clear all</button>
            </p>
        </div>

        <!-- SLA legend -->
        <div class="flex items-center justify-end gap-4 mb-2 text-xs text-gray-400">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block" /> Approaching (80%)</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500 inline-block" /> Breached</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-300 inline-block" /> Resolved / Closed</span>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl overflow-hidden" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
            <table class="w-full">
                <thead>
                    <tr style="background: var(--color-surface); border-bottom: 2px solid var(--color-border);">
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left w-36">Ref</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">Summary</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left w-40">Sub-classification</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left w-32">Programme</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left w-28">District</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left w-16">Days</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left w-32">State</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left w-28">Received</th>
                        <th class="px-4 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="g in filteredGrievances"
                        :key="g.id"
                        class="border-b border-gray-100 hover:bg-gray-50 transition-colors duration-100"
                    >
                        <td class="px-4 py-2.5">
                            <Link :href="route('admin.grievances.show', g.id)" class="font-mono text-xs font-semibold text-gray-900 hover:underline">
                                {{ g.g_number }}
                            </Link>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-800 max-w-xs truncate">{{ g.summary }}</td>
                        <td class="px-4 py-2.5 text-sm text-gray-700">
                            <span v-if="g.org_classification_label">{{ g.org_classification_label }}</span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-700">
                            <span v-if="g.programme_name">{{ g.programme_name }}</span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-700">
                            <span v-if="g.district_name">{{ g.district_name }}</span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-2.5" :title="`SLA: ${g.sla_days} days — ${g.days_open} days elapsed`">
                            <span :class="daysBadge(g)">{{ g.days_open }}d</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <span :class="['state-badge inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold leading-none', stateStyle(g)]">
                                {{ g.state.label }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-600 whitespace-nowrap">
                            {{ g.received_at ? new Date(g.received_at).toLocaleDateString() : '—' }}
                        </td>
                        <td class="px-4 py-2.5">
                            <Link :href="route('admin.grievances.show', g.id)" class="text-gray-300 hover:text-gray-600 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="filteredGrievances.length === 0" class="py-16 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                <p class="text-sm font-medium text-slate-500">No grievances match your filters</p>
                <p class="text-xs text-slate-400 mt-1">Try adjusting the search or filter criteria</p>
            </div>
        </div>
    </AppLayout>
</template>
