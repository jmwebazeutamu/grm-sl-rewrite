<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface AuditEntry {
    id: number;
    action: string;
    actor: { id: number; name: string } | null;
    subject_type: string | null;
    subject_id: number | null;
    payload: Record<string, unknown> | null;
    ip_address: string | null;
    occurred_at: string;
}

const props = defineProps<{
    entries: { data: AuditEntry[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    filters: { filter?: Record<string, string> };
    known_actions: string[];
}>();

const actionFilter = ref(props.filters.filter?.action ?? '');
const fromFilter = ref(props.filters.filter?.from ?? '');
const toFilter = ref(props.filters.filter?.to ?? '');

function applyFilters(): void {
    router.get(
        route('admin.audit.index'),
        {
            filter: {
                action: actionFilter.value || undefined,
                from: fromFilter.value || undefined,
                to: toFilter.value || undefined,
            },
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Audit log" />
    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-4">Audit log</h1>

        <div class="flex gap-3 mb-4">
            <select v-model="actionFilter" class="rounded border-slate-300 text-sm" @change="applyFilters">
                <option value="">All actions</option>
                <option v-for="a in known_actions" :key="a" :value="a">{{ a }}</option>
            </select>
            <input v-model="fromFilter" type="date" class="rounded border-slate-300 text-sm" @change="applyFilters" />
            <input v-model="toFilter" type="date" class="rounded border-slate-300 text-sm" @change="applyFilters" />
        </div>

        <table class="w-full bg-white rounded shadow-sm">
            <thead class="text-left text-sm text-slate-600">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Actor</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Payload</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in entries.data" :key="entry.id" class="border-t align-top">
                    <td class="px-4 py-3 text-xs font-mono text-slate-600">
                        {{ new Date(entry.occurred_at).toLocaleString() }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-900">{{ entry.actor?.name ?? 'system' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <code class="px-1.5 py-0.5 rounded bg-slate-100 text-xs">{{ entry.action }}</code>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <template v-if="entry.subject_type">{{ entry.subject_type }}#{{ entry.subject_id }}</template>
                        <template v-else>—</template>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 font-mono max-w-md">
                        <pre class="whitespace-pre-wrap">{{ entry.payload ? JSON.stringify(entry.payload, null, 2) : '—' }}</pre>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500 font-mono">{{ entry.ip_address ?? '—' }}</td>
                </tr>
                <tr v-if="entries.data.length === 0">
                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">No entries match.</td>
                </tr>
            </tbody>
        </table>
    </AppLayout>
</template>
