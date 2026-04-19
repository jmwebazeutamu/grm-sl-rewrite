<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    districts: { type: Array, default: () => [] },
    programmes: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    grievanceTypes: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    savedReports: { type: Array, default: () => [] },
    fieldLabels: { type: Object, default: () => ({}) },
    validFields: { type: Array, default: () => [] },
});

const FIELD_GROUPS = {
    'Grievance': ['ref', 'summary', 'status', 'grievance_type', 'org_classification', 'programme', 'is_anonymous', 'submitted_at', 'received_at', 'resolved_at', 'closed_at'],
    'Location': ['region', 'district', 'chiefdom'],
    'People & Organisation': ['complainant_name', 'implementing_org', 'assigned_org', 'assigned_officer'],
    'Performance': ['days_open', 'days_to_resolution'],
};

const FILTER_OPERATORS = {
    submitted_at: ['=', '>', '<', '>=', '<=', 'between'],
    summary: ['contains'],
    status: ['=', 'in'],
    district: ['=', 'in'],
    programme: ['=', 'in'],
    assigned_org: ['=', 'in'],
    grievance_type: ['=', 'in'],
    days_open: ['>', '>=', '<', '<=', 'between'],
    days_to_resolution: ['>'],
};

const FILTER_FIELDS = Object.keys(FILTER_OPERATORS);

const selectedFields = ref([]);
const filters = ref([]);
const results = ref(null);
const loading = ref(false);
const error = ref('');
const saveReportName = ref('');
const showSaveForm = ref(false);

function toggleField(f) {
    const i = selectedFields.value.indexOf(f);
    if (i >= 0) selectedFields.value.splice(i, 1);
    else selectedFields.value.push(f);
}

function selectAllGroup(group) {
    group.forEach((f) => {
        if (!selectedFields.value.includes(f)) selectedFields.value.push(f);
    });
}

function clearGroup(group) {
    selectedFields.value = selectedFields.value.filter((f) => !group.includes(f));
}

function addFilter() {
    const field = FILTER_FIELDS[0];
    filters.value.push({
        field,
        operator: FILTER_OPERATORS[field][0],
        value: null,
    });
}

function removeFilter(i) {
    filters.value.splice(i, 1);
}

function onFilterFieldChange(i) {
    const f = filters.value[i];
    const ops = FILTER_OPERATORS[f.field] || ['='];
    f.operator = ops[0];
    f.value = null;
}

function operatorsFor(field) {
    return FILTER_OPERATORS[field] || ['='];
}

function isMultiSelect(filter) {
    return filter.operator === 'in';
}

function isBetween(filter) {
    return filter.operator === 'between';
}

function isDateField(field) {
    return ['submitted_at'].includes(field);
}

function isNumericField(field) {
    return ['days_open', 'days_to_resolution'].includes(field);
}

function isSelectField(field) {
    return ['status', 'district', 'programme', 'assigned_org', 'grievance_type'].includes(field);
}

function optionsFor(field) {
    switch (field) {
        case 'status': return props.states.map((s) => ({ id: s.value, name: s.label }));
        case 'district': return props.districts;
        case 'programme': return props.programmes;
        case 'assigned_org': return props.organizations;
        case 'grievance_type': return props.grievanceTypes;
        default: return [];
    }
}

function payload() {
    return {
        fields: selectedFields.value,
        filters: filters.value
            .filter((f) => f.value !== null && f.value !== '' && f.value !== '__all__')
            .map((f) => ({
                field: f.field,
                operator: f.operator,
                value: f.value,
            })),
    };
}

async function runPreview() {
    if (selectedFields.value.length === 0) {
        error.value = 'Select at least one column.';
        return;
    }
    error.value = '';
    loading.value = true;
    results.value = null;

    try {
        const res = await axios.post(route('admin.reports.preview'), payload(), {
            headers: { Accept: 'application/json' },
        });
        results.value = res.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Network error.';
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    if (selectedFields.value.length === 0) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('admin.reports.export');
    form.style.display = 'none';

    const addHidden = (name, val) => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = name;
        inp.value = val;
        form.appendChild(inp);
    };

    addHidden('_token', csrf);
    selectedFields.value.forEach((f, i) => addHidden(`fields[${i}]`, f));
    filters.value.forEach((f, i) => {
        addHidden(`filters[${i}][field]`, f.field);
        addHidden(`filters[${i}][operator]`, f.operator);
        if (Array.isArray(f.value)) {
            f.value.forEach((v, j) => addHidden(`filters[${i}][value][${j}]`, v));
        } else {
            addHidden(`filters[${i}][value]`, f.value ?? '');
        }
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function saveReport() {
    if (!saveReportName.value.trim()) return;
    router.post(route('admin.reports.saved.store'), {
        name: saveReportName.value.trim(),
        ...payload(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            saveReportName.value = '';
            showSaveForm.value = false;
        },
    });
}

function loadSavedReport(report, run = false) {
    selectedFields.value = [...(report.fields || [])];
    filters.value = (report.filters || []).map((f) => ({ ...f }));
    if (run) runPreview();
}

function deleteSavedReport(report) {
    if (!confirm(`Delete saved report "${report.name}"?`)) return;
    router.delete(route('admin.reports.saved.destroy', report.id), { preserveScroll: true });
}

function slaBorderClass(row) {
    const d = Number(row.days_open ?? 0);
    if (d >= 30) return 'border-l-4 border-red-500 bg-red-50';
    if (d >= 24) return 'border-l-4 border-amber-400 bg-amber-50';
    return '';
}

const DATE_FIELDS = ['submitted_at', 'received_at', 'resolved_at', 'closed_at'];

function formatCell(field, value) {
    if (value == null || value === '') return '—';
    if (DATE_FIELDS.includes(field)) {
        const d = new Date(value);
        return isNaN(d.getTime()) ? value : d.toLocaleDateString();
    }
    return value;
}

const showDaysOpen = computed(() => selectedFields.value.includes('days_open'));
</script>

<template>
    <Head title="Reports" />
    <AppLayout>
        <div class="flex gap-6 min-h-[calc(100vh-8rem)]">
            <!-- LEFT SIDEBAR — Query builder -->
            <aside class="w-80 flex-none space-y-5 overflow-y-auto">
                <!-- Fields -->
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Columns to include</h2>
                    <div v-for="(fields, group) in FIELD_GROUPS" :key="group" class="mb-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-slate-500 uppercase">{{ group }}</span>
                            <span class="space-x-2 text-[10px]">
                                <button type="button" class="text-indigo-600 hover:underline" @click="selectAllGroup(fields)">All</button>
                                <button type="button" class="text-slate-500 hover:underline" @click="clearGroup(fields)">Clear</button>
                            </span>
                        </div>
                        <label v-for="f in fields" :key="f" class="flex items-center gap-2 text-sm py-0.5 cursor-pointer">
                            <input
                                type="checkbox"
                                :checked="selectedFields.includes(f)"
                                class="rounded border-slate-300"
                                @change="toggleField(f)"
                            />
                            {{ fieldLabels[f] || f }}
                        </label>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Filters</h2>
                        <button type="button" class="text-xs text-indigo-600 hover:underline" @click="addFilter">+ Add filter</button>
                    </div>
                    <div v-for="(f, i) in filters" :key="i" class="flex flex-col gap-1 mb-3 p-2 border border-slate-100 rounded bg-slate-50">
                        <div class="flex items-center gap-1">
                            <select v-model="f.field" class="flex-1 rounded border-slate-300 text-xs" @change="onFilterFieldChange(i)">
                                <option v-for="ff in FILTER_FIELDS" :key="ff" :value="ff">{{ fieldLabels[ff] || ff }}</option>
                            </select>
                            <select v-model="f.operator" class="w-16 rounded border-slate-300 text-xs">
                                <option v-for="op in operatorsFor(f.field)" :key="op" :value="op">{{ op }}</option>
                            </select>
                            <button type="button" class="text-slate-400 hover:text-rose-600 text-xs px-1" @click="removeFilter(i)">×</button>
                        </div>
                        <div>
                            <template v-if="isBetween(f)">
                                <div class="flex gap-1">
                                    <input
                                        :type="isDateField(f.field) ? 'date' : 'number'"
                                        :value="Array.isArray(f.value) ? f.value[0] : ''"
                                        class="flex-1 rounded border-slate-300 text-xs"
                                        @input="(e) => { if (!Array.isArray(f.value)) f.value = [null, null]; f.value[0] = e.target.value; }"
                                    />
                                    <input
                                        :type="isDateField(f.field) ? 'date' : 'number'"
                                        :value="Array.isArray(f.value) ? f.value[1] : ''"
                                        class="flex-1 rounded border-slate-300 text-xs"
                                        @input="(e) => { if (!Array.isArray(f.value)) f.value = [null, null]; f.value[1] = e.target.value; }"
                                    />
                                </div>
                            </template>
                            <template v-else-if="isMultiSelect(f)">
                                <select
                                    multiple
                                    :value="Array.isArray(f.value) ? f.value : []"
                                    class="w-full rounded border-slate-300 text-xs h-20"
                                    @change="(e) => f.value = Array.from(e.target.selectedOptions).map((o) => o.value)"
                                >
                                    <option v-for="opt in optionsFor(f.field)" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                </select>
                            </template>
                            <template v-else-if="isSelectField(f.field)">
                                <select v-model="f.value" class="w-full rounded border-slate-300 text-xs">
                                    <option value="__all__">All</option>
                                    <option v-for="opt in optionsFor(f.field)" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                </select>
                            </template>
                            <template v-else-if="isDateField(f.field)">
                                <input v-model="f.value" type="date" class="w-full rounded border-slate-300 text-xs" />
                            </template>
                            <template v-else-if="isNumericField(f.field)">
                                <input v-model.number="f.value" type="number" class="w-full rounded border-slate-300 text-xs" />
                            </template>
                            <template v-else>
                                <input v-model="f.value" type="text" placeholder="Search…" class="w-full rounded border-slate-300 text-xs" />
                            </template>
                        </div>
                    </div>
                    <p v-if="filters.length === 0" class="text-xs text-slate-400 italic">No filters yet.</p>
                </div>

                <!-- Actions -->
                <div class="space-y-2">
                    <button
                        type="button"
                        :disabled="selectedFields.length === 0 || loading"
                        class="w-full px-3 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50"
                        @click="runPreview"
                    >
                        {{ loading ? 'Running…' : 'Preview report' }}
                    </button>
                    <button
                        type="button"
                        :disabled="selectedFields.length === 0"
                        class="w-full px-3 py-2 rounded border border-slate-300 text-sm text-slate-700 disabled:opacity-50"
                        @click="exportCsv"
                    >
                        Export CSV
                    </button>
                    <button
                        type="button"
                        :disabled="selectedFields.length === 0"
                        class="w-full px-3 py-2 text-xs text-slate-600 hover:text-slate-900 underline text-left"
                        @click="showSaveForm = !showSaveForm"
                    >
                        {{ showSaveForm ? 'Cancel' : 'Save this report' }}
                    </button>
                    <div v-if="showSaveForm" class="flex gap-2">
                        <input v-model="saveReportName" type="text" placeholder="Report name…" maxlength="200" class="flex-1 rounded border-slate-300 text-xs" />
                        <button type="button" class="px-2 py-1 rounded bg-slate-900 text-white text-xs" @click="saveReport">Save</button>
                    </div>
                </div>

                <!-- Saved reports -->
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Saved reports</h2>
                    <p v-if="savedReports.length === 0" class="text-xs text-slate-400 italic">No saved reports yet.</p>
                    <ul v-else class="space-y-1">
                        <li v-for="sr in savedReports" :key="sr.id" class="flex items-center gap-2 text-xs">
                            <button type="button" class="flex-1 text-left text-slate-700 hover:text-indigo-700 truncate" @click="loadSavedReport(sr)">
                                {{ sr.name }}
                            </button>
                            <button type="button" class="text-indigo-600 hover:underline" @click="loadSavedReport(sr, true)">Run</button>
                            <button type="button" class="text-rose-500 hover:text-rose-700" @click="deleteSavedReport(sr)">×</button>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- MAIN PANEL — Results -->
            <main class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold text-slate-900 mb-4">Reports</h1>

                <div v-if="error" class="mb-4 rounded bg-rose-50 border border-rose-200 px-4 py-2 text-sm text-rose-800">{{ error }}</div>

                <div v-if="loading" class="flex items-center justify-center py-20 text-slate-500">
                    <span class="inline-block h-5 w-5 rounded-full border-2 border-slate-400 border-t-transparent animate-spin mr-2" />
                    Running report…
                </div>

                <div v-else-if="results" class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
                    <div class="px-4 py-3 border-b border-slate-200 text-sm text-slate-600">
                        Showing {{ results.rows.length }} of {{ results.total }} results
                        <span v-if="results.total > 200" class="text-amber-700"> — export for full results</span>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-600 bg-slate-50">
                            <tr>
                                <th v-for="f in results.fields" :key="f" class="px-3 py-2 whitespace-nowrap">
                                    {{ fieldLabels[f] || f }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, i) in results.rows"
                                :key="i"
                                :class="['border-t border-slate-100', showDaysOpen ? slaBorderClass(row) : '']"
                            >
                                <td v-for="f in results.fields" :key="f" class="px-3 py-2 whitespace-nowrap">
                                    {{ formatCell(f, row[f]) }}
                                </td>
                            </tr>
                            <tr v-if="results.rows.length === 0">
                                <td :colspan="results.fields.length" class="px-4 py-6 text-center text-slate-500">
                                    No results match the current filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="flex items-center justify-center py-20 text-slate-400 text-sm">
                    Configure your report on the left and click Preview.
                </div>
            </main>
        </div>
    </AppLayout>
</template>
