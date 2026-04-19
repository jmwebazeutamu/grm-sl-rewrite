<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    reportData: { type: Object, default: () => ({ overall: [], by_programme: [], by_classification: [], available_years: [] }) },
    selectedYear: { type: Number, default: 2026 },
    isGlobalView: { type: Boolean, default: false },
    organizations: { type: Array, default: () => [] },
});

const year = ref(props.selectedYear);
const orgId = ref(null);
const quarterFilter = ref('all');
const loading = ref(false);
const data = ref(props.reportData);
const progViewMode = ref('count');

const COLOURS = ['#6366f1', '#22c55e', '#f59e0b', '#3b82f6', '#ec4899', '#14b8a6', '#f97316', '#8b5cf6'];

async function fetchData() {
    loading.value = true;
    const params = new URLSearchParams({ year: year.value });
    if (orgId.value) params.append('organization_id', orgId.value);
    try {
        const res = await fetch(`${route('admin.reports.quarterly.data')}?${params}`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) data.value = await res.json();
    } finally {
        loading.value = false;
    }
}

watch(year, fetchData);
watch(orgId, fetchData);

const overall = computed(() => data.value?.overall ?? []);
const hasData = computed(() => overall.value.some((q) => q.total > 0));
const maxTotal = computed(() => Math.max(...overall.value.map((q) => q.total), 1));

function rateClass(rate) {
    if (rate >= 75) return 'text-green-600 font-semibold';
    if (rate >= 50) return 'text-amber-600 font-semibold';
    if (rate > 0) return 'text-red-600 font-semibold';
    return 'text-gray-400';
}

function borderClass(rate) {
    if (rate >= 75) return 'border-l-4 border-green-500';
    if (rate >= 50) return 'border-l-4 border-amber-400';
    if (rate > 0) return 'border-l-4 border-red-500';
    return 'border-l-4 border-gray-200';
}

function barColour(rate) {
    if (rate >= 75) return '#22c55e';
    if (rate >= 50) return '#f59e0b';
    return '#ef4444';
}

const totalsRow = computed(() => {
    const o = overall.value;
    const total = o.reduce((s, q) => s + q.total, 0);
    const resolved = o.reduce((s, q) => s + q.resolved_within_sla, 0);
    const breached = o.reduce((s, q) => s + (q.breached ?? 0), 0);
    const withinWindow = o.reduce((s, q) => s + (q.within_window ?? 0), 0);
    const rate = total > 0 ? Math.round((resolved / total) * 1000) / 10 : 0;
    return { total, resolved, breached, withinWindow, rate };
});

const filteredClassification = computed(() => {
    const series = data.value?.by_classification ?? [];
    return series.map((s) => {
        const items = quarterFilter.value === 'all' ? s.data : s.data.filter((d) => d.quarter === quarterFilter.value);
        const total = items.reduce((a, i) => a + i.total, 0);
        const resolved = items.reduce((a, i) => a + i.resolved_within_sla, 0);
        const rate = total > 0 ? Math.round((resolved / total) * 1000) / 10 : 0;
        return { name: s.name, total, resolved, rate };
    }).filter((s) => s.total > 0).sort((a, b) => b.rate - a.rate);
});

function exportCsv() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('admin.reports.quarterly.export');
    form.style.display = 'none';
    const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; form.appendChild(i); };
    add('_token', csrf);
    add('year', year.value);
    if (orgId.value) add('organization_id', orgId.value);
    document.body.appendChild(form);
    form.submit();
    form.remove();
}
</script>

<template>
    <Head title="Quarterly Resolution Report" />
    <AppLayout>
        <!-- Header + controls -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <h1 class="text-2xl font-semibold text-slate-900">Quarterly Resolution Report</h1>
            <div class="flex items-center gap-3">
                <select v-model="year" class="rounded border-slate-300 text-sm">
                    <option v-for="y in data.available_years" :key="y" :value="y">{{ y }}</option>
                </select>
                <div class="flex rounded border border-slate-300 overflow-hidden text-xs">
                    <button v-for="q in ['all', 'Q1', 'Q2', 'Q3', 'Q4']" :key="q" type="button"
                        :class="['px-3 py-1.5', quarterFilter === q ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50']"
                        @click="quarterFilter = q">
                        {{ q === 'all' ? 'All' : q }}
                    </button>
                </div>
                <select v-if="isGlobalView" v-model="orgId" class="rounded border-slate-300 text-sm">
                    <option :value="null">All organisations</option>
                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <button type="button" class="px-3 py-2 rounded border border-slate-300 text-sm text-slate-700" @click="exportCsv">Export CSV</button>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-20 text-slate-500">
            <span class="inline-block h-5 w-5 rounded-full border-2 border-slate-400 border-t-transparent animate-spin mr-2" />
            Loading…
        </div>

        <div v-else-if="!hasData" class="flex items-center justify-center py-20 text-slate-400 text-sm">
            No grievance data for the selected period.
        </div>

        <template v-else>
            <!-- Summary cards -->
            <div class="grid grid-cols-4 gap-4 mb-8">
                <div v-for="q in overall" :key="q.quarter" :class="['bg-white rounded-lg p-4 border border-slate-200', borderClass(q.resolution_rate)]">
                    <div class="text-sm font-semibold text-slate-900">{{ q.quarter_label }}</div>
                    <div class="text-xs text-slate-500 mb-3">{{ q.date_range }}</div>
                    <div v-if="q.total > 0" class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-slate-600">Registered</span><span class="text-slate-900 font-medium">{{ q.total }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-600">Resolved ≤90d</span><span class="text-green-700 font-medium">{{ q.resolved_within_sla }} ({{ q.resolution_rate }}%)</span></div>
                        <div v-if="q.breached > 0" class="flex justify-between"><span class="text-slate-600">Breached (&gt;90d)</span><span class="text-red-700 font-medium">{{ q.breached }}</span></div>
                        <div v-if="q.within_window > 0" class="flex justify-between"><span class="text-slate-600">Open / within SLA</span><span class="text-amber-700 font-medium">{{ q.within_window }}</span></div>
                    </div>
                    <div v-else class="text-sm text-slate-400 italic mt-2">No data</div>
                </div>
            </div>

            <!-- Chart 1: Overall performance — grouped bar -->
            <section class="bg-white border border-slate-200 rounded-lg p-5 mb-6">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Resolution within 90 days by quarter</h2>
                <div class="flex items-end gap-6 h-48">
                    <div v-for="q in overall" :key="q.quarter" class="flex-1 flex flex-col items-center gap-1">
                        <div class="flex gap-1 items-end h-40 w-full justify-center">
                            <div class="w-6 bg-green-500 rounded-t transition-all" :style="{ height: (q.resolved_within_sla / maxTotal * 100) + '%' }" :title="`Resolved ≤90d: ${q.resolved_within_sla}`" />
                            <div class="w-6 bg-amber-500 rounded-t transition-all" :style="{ height: ((q.within_window ?? 0) / maxTotal * 100) + '%' }" :title="`Open / within SLA: ${q.within_window ?? 0}`" />
                            <div class="w-6 bg-red-500 rounded-t transition-all" :style="{ height: ((q.breached ?? 0) / maxTotal * 100) + '%' }" :title="`Breached: ${q.breached ?? 0}`" />
                        </div>
                        <div class="text-xs text-slate-600 font-medium">{{ q.quarter }}</div>
                        <div class="text-[10px] text-indigo-600 font-semibold">{{ q.resolution_rate }}%</div>
                    </div>
                </div>
                <div class="flex gap-4 justify-center mt-3 text-[10px] text-slate-500">
                    <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-green-500" /> Resolved ≤90d</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-amber-500" /> Open / within SLA</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-red-500" /> Breached</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-indigo-600" /> Rate %</span>
                </div>
            </section>

            <!-- Chart 2: By programme — stacked bar -->
            <section class="bg-white border border-slate-200 rounded-lg p-5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Resolved within 90 days by programme</h2>
                    <div class="flex rounded border border-slate-300 overflow-hidden text-[10px]">
                        <button type="button" :class="['px-2 py-1', progViewMode === 'count' ? 'bg-slate-900 text-white' : 'text-slate-600']" @click="progViewMode = 'count'">Count</button>
                        <button type="button" :class="['px-2 py-1', progViewMode === 'pct' ? 'bg-slate-900 text-white' : 'text-slate-600']" @click="progViewMode = 'pct'">%</button>
                    </div>
                </div>
                <div class="flex items-end gap-6 h-48">
                    <div v-for="(q, qi) in ['Q1','Q2','Q3','Q4']" :key="q" class="flex-1 flex flex-col items-center gap-1">
                        <div class="flex flex-col-reverse h-40 w-12 overflow-hidden rounded-t">
                            <template v-for="(s, si) in (data.by_programme || [])" :key="s.name">
                                <div
                                    :style="{
                                        height: (() => {
                                            const d = s.data.find(r => r.quarter === q);
                                            const val = d?.resolved_within_sla ?? 0;
                                            if (progViewMode === 'pct') {
                                                const qTotal = (data.by_programme || []).reduce((a, x) => a + (x.data.find(r => r.quarter === q)?.resolved_within_sla ?? 0), 0);
                                                return qTotal > 0 ? (val / qTotal * 100) + '%' : '0%';
                                            }
                                            return (val / maxTotal * 100) + '%';
                                        })(),
                                        backgroundColor: COLOURS[si % COLOURS.length],
                                    }"
                                    class="transition-all"
                                    :title="`${s.name}: ${s.data.find(r => r.quarter === q)?.resolved_within_sla ?? 0}`"
                                />
                            </template>
                        </div>
                        <div class="text-xs text-slate-600 font-medium">{{ q }}</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 justify-center mt-3">
                    <span v-for="(s, si) in (data.by_programme || [])" :key="s.name" class="flex items-center gap-1 text-[10px] text-slate-600">
                        <span class="inline-block h-2 w-4 rounded" :style="{ backgroundColor: COLOURS[si % COLOURS.length] }" />
                        {{ s.name }}
                    </span>
                </div>
            </section>

            <!-- Chart 3: By classification — horizontal bars -->
            <section class="bg-white border border-slate-200 rounded-lg p-5 mb-6">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-1">Resolution rate by sub-classification</h2>
                <p class="text-[10px] text-slate-500 mb-4">Filtered by: {{ quarterFilter === 'all' ? 'All quarters' : quarterFilter }}</p>
                <div v-if="filteredClassification.length === 0" class="text-sm text-slate-400 italic py-4">No sub-classification data.</div>
                <div v-else class="space-y-2">
                    <div v-for="c in filteredClassification" :key="c.name" class="flex items-center gap-3">
                        <div class="w-36 text-xs text-slate-700 truncate text-right" :title="c.name">{{ c.name }}</div>
                        <div class="flex-1 h-6 bg-slate-100 rounded overflow-hidden relative">
                            <div class="h-full rounded transition-all" :style="{ width: c.rate + '%', backgroundColor: barColour(c.rate) }" />
                            <span class="absolute right-2 top-0.5 text-[10px] font-semibold" :style="{ color: c.rate > 50 ? '#fff' : '#334155' }">{{ c.rate }}%</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Summary table -->
            <section class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-600 bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Quarter</th>
                            <th class="px-4 py-3">Date range</th>
                            <th class="px-4 py-3 text-right">Registered</th>
                            <th class="px-4 py-3 text-right">Resolved ≤90d</th>
                            <th class="px-4 py-3 text-right">Open / within SLA</th>
                            <th class="px-4 py-3 text-right">Breached</th>
                            <th class="px-4 py-3 text-right">Resolution rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="q in overall" :key="q.quarter" class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ q.quarter_label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ q.date_range }}</td>
                            <td class="px-4 py-3 text-right text-slate-900">{{ q.total }}</td>
                            <td class="px-4 py-3 text-right text-green-700">{{ q.resolved_within_sla }}</td>
                            <td class="px-4 py-3 text-right text-amber-700">{{ q.within_window ?? 0 }}</td>
                            <td class="px-4 py-3 text-right text-red-700">{{ q.breached ?? 0 }}</td>
                            <td :class="['px-4 py-3 text-right', rateClass(q.resolution_rate)]">{{ q.resolution_rate }}%</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="2">Total</td>
                            <td class="px-4 py-3 text-right">{{ totalsRow.total }}</td>
                            <td class="px-4 py-3 text-right text-green-700">{{ totalsRow.resolved }}</td>
                            <td class="px-4 py-3 text-right text-amber-700">{{ totalsRow.withinWindow }}</td>
                            <td class="px-4 py-3 text-right text-red-700">{{ totalsRow.breached }}</td>
                            <td :class="['px-4 py-3 text-right', rateClass(totalsRow.rate)]">{{ totalsRow.rate }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        </template>
    </AppLayout>
</template>
