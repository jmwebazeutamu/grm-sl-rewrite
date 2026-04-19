<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Timeline from '@/Components/Timeline.vue';
import ActionComposer from '@/Components/ActionComposer.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import ClosurePanel from '@/Components/ClosurePanel.vue';
import LocationPicker from '@/Components/LocationPicker.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    grievance: Object,
    timeline: Array,
    allowed_transitions: Array,
    action_types: Array,
    organizations: Array,
    acc_org_id: Number,
    org_grievance_types: Array,
    capabilities: Object,
    officers_in_org: Array,
    attachments: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
});

const editingLocation = ref(false);
const locationDraft = ref({
    region_id: props.grievance.data.location?.region_id ?? null,
    district_id: props.grievance.data.location?.district_id ?? null,
    chiefdom_id: props.grievance.data.location?.chiefdom_id ?? null,
    section_id: props.grievance.data.location?.section_id ?? null,
    locality_id: props.grievance.data.location?.locality_id ?? null,
});
const locationErrors = ref({});

function startLocationEdit() {
    locationDraft.value = {
        region_id: props.grievance.data.location?.region_id ?? null,
        district_id: props.grievance.data.location?.district_id ?? null,
        chiefdom_id: props.grievance.data.location?.chiefdom_id ?? null,
        section_id: props.grievance.data.location?.section_id ?? null,
        locality_id: props.grievance.data.location?.locality_id ?? null,
    };
    locationErrors.value = {};
    editingLocation.value = true;
}

function saveLocation() {
    router.patch(route('admin.grievances.location.update', props.grievance.data.id), locationDraft.value, {
        preserveScroll: true,
        onSuccess: () => {
            editingLocation.value = false;
        },
        onError: (errors) => {
            locationErrors.value = errors;
        },
    });
}

function cancelLocationEdit() {
    editingLocation.value = false;
    locationErrors.value = {};
}

const g = computed(() => props.grievance.data);
const state = computed(() => g.value.state.value);
const isTerminal = computed(() => g.value.state.is_terminal);
const isRejected = computed(() => state.value === 'rejected');

// Pipeline progress helpers.
const pastUnderReview = computed(() => !['submitted', 'under_review'].includes(state.value));
const pastAccepted = computed(() => !['submitted', 'under_review', 'accepted'].includes(state.value));
const pastAssigned = computed(() => !['submitted', 'under_review', 'accepted', 'categorized', 'assigned'].includes(state.value));
const reachedInProgress = computed(() => ['in_progress', 'resolved', 'under_admin_review', 'closed', 'reopened', 'escalated'].includes(state.value));
const inClosureWindow = computed(() => ['resolved', 'under_admin_review'].includes(state.value));
const orgReadOnly = computed(() => inClosureWindow.value && props.capabilities?.is_org_role);
const canAssign = computed(() => {
    const active = ['submitted', 'under_review', 'accepted', 'categorized', 'assigned', 'org_classified', 'in_progress', 'reopened'];
    return props.capabilities?.can_assign && active.includes(state.value);
});
const showClosurePanel = computed(
    () => props.capabilities?.can_admin_review && ['resolved', 'under_admin_review', 'closed', 'reopened'].includes(state.value),
);

// Forms.
const rejectForm = useForm({ reason: '' });
const categorizeForm = useForm({
    category: 'administrative',
    classified_organization_id: null,
});
const orgClassifyForm = useForm({ org_classification_id: null });
const editingOrgClassification = ref(false);
const editOrgClassForm = useForm({
    org_classification_id: props.grievance.data.org_classification?.id ?? null,
});
function startEditOrgClassification() {
    editOrgClassForm.org_classification_id = props.grievance.data.org_classification?.id ?? null;
    editingOrgClassification.value = true;
}
function saveOrgClassificationEdit() {
    editOrgClassForm.patch(route('admin.grievances.orgClassify.edit', props.grievance.data.id), {
        preserveScroll: true,
        onSuccess: () => { editingOrgClassification.value = false; },
    });
}
function cancelOrgClassificationEdit() { editingOrgClassification.value = false; }
const assignForm = useForm({ officer_id: props.grievance.data.assigned_officer_id ?? null });
const transitionForm = useForm({ state: '', note: '' });

// Org dropdown: if corruption, lock to ACC.
const filteredOrgs = computed(() => {
    if (categorizeForm.category === 'corruption') {
        return props.organizations.filter((o) => o.id === props.acc_org_id);
    }
    return props.organizations.filter((o) => o.id !== props.acc_org_id);
});

function accept() {
    useForm({}).post(route('admin.grievances.accept', g.value.id), { preserveScroll: true });
}
function reject() {
    rejectForm.post(route('admin.grievances.reject', g.value.id), { preserveScroll: true });
}
function categorize() {
    // Auto-lock to ACC if corruption.
    if (categorizeForm.category === 'corruption') {
        categorizeForm.classified_organization_id = props.acc_org_id;
    }
    categorizeForm.put(route('admin.grievances.categorize', g.value.id), { preserveScroll: true });
}
function orgClassify() {
    orgClassifyForm.put(route('admin.grievances.orgClassify', g.value.id), { preserveScroll: true });
}
function saveAssignment() {
    assignForm.post(route('admin.grievances.assign', g.value.id), { preserveScroll: true });
}
function applyTransition(target) {
    transitionForm.state = target;
    transitionForm.post(route('admin.grievances.transition', g.value.id), { preserveScroll: true });
}

const showRejectConfirm = useForm({ visible: false });
</script>

<template>
    <Head :title="g.g_number" />
    <AppLayout>
        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="font-mono text-xs font-semibold tracking-wide" style="color: var(--color-gold);">{{ g.g_number }}</p>
                <h1 class="text-2xl font-bold mt-1" style="color: var(--color-navy);">{{ g.summary }}</h1>
            </div>
            <span class="state-badge inline-block px-4 py-1.5 rounded-full text-sm font-semibold"
                :style="{
                    background: isTerminal ? '#e2e8f0' : state === 'in_progress' || state === 'reopened' ? 'var(--color-state-progress)' : state === 'resolved' ? 'var(--color-state-resolved)' : state === 'rejected' ? 'var(--color-state-rejected)' : 'var(--color-state-review)',
                    color: isTerminal ? '#475569' : '#fff',
                }"
            >
                {{ g.state.label }}
            </span>
        </div>

        <div class="space-y-6">
            <!-- ═══════════ PANEL 0: Narration (always, when description present) ═══════════ -->
            <section v-if="g.description" class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <h2 class="text-xs font-bold uppercase tracking-[0.1em] mb-3" style="color: var(--color-navy);">Narration</h2>
                <p class="text-sm text-slate-900 whitespace-pre-wrap">{{ g.description }}</p>
            </section>

            <!-- ═══════════ PANEL 1: Grievance details (always) ═══════════ -->
            <section class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <h2 class="text-xs font-bold uppercase tracking-[0.1em] mb-3" style="color: var(--color-navy);">Grievance details</h2>
                <div class="grid grid-cols-2 gap-6 text-sm">
                    <!-- Left column: grievance metadata -->
                    <dl class="space-y-3">
                        <div><dt class="text-slate-500">Type</dt><dd class="text-slate-900">{{ g.grievance_type?.name ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Priority</dt><dd class="text-slate-900">{{ g.priority?.name ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Received</dt><dd class="text-slate-900">{{ g.received_at ? new Date(g.received_at).toLocaleDateString() : '—' }}</dd></div>
                        <div>
                            <dt class="text-slate-500">Implementing Organisation</dt>
                            <dd class="text-slate-900">{{ g.implementing_organization?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Programme</dt>
                            <dd class="text-slate-900">
                                <template v-if="g.programme">
                                    {{ g.programme.name }}{{ g.programme.acronym ? ` (${g.programme.acronym})` : '' }}
                                </template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-500 flex items-center gap-2">
                                Location
                                <button
                                    v-if="capabilities.can_edit && !editingLocation"
                                    type="button"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 underline"
                                    @click="startLocationEdit"
                                >
                                    Edit
                                </button>
                            </dt>
                            <dd v-if="!editingLocation" class="text-slate-900">{{ g.location?.label ?? '—' }}</dd>
                            <dd v-else class="mt-2 space-y-3">
                                <LocationPicker
                                    :regions="regions"
                                    :model-value="locationDraft"
                                    :errors="locationErrors"
                                    @update:model-value="(v) => (locationDraft = v)"
                                />
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="px-3 py-1.5 rounded bg-slate-900 text-white text-xs"
                                        @click="saveLocation"
                                    >
                                        Save
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs text-slate-600 underline"
                                        @click="cancelLocationEdit"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </dd>
                        </div>
                    </dl>

                    <!-- Right column: complainant -->
                    <dl class="space-y-3 md:border-l md:border-slate-100 md:pl-6">
                        <div v-if="g.is_anonymous || !g.complainer">
                            <dt class="text-slate-500">Complainant</dt>
                            <dd class="text-slate-500 italic">Submitted anonymously</dd>
                        </div>
                        <template v-else>
                            <div>
                                <dt class="text-slate-500">Complainant</dt>
                                <dd class="text-slate-900">{{ g.complainer.first_name }} {{ g.complainer.last_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Email</dt>
                                <dd class="text-slate-900">{{ g.complainer.email || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Phone</dt>
                                <dd class="text-slate-900">{{ g.complainer.phone_number || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Address</dt>
                                <dd class="text-slate-900 whitespace-pre-wrap">{{ g.complainer.address || '—' }}</dd>
                            </div>
                        </template>
                    </dl>
                </div>

                <!-- Person(s) this grievance is about -->
                <div v-if="g.suspects && g.suspects.length" class="mt-4 border-t pt-3">
                    <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">
                        Person(s) this grievance is about
                    </h3>
                    <ul class="divide-y divide-slate-100 border border-slate-200 rounded">
                        <li v-for="s in g.suspects" :key="s.id" class="px-3 py-2 text-sm space-y-1">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <dt class="text-slate-500 text-xs">Name</dt>
                                    <dd class="text-slate-900 flex items-center gap-2">
                                        <span>{{ [s.first_name, s.last_name].filter(Boolean).join(' ') || '—' }}</span>
                                        <span
                                            v-if="s.is_beneficiary"
                                            class="inline-block px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-semibold uppercase tracking-wide"
                                        >
                                            Beneficiary
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 text-xs">Title / role</dt>
                                    <dd class="text-slate-900">{{ s.title || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 text-xs">Phone</dt>
                                    <dd class="text-slate-900">{{ s.phone_number || '—' }}</dd>
                                </div>
                            </div>
                            <div v-if="s.is_beneficiary" class="pt-1 text-xs text-slate-600 flex flex-wrap gap-x-4">
                                <div v-if="s.beneficiary_id_number">
                                    <span class="text-slate-500">Beneficiary ID:</span>
                                    <span class="text-slate-900 font-mono ml-1">{{ s.beneficiary_id_number }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Project:</span>
                                    <span class="text-slate-900 ml-1">{{ s.programme?.name ?? 'Other' }}</span>
                                </div>
                                <div v-if="s.implementing_organization">
                                    <span class="text-slate-500">Implementing org:</span>
                                    <span class="text-slate-900 ml-1">{{ s.implementing_organization.name }}</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

            </section>

            <!-- ═══════════ PANEL 2: Review (accept/reject) ═══════════ -->
            <section v-if="!isRejected || ['submitted', 'under_review'].includes(state)" class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                <h2 class="text-xs font-bold uppercase tracking-[0.1em] mb-3" style="color: var(--color-navy);">Review</h2>

                <!-- Form: visible only in submitted/under_review -->
                <div v-if="['submitted', 'under_review'].includes(state) && capabilities.can_review">
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="px-4 py-2 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700"
                            @click="accept"
                        >
                            Accept
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded bg-rose-600 text-white text-sm hover:bg-rose-700"
                            @click="showRejectConfirm.visible = true"
                        >
                            Reject
                        </button>
                    </div>
                    <!-- Reject confirmation -->
                    <div v-if="showRejectConfirm.visible" class="mt-3 p-3 bg-rose-50 border border-rose-200 rounded">
                        <label class="block text-sm font-medium text-rose-900">Reason for rejection</label>
                        <textarea v-model="rejectForm.reason" rows="2" required maxlength="2000" class="mt-1 block w-full rounded border-rose-300 text-sm" />
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="px-3 py-1.5 rounded bg-rose-700 text-white text-sm" @click="reject">Confirm rejection</button>
                            <button type="button" class="px-3 py-1.5 rounded border border-slate-300 text-sm" @click="showRejectConfirm.visible = false">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Read-only stamp: after review -->
                <div v-else-if="pastUnderReview && !isRejected" class="flex items-center gap-2 text-sm text-emerald-800">
                    <span class="text-emerald-600">✓</span>
                    Accepted{{ g.reviewed_by ? ` by ${g.reviewed_by.name}` : '' }}{{ g.accepted_at ? ` on ${new Date(g.accepted_at).toLocaleDateString()}` : '' }}
                </div>
            </section>

            <!-- Rejection stamp — if rejected, show reason + stop -->
            <section v-if="isRejected" class="bg-rose-50 border border-rose-200 rounded-lg p-5">
                <h2 class="text-sm font-semibold text-rose-800 uppercase tracking-wide mb-2">Rejected</h2>
                <p class="text-sm text-rose-900">{{ g.review_comment ?? 'No reason provided.' }}</p>
                <p v-if="g.reviewed_by" class="mt-1 text-xs text-rose-700">
                    By {{ g.reviewed_by.name }}{{ g.received_at ? ` on ${new Date(g.received_at).toLocaleDateString()}` : '' }}
                </p>
            </section>

            <!-- Files panel is also shown for rejected cases (evidence stays visible) -->
            <AttachmentsPanel
                v-if="isRejected"
                :grievance-id="g.id"
                :attachments="attachments"
                :can-upload="capabilities.can_upload_attachment"
                :can-delete="capabilities.can_delete_attachment"
            />

            <!-- ═══════════ PANEL 3: Categorization ═══════════ -->
            <template v-if="!isRejected">
                <section v-if="state === 'accepted' && capabilities.can_classify" class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <h2 class="text-xs font-bold uppercase tracking-[0.1em] mb-3" style="color: var(--color-navy);">Categorization</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="categorizeForm.category" type="radio" value="corruption" class="border-slate-300" />
                                    Corruption
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="categorizeForm.category" type="radio" value="administrative" class="border-slate-300" />
                                    Administrative
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Assign to organization</label>
                            <p v-if="categorizeForm.category === 'corruption'" class="text-xs text-amber-700 mb-1">
                                Corruption cases are always assigned to the Anti-Corruption Commission.
                            </p>
                            <select v-model="categorizeForm.classified_organization_id" required class="w-full rounded border-slate-300 text-sm">
                                <option :value="null">— Select organization —</option>
                                <option v-for="o in filteredOrgs" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                            <span v-if="categorizeForm.errors.classified_organization_id" class="text-sm text-rose-600">{{ categorizeForm.errors.classified_organization_id }}</span>
                        </div>

                        <button
                            type="button"
                            :disabled="!categorizeForm.classified_organization_id || categorizeForm.processing"
                            class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50"
                            @click="categorize"
                        >
                            Confirm categorization
                        </button>
                    </div>
                </section>

                <!-- Locked categorization display -->
                <section v-else-if="pastAccepted && g.category" class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                        Categorization
                        <span class="text-xs text-slate-400">🔒 Locked</span>
                    </h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-slate-500">Category</dt><dd class="text-slate-900 capitalize">{{ g.category }}</dd></div>
                        <div><dt class="text-slate-500">Assigned to</dt><dd class="text-slate-900">{{ g.classified_organization?.name ?? '—' }}</dd></div>
                    </dl>
                    <p class="mt-2 text-xs text-slate-500">
                        Set{{ g.categorized_at ? ` on ${new Date(g.categorized_at).toLocaleDateString()}` : '' }} — cannot be changed.
                    </p>
                </section>

                <!-- ═══════════ PANEL 4: Org sub-classification ═══════════ -->
                <section v-if="state === 'assigned' && capabilities.can_org_classify" class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <h2 class="text-xs font-bold uppercase tracking-[0.1em] mb-3" style="color: var(--color-navy);">Organization sub-classification</h2>

                    <div v-if="org_grievance_types.length === 0" class="text-sm text-amber-700 bg-amber-50 rounded p-3">
                        Your organisation has no sub-classifications configured. Ask your org admin to add them under <strong>Configuration → Sub-classifications</strong>.
                    </div>
                    <div v-else class="space-y-3">
                        <select v-model="orgClassifyForm.org_classification_id" required class="w-full rounded border-slate-300 text-sm">
                            <option :value="null">— Select type —</option>
                            <option v-for="t in org_grievance_types" :key="t.id" :value="t.id">{{ t.label }}</option>
                        </select>
                        <button
                            type="button"
                            :disabled="!orgClassifyForm.org_classification_id || orgClassifyForm.processing"
                            class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50"
                            @click="orgClassify"
                        >
                            Classify and begin work
                        </button>
                    </div>
                </section>

                <!-- Locked or editable org classification display -->
                <section v-else-if="pastAssigned && g.org_classification" class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                        Org sub-classification
                        <span v-if="!capabilities.can_edit_org_classification" class="text-xs text-slate-400">🔒</span>
                        <button
                            v-else-if="!editingOrgClassification"
                            type="button"
                            class="text-xs text-indigo-600 hover:text-indigo-800 underline"
                            @click="startEditOrgClassification"
                        >
                            Edit
                        </button>
                    </h2>
                    <p v-if="!editingOrgClassification" class="text-sm text-slate-900">{{ g.org_classification.label }}</p>
                    <div v-else class="space-y-3">
                        <select v-model="editOrgClassForm.org_classification_id" required class="w-full rounded border-slate-300 text-sm">
                            <option :value="null">— Select type —</option>
                            <option v-for="t in org_grievance_types" :key="t.id" :value="t.id">{{ t.label }}</option>
                        </select>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                :disabled="!editOrgClassForm.org_classification_id || editOrgClassForm.processing"
                                class="px-3 py-1.5 rounded bg-slate-900 text-white text-xs disabled:opacity-50"
                                @click="saveOrgClassificationEdit"
                            >
                                Save
                            </button>
                            <button type="button" class="text-xs text-slate-600 underline" @click="cancelOrgClassificationEdit">
                                Cancel
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ═══════════ PANEL 4.5: Files & Documents (always visible) ═══════════ -->
                <AttachmentsPanel
                    :grievance-id="g.id"
                    :attachments="attachments"
                    :can-upload="capabilities.can_upload_attachment"
                    :can-delete="capabilities.can_delete_attachment"
                />

                <!-- ═══════════ PANEL 5: Actions + Timeline (in_progress+) ═══════════ -->
                <template v-if="reachedInProgress">
                    <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
                        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Timeline</h2>
                        <Timeline :items="timeline" />
                    </section>

                    <div v-if="orgReadOnly" class="bg-amber-50 border border-amber-300 rounded-lg p-4 text-sm text-amber-900">
                        <strong class="block mb-1">Awaiting admin closure review</strong>
                        This grievance has been marked resolved and is pending admin closure review.
                        No further updates are permitted until the admin closes or escalates the case.
                    </div>

                    <ActionComposer
                        v-if="capabilities.can_edit && !isTerminal && !orgReadOnly"
                        :grievance-id="g.id"
                        :types="action_types"
                    />

                    <ClosurePanel
                        v-if="showClosurePanel"
                        :grievance="g"
                        :can-begin="capabilities.can_admin_review && state === 'resolved'"
                        :can-act="capabilities.can_closure_action"
                    />

                    <!-- Assignment + quick transitions -->
                    <div class="grid grid-cols-2 gap-4">
                        <div v-if="canAssign" class="bg-white border border-slate-200 rounded-lg p-5 space-y-3">
                            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Assignment</h2>
                            <div class="text-sm text-slate-600">{{ g.assigned_officer?.name ?? 'Unassigned' }}</div>
                            <select v-model="assignForm.officer_id" class="w-full rounded border-slate-300 text-sm">
                                <option :value="null">— Unassigned —</option>
                                <option v-for="o in officers_in_org" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                            <button type="button" :disabled="assignForm.processing" class="px-3 py-1.5 rounded bg-slate-900 text-white text-sm disabled:opacity-50" @click="saveAssignment">Save</button>
                        </div>

                        <div v-if="capabilities.can_transition && allowed_transitions.length" class="bg-white border border-slate-200 rounded-lg p-5 space-y-2">
                            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Quick actions</h2>
                            <button
                                v-for="t in allowed_transitions"
                                :key="t.value"
                                type="button"
                                class="w-full px-3 py-1.5 rounded border border-slate-300 text-sm text-left hover:bg-slate-50"
                                @click="applyTransition(t.value)"
                            >
                                → {{ t.label }}
                            </button>
                        </div>
                    </div>
                </template>

                <!-- ═══════════ PANEL 6: Feedback (existing, no changes) ═══════════ -->
                <!-- Feedback is shown via the timeline when kind=feedback -->
            </template>
        </div>
    </AppLayout>
</template>
