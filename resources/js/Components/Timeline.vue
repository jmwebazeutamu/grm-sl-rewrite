<script setup lang="ts">
interface TimelineItem {
    kind: 'submitted' | 'state' | 'action' | 'feedback';
    occurred_at: string;
    actor: { id: number; name: string } | null;
    data: Record<string, unknown>;
}

defineProps<{ items: TimelineItem[] }>();

function relative(iso: string): string {
    const d = new Date(iso);
    const mins = Math.floor((Date.now() - d.getTime()) / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    if (mins < 1440) return `${Math.floor(mins / 60)}h ago`;
    return d.toLocaleDateString();
}

function stateHeadline(item: TimelineItem): string {
    const actor = item.actor?.name ?? 'System';
    const to = (item.data as any).to;
    if (to === 'resolved') return `Marked resolved by ${actor}`;
    if (to === 'under_admin_review') return `Picked up for closure review by ${actor}`;
    if (to === 'closed') return `Closed by ${actor}`;
    if (to === 'reopened') return `Escalated and reopened by ${actor}`;
    return actor;
}

function stateSubline(item: TimelineItem): string {
    const to = (item.data as any).to;
    if (['resolved', 'under_admin_review', 'closed', 'reopened'].includes(to)) {
        return '';
    }
    return 'Status changed to';
}
</script>

<template>
    <ol class="relative border-l-2 border-slate-200 ml-3 space-y-4">
        <li v-for="(item, i) in items" :key="i" class="pl-6 relative">
            <span
                class="absolute -left-[9px] top-1 h-4 w-4 rounded-full border-2 border-white"
                :class="{
                    'bg-sky-500': item.kind === 'submitted',
                    'bg-indigo-500': item.kind === 'state' && !(item.data as any).assigned_to,
                    'bg-amber-500': item.kind === 'state' && (item.data as any).assigned_to,
                    'bg-slate-500': item.kind === 'action' && !(item.data as any).assigned_to,
                    'bg-violet-500': item.kind === 'action' && !!(item.data as any).assigned_to,
                    'bg-emerald-500': item.kind === 'feedback',
                }"
            />

            <div v-if="item.kind === 'submitted'" class="text-sm">
                <span class="font-medium text-slate-900">Submitted</span>
                <span class="text-slate-500"> · {{ relative(item.occurred_at) }}</span>
            </div>

            <!-- State change — standout card with indigo theme -->
            <div v-else-if="item.kind === 'state'" class="bg-indigo-50 border border-indigo-200 rounded p-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-indigo-100 text-indigo-600 text-xs">↗</span>
                        <span class="font-medium text-indigo-900">{{ stateHeadline(item) }}</span>
                    </div>
                    <span class="text-xs text-indigo-500">{{ relative(item.occurred_at) }}</span>
                </div>
                <div class="mt-2 flex items-center gap-2 flex-wrap">
                    <template v-if="stateSubline(item)">
                        <span class="text-sm text-indigo-700">{{ stateSubline(item) }}</span>
                        <span class="inline-block px-2.5 py-0.5 rounded-full bg-indigo-600 text-white text-xs font-semibold">
                            {{ (item.data as any).to_label }}
                        </span>
                    </template>
                    <span
                        v-if="(item.data as any).outcome"
                        :class="[
                            'inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold',
                            (item.data as any).outcome === 'satisfied' ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white',
                        ]"
                    >
                        {{ (item.data as any).outcome === 'satisfied' ? 'Complainant satisfied' : 'Complainant dissatisfied' }}
                    </span>
                </div>
                <p v-if="(item.data as any).note" class="mt-2 text-sm text-indigo-800 italic border-t border-indigo-100 pt-2">
                    "{{ (item.data as any).note }}"
                </p>
            </div>

            <!-- Action: regular -->
            <div
                v-else-if="item.kind === 'action' && !(item.data as any).assigned_to"
                class="bg-white border border-slate-200 rounded p-3 shadow-sm"
            >
                <div class="flex items-baseline justify-between">
                    <div>
                        <span class="font-medium text-slate-900">{{ item.actor?.name ?? 'System' }}</span>
                        <span class="ml-2 inline-block px-2 py-0.5 rounded bg-slate-100 text-xs text-slate-700">
                            {{ (item.data as any).type_label }}
                        </span>
                    </div>
                    <span class="text-xs text-slate-500">{{ relative(item.occurred_at) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-800 whitespace-pre-wrap">{{ (item.data as any).body }}</p>
            </div>

            <!-- Action: assignment (officer assigned — standout card) -->
            <div
                v-else-if="item.kind === 'action' && !!(item.data as any).assigned_to"
                class="bg-violet-50 border border-violet-200 rounded p-3 shadow-sm"
            >
                <div class="flex items-baseline justify-between">
                    <div>
                        <span class="font-medium text-violet-900">{{ item.actor?.name ?? 'System' }}</span>
                        <span class="ml-2 inline-block px-2 py-0.5 rounded bg-violet-100 text-xs text-violet-700">
                            {{ (item.data as any).type_label }}
                        </span>
                    </div>
                    <span class="text-xs text-violet-500">{{ relative(item.occurred_at) }}</span>
                </div>
                <p class="mt-2 text-sm text-violet-900 whitespace-pre-wrap">{{ (item.data as any).body }}</p>
                <div class="mt-2 flex items-center gap-2 text-sm font-medium text-violet-800">
                    <span class="inline-block h-5 w-5 rounded-full bg-violet-200 text-violet-700 text-xs flex items-center justify-center">👤</span>
                    Assigned to <strong>{{ (item.data as any).assigned_to }}</strong>
                </div>
            </div>

            <div v-else-if="item.kind === 'feedback'" class="bg-emerald-50 border border-emerald-200 rounded p-3">
                <div class="flex items-baseline justify-between">
                    <span class="font-medium text-emerald-900">
                        Complainant feedback · {{ (item.data as any).rating_label }}
                    </span>
                    <span class="text-xs text-emerald-700">{{ relative(item.occurred_at) }}</span>
                </div>
                <p v-if="(item.data as any).comment" class="mt-2 text-sm text-emerald-900 italic">
                    "{{ (item.data as any).comment }}"
                </p>
            </div>
        </li>
    </ol>
</template>
