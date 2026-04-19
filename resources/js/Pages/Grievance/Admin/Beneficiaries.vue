<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    beneficiaries: { type: Object, required: true },
    programmes: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const f = reactive({
    search: props.filters.search ?? '',
    programme_id: props.filters.programme_id ?? '',
    organization_id: props.filters.organization_id ?? '',
    state: props.filters.state ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

function applyFilters() {
    const params = {};
    Object.entries(f).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get(route('admin.beneficiaries.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

function clearFilters() {
    Object.keys(f).forEach((k) => { f[k] = ''; });
    applyFilters();
}

const activeFilterCount = computed(() => Object.values(f).filter((v) => v && v !== '').length);

function stateStyle(stateValue) {
    const map = {
        submitted: 'bg-blue-100 text-blue-800', under_review: 'bg-indigo-100 text-indigo-800',
        accepted: 'bg-blue-100 text-blue-700', categorized: 'bg-cyan-100 text-cyan-800',
        assigned: 'bg-violet-100 text-violet-800', org_classified: 'bg-purple-100 text-purple-800',
        in_progress: 'bg-amber-100 text-amber-800', reopened: 'bg-orange-100 text-orange-800',
        resolved: 'bg-emerald-100 text-emerald-800', under_admin_review: 'bg-amber-100 text-amber-700',
        closed: 'bg-green-100 text-green-800', rejected: 'bg-red-100 text-red-800',
        escalated: 'bg-orange-100 text-orange-800', trashed: 'bg-slate-200 text-slate-600',
    };
    return map[stateValue] ?? 'bg-slate-100 text-slate-700';
}

function stateLabel(stateValue) {
    const s = props.states.find((st) => st.value === stateValue);
    return s?.label ?? stateValue?.replace(/_/g, ' ') ?? '—';
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString();
}
</script>

<template>
    <Head title="Project Beneficiaries" />
    <AppLayout>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--color-navy);">Project Beneficiaries</h1>
                <p class="text-sm mt-0.5" style="color: var(--color-slate);">
                    {{ beneficiaries.total }} beneficiar{{ beneficiaries.total === 1 ? 'y' : 'ies' }} registered a grievance
                </p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="bg-white rounded-xl p-4 mb-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[220px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    <input v-model="f.search" type="text" placeholder="Search by name or ID number…" class="w-full pl-10 pr-3 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent" @keydown.enter="applyFilters" />
                </div>
                <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium text-white transition hover:opacity-90" style="background: var(--color-navy);" @click="applyFilters">Filter</button>
            </div>

            <div class="flex flex-wrap items-end gap-3 mt-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">Programme</label>
                    <select v-model="f.programme_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">All programmes</option>
                        <option v-for="p in programmes" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">Organisation</label>
                    <select v-model="f.organization_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">All organisations</option>
                        <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">Status</label>
                    <select v-model="f.state" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                        <option value="">All states</option>
                        <option v-for="s in states" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">From</label>
                    <input v-model="f.date_from" type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 font-medium">To</label>
                    <input v-model="f.date_to" type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                </div>
                <div class="flex flex-col justify-end">
                    <button type="button" class="text-sm text-gray-400 hover:text-gray-600 underline py-2 whitespace-nowrap" @click="clearFilters">Clear filters</button>
                </div>
            </div>

            <p v-if="activeFilterCount > 0" class="text-xs text-gray-500 mt-2">
                {{ activeFilterCount }} filter{{ activeFilterCount > 1 ? 's' : '' }} active
                <button type="button" class="ml-2 text-red-400 hover:text-red-600 underline" @click="clearFilters">Clear all</button>
            </p>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl overflow-hidden" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
            <table class="w-full">
                <thead>
                    <tr style="background: var(--color-surface); border-bottom: 2px solid var(--color-border);">
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">ID Number</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">Name</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">Programme</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">Organisation</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">Grievance Ref</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">Status</th>
                        <th class="px-4 py-2 text-xs font-semibold text-gray-700 uppercase tracking-wide text-left">Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="b in beneficiaries.data"
                        :key="b.id"
                        class="border-b border-gray-100 hover:bg-gray-50 transition-colors duration-100"
                    >
                        <td class="px-4 py-2.5 text-sm text-gray-900 font-semibold font-mono">
                            {{ b.beneficiary_id_number || '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-800">
                            {{ [b.first_name, b.last_name].filter(Boolean).join(' ') || '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-700">
                            {{ b.programme?.name ?? '' }}<span v-if="!b.programme" class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-700">
                            {{ b.implementing_organization?.name ?? '' }}<span v-if="!b.implementing_organization" class="text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <Link :href="route('admin.grievances.show', b.grievance_id)" class="font-mono text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">
                                {{ b.g_number }}
                            </Link>
                        </td>
                        <td class="px-4 py-2.5">
                            <span v-if="b.state" :class="['state-badge inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold leading-none', stateStyle(b.state)]">
                                {{ stateLabel(b.state) }}
                            </span>
                            <span v-else class="text-gray-400 text-xs">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-600 whitespace-nowrap">
                            {{ fmtDate(b.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty state -->
            <div v-if="beneficiaries.data.length === 0" class="text-center py-16">
                <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                <p class="text-sm font-medium text-gray-500">No beneficiaries found</p>
                <p class="text-xs text-gray-400 mt-1">Try adjusting your filters</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="beneficiaries.links && beneficiaries.links.length > 3" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in beneficiaries.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="['px-3 py-1.5 rounded text-sm', link.active ? 'bg-[var(--color-navy)] text-white font-semibold' : 'text-gray-600 hover:bg-gray-100']"
                    v-html="link.label"
                    preserve-state
                    preserve-scroll
                />
                <span v-else class="px-3 py-1.5 text-sm text-gray-300" v-html="link.label" />
            </template>
        </div>
    </AppLayout>
</template>
