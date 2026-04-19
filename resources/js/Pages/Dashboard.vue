<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    widgets: { type: Object, required: true },
    organization: { type: Object, default: null },
    my_assigned: { type: Number, default: 0 },
});

const page = usePage();
const roles = computed(() => page.props.auth.roles ?? []);
const permissions = computed(() => page.props.auth.permissions ?? []);
const user = computed(() => page.props.auth.user);

const hasRole = (r) => roles.value.includes(r);
const hasAnyRole = (...rs) => rs.some((r) => roles.value.includes(r));
const can = (p) => permissions.value.includes(p);

const isSuperAdmin = computed(() => hasRole('super-admin'));
const isAccReviewer = computed(() => hasRole('acc-reviewer'));
const isOfficer = computed(() => hasRole('organization-officer'));

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});

const todayLabel = computed(() =>
    new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
);

const states = computed(() => props.widgets.state_counts ?? {});
const c = (k) => states.value[k] ?? 0;

const ACTIVE_KEYS = ['submitted', 'under_review', 'accepted', 'categorized', 'assigned', 'org_classified', 'in_progress', 'escalated', 'under_admin_review', 'reopened'];
const activeTotal = computed(() => ACTIVE_KEYS.reduce((t, k) => t + c(k), 0));
const pendingReview = computed(() => c('submitted') + c('under_review'));

const resolutionRate = computed(() => props.widgets.resolution_rate);
const resolutionTone = computed(() => {
    const r = resolutionRate.value;
    if (r == null) return 'neutral';
    if (r >= 75) return 'good';
    if (r >= 50) return 'warn';
    return 'bad';
});

const STATE_LABELS = {
    submitted: 'Submitted', under_review: 'Under review', accepted: 'Accepted', categorized: 'Categorized',
    assigned: 'Assigned', org_classified: 'Org classified', in_progress: 'In progress', resolved: 'Resolved',
    closed: 'Closed', rejected: 'Rejected', trashed: 'Trashed', escalated: 'Escalated',
    under_admin_review: 'Under admin review', reopened: 'Reopened',
};

const STATE_COLORS = {
    submitted: '#3b82f6', under_review: '#3b82f6',
    in_progress: '#f59e0b', reopened: '#f59e0b', escalated: '#f59e0b', under_admin_review: '#f59e0b',
    accepted: '#8b5cf6', categorized: '#8b5cf6', assigned: '#8b5cf6', org_classified: '#8b5cf6',
    resolved: '#22c55e',
    closed: '#6b7280',
    rejected: '#ef4444', trashed: '#ef4444',
};

const stateBars = computed(() => Object.entries(states.value)
    .filter(([, n]) => n > 0)
    .map(([k, n]) => ({ key: k, label: STATE_LABELS[k] ?? k, count: n, color: STATE_COLORS[k] ?? '#94a3b8' }))
    .sort((a, b) => b.count - a.count));

const stateBarMax = computed(() => Math.max(1, ...stateBars.value.map((b) => b.count)));

const submissions = computed(() => props.widgets.submissions_per_day?.series ?? []);
const submissionDays = computed(() => props.widgets.submissions_per_day?.days ?? 30);
const submissionsTotal = computed(() => submissions.value.reduce((t, d) => t + d.count, 0));
const submissionsMax = computed(() => Math.max(1, ...submissions.value.map((d) => d.count)));

const submissionTicks = computed(() => {
    const arr = submissions.value;
    const step = Math.max(1, Math.floor(arr.length / 6));
    return arr.map((d, i) => (i % step === 0 || i === arr.length - 1 ? d.date : null));
});

function fmtDay(iso) {
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

const sla = computed(() => props.widgets.sla_buckets ?? { within: 0, approaching: 0, breached: 0 });
const slaDays = computed(() => props.widgets.sla_days ?? 90);
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <!-- Greeting -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold" style="color: var(--color-navy);">
                {{ greeting }}, {{ user?.name?.split(' ')[0] ?? 'there' }}.
            </h1>
            <p class="mt-1 text-sm" style="color: var(--color-slate);">
                <span v-if="organization" class="font-medium">{{ organization.name }}</span>
                <span v-else-if="isSuperAdmin" class="font-medium">System-wide</span>
                <span v-if="organization || isSuperAdmin"> · </span>
                {{ todayLabel }}
            </p>
        </div>

        <!-- Officer band -->
        <div v-if="isOfficer && !isSuperAdmin" class="mb-6 bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid var(--color-gold);">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold" style="color: var(--color-navy);">My assigned cases</h2>
                    <p class="text-sm mt-1" style="color: var(--color-slate);">
                        You have <strong class="text-slate-900">{{ my_assigned }}</strong> active {{ my_assigned === 1 ? 'case' : 'cases' }} assigned to you.
                    </p>
                </div>
                <Link :href="route('admin.grievances.index')" class="px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: var(--color-navy);">View my cases</Link>
            </div>
        </div>

        <!-- Row 2 — Stat cards -->
        <div v-if="can('grievance.viewAny')" class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-6">
            <div class="bg-white rounded-xl p-4" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid var(--color-navy);">
                <div class="text-2xl font-bold" style="color: var(--color-navy);">{{ activeTotal }}</div>
                <div class="text-[11px] font-semibold uppercase tracking-wider mt-1 text-slate-500">Active cases</div>
            </div>
            <div class="bg-white rounded-xl p-4" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid #3b82f6;">
                <div class="text-2xl font-bold" style="color: var(--color-navy);">{{ pendingReview }}</div>
                <div class="text-[11px] font-semibold uppercase tracking-wider mt-1 text-slate-500">Pending review</div>
            </div>
            <div class="bg-white rounded-xl p-4" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid #f59e0b;">
                <div class="text-2xl font-bold" style="color: var(--color-navy);">{{ c('in_progress') }}</div>
                <div class="text-[11px] font-semibold uppercase tracking-wider mt-1 text-slate-500">In progress</div>
            </div>
            <div class="bg-white rounded-xl p-4" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid #22c55e;">
                <div class="text-2xl font-bold" style="color: var(--color-navy);">{{ c('resolved') }}</div>
                <div class="text-[11px] font-semibold uppercase tracking-wider mt-1 text-slate-500">Resolved</div>
            </div>
            <div class="bg-white rounded-xl p-4" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid #6b7280;">
                <div class="text-2xl font-bold" style="color: var(--color-navy);">{{ c('closed') }}</div>
                <div class="text-[11px] font-semibold uppercase tracking-wider mt-1 text-slate-500">Closed</div>
            </div>
            <div class="bg-white rounded-xl p-4" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid;"
                :style="{ borderLeftColor: resolutionTone === 'good' ? '#22c55e' : resolutionTone === 'warn' ? '#f59e0b' : resolutionTone === 'bad' ? '#ef4444' : '#94a3b8' }">
                <div class="text-2xl font-bold" style="color: var(--color-navy);">
                    <template v-if="resolutionRate == null">—</template>
                    <template v-else>{{ resolutionRate }}%</template>
                </div>
                <div class="text-[11px] font-semibold uppercase tracking-wider mt-1 text-slate-500">Resolution rate</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Within {{ slaDays }}-day SLA</div>
            </div>
        </div>

        <!-- ACC intake queue -->
        <div v-if="isAccReviewer && !isSuperAdmin" class="mb-6 bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid var(--color-state-progress);">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold" style="color: var(--color-navy);">Intake queue</h2>
                    <p class="text-sm mt-1" style="color: var(--color-slate);"><strong class="text-slate-900">{{ pendingReview }}</strong> cases pending review + classification.</p>
                </div>
                <Link :href="route('admin.grievances.index') + '?filter[state]=submitted'" class="px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: var(--color-state-progress);">Open queue</Link>
            </div>
        </div>

        <!-- Row 3 — Submissions chart + by-state chart -->
        <div v-if="can('grievance.viewAny')" class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
            <!-- Submissions -->
            <section class="lg:col-span-2 bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <div class="flex items-baseline justify-between mb-4">
                    <h2 class="text-xs font-bold uppercase tracking-[0.1em]" style="color: var(--color-navy);">Submissions · last {{ submissionDays }} days</h2>
                    <span v-if="submissionsTotal > 0" class="text-xs text-slate-400">{{ submissionsTotal }} total</span>
                </div>

                <div v-if="submissionsTotal === 0" class="py-12 text-center text-sm text-slate-400">No submissions in this period.</div>

                <svg v-else :viewBox="`0 0 ${submissions.length * 12 + 20} 160`" class="w-full" preserveAspectRatio="none" style="height: 160px;">
                    <g v-for="(d, i) in submissions" :key="d.date">
                        <rect
                            :x="10 + i * 12"
                            :y="140 - (d.count / submissionsMax) * 120"
                            width="9"
                            :height="Math.max(1, (d.count / submissionsMax) * 120)"
                            fill="#0f2044"
                            rx="1.5"
                        >
                            <title>{{ d.count }} grievances · {{ fmtDay(d.date) }}</title>
                        </rect>
                    </g>
                </svg>

                <div v-if="submissionsTotal > 0" class="mt-2 flex justify-between text-[10px] text-slate-400 font-mono">
                    <span v-for="(t, i) in submissionTicks" :key="i">{{ t ? fmtDay(t) : '' }}</span>
                </div>
            </section>

            <!-- By state -->
            <section class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <h2 class="text-xs font-bold uppercase tracking-[0.1em] mb-4" style="color: var(--color-navy);">By state</h2>

                <div v-if="stateBars.length === 0" class="py-6 text-center text-sm text-slate-400">No grievances yet.</div>

                <div v-else class="space-y-2.5">
                    <div v-for="bar in stateBars" :key="bar.key" class="flex items-center gap-2 text-xs">
                        <div class="w-28 text-slate-600 truncate">{{ bar.label }}</div>
                        <div class="flex-1 relative h-6 rounded bg-slate-50 overflow-hidden">
                            <div class="absolute inset-y-0 left-0 rounded" :style="{ width: `${Math.max(4, (bar.count / stateBarMax) * 100)}%`, background: bar.color }" />
                            <div class="absolute inset-y-0 right-2 flex items-center text-[11px] font-bold text-white drop-shadow" :style="{ textShadow: '0 1px 1px rgba(0,0,0,0.35)' }">
                                {{ bar.count }}
                            </div>
                        </div>
                    </div>
                </div>

                <Link :href="route('admin.reports.index')" class="mt-4 inline-flex items-center gap-1 text-xs font-medium" style="color: var(--color-gold);">
                    Full reports
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </Link>
            </section>
        </div>

        <!-- Row 4 — SLA summary -->
        <div v-if="can('grievance.viewAny')" class="mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid #22c55e;">
                    <div class="text-2xl font-bold text-green-700">{{ sla.within }}</div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mt-1">Within SLA</div>
                    <p class="text-[11px] text-slate-400 mt-1">Active cases inside SLA window</p>
                </div>
                <div class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid #f59e0b;">
                    <div class="text-2xl font-bold text-amber-600">{{ sla.approaching }}</div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mt-1">Approaching SLA</div>
                    <p class="text-[11px] text-slate-400 mt-1">Active cases nearing breach</p>
                </div>
                <div class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-left: 4px solid #ef4444;">
                    <div class="text-2xl font-bold text-red-600">{{ sla.breached }}</div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mt-1">Breached SLA</div>
                    <p class="text-[11px] text-slate-400 mt-1">Active cases past SLA</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">SLA period: {{ slaDays }} days default.</p>
        </div>

        <!-- Row 5 — Quick actions -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link v-if="can('grievance.viewAny')" :href="route('admin.grievances.index')" class="group block bg-white rounded-xl p-5 transition hover:shadow-md" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-lg flex items-center justify-center" style="background: var(--color-gold); color: var(--color-navy);">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color: var(--color-navy);">View grievances</h3>
                        <p class="text-xs" style="color: var(--color-slate);">Browse, filter, and manage cases.</p>
                    </div>
                </div>
            </Link>
            <Link v-if="can('user.create')" :href="route('admin.org.users.create')" class="group block bg-white rounded-xl p-5 transition hover:shadow-md" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-lg flex items-center justify-center" style="background: var(--color-navy); color: var(--color-gold);">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color: var(--color-navy);">Add user</h3>
                        <p class="text-xs" style="color: var(--color-slate);">Invite by email or create with password.</p>
                    </div>
                </div>
            </Link>
            <a href="/submit-grievance" class="group block bg-white rounded-xl p-5 transition hover:shadow-md" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-lg flex items-center justify-center" style="background: var(--color-state-resolved); color: #fff;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color: var(--color-navy);">Public intake form</h3>
                        <p class="text-xs" style="color: var(--color-slate);">Submit a new grievance on behalf of a complainant.</p>
                    </div>
                </div>
            </a>
        </div>
    </AppLayout>
</template>
