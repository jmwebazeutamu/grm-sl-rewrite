<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import LocationPicker from '@/Components/LocationPicker.vue';

interface Lookup {
    id: number;
    name: string;
}

interface ProjectLookup {
    id: number;
    name: string;
    acronym?: string | null;
    organization_id: number | null;
}

const props = defineProps<{
    grievanceTypes: Lookup[];
    howReported: Lookup[];
    organizations: Lookup[];
    programmes: ProjectLookup[];
    regions: Lookup[];
    recaptchaSiteKey: string | null;
}>();

interface SuspectInput {
    first_name: string;
    last_name: string;
    title: string;
    phone_number: string;
    is_beneficiary: boolean;
    programme_id: number | null;
    implementing_organization_id: number | null;
    beneficiary_id_number: string;
}

const form = useForm({
    summary: '',
    description: '',
    grievance_type_id: props.grievanceTypes[0]?.id ?? null,
    how_reported_id: null as number | null,
    region_id: null as number | null,
    district_id: null as number | null,
    chiefdom_id: null as number | null,
    section_id: null as number | null,
    locality_id: null as number | null,
    implementing_organization_id: null as number | null,
    programme_id: null as number | null,
    is_anonymous: false,
    complainer: {
        first_name: '',
        last_name: '',
        gender: '',
        email: '',
        phone_number: '',
        address: '',
    },
    suspects: [] as SuspectInput[],
    attachments: [] as File[],
    recaptcha_token: '',
});

const recaptchaReady = ref(false);

onMounted(() => {
    if (!props.recaptchaSiteKey) {
        recaptchaReady.value = true;
        return;
    }
    const script = document.createElement('script');
    script.src = `https://www.google.com/recaptcha/api.js?render=${props.recaptchaSiteKey}`;
    script.async = true;
    script.onload = () => {
        recaptchaReady.value = true;
    };
    document.head.appendChild(script);
});

function addSuspect(): void {
    form.suspects.push({
        first_name: '',
        last_name: '',
        title: '',
        phone_number: '',
        is_beneficiary: false,
        programme_id: null,
        implementing_organization_id: null,
        beneficiary_id_number: '',
    });
}

function onBeneficiaryToggle(index: number): void {
    const s = form.suspects[index];
    if (!s.is_beneficiary) {
        s.programme_id = null;
        s.implementing_organization_id = null;
        s.beneficiary_id_number = '';
    }
}

function onProgrammeChange(index: number): void {
    const s = form.suspects[index];
    if (s.programme_id === null) return;
    const prog = props.programmes.find((p) => p.id === s.programme_id);
    if (prog && prog.organization_id) {
        s.implementing_organization_id = prog.organization_id;
    }
}

function isOrgLocked(index: number): boolean {
    return form.suspects[index].programme_id !== null;
}

const grievanceProgrammeOptions = computed(() =>
    props.programmes.filter((p) => p.organization_id === form.implementing_organization_id),
);

function onGrievanceOrgChange(): void {
    form.programme_id = null;
}

function onLocationChange(loc: Record<string, number | null>): void {
    form.region_id = loc.region_id ?? null;
    form.district_id = loc.district_id ?? null;
    form.chiefdom_id = loc.chiefdom_id ?? null;
    form.section_id = loc.section_id ?? null;
    form.locality_id = loc.locality_id ?? null;
}

const locationValue = computed(() => ({
    region_id: form.region_id,
    district_id: form.district_id,
    chiefdom_id: form.chiefdom_id,
    section_id: form.section_id,
    locality_id: form.locality_id,
}));

function removeSuspect(index: number): void {
    form.suspects.splice(index, 1);
}

function onFilesChosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.attachments = input.files ? Array.from(input.files) : [];
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    if (event.dataTransfer?.files) {
        form.attachments = Array.from(event.dataTransfer.files);
    }
}

function removeAttachment(i: number): void {
    form.attachments.splice(i, 1);
}

function fmtSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const hasErrors = computed(() => Object.keys(form.errors).length > 0);

async function submit(): Promise<void> {
    if (props.recaptchaSiteKey && typeof (window as any).grecaptcha?.execute === 'function') {
        await new Promise<void>((resolve) => (window as any).grecaptcha.ready(resolve));
        form.recaptcha_token = await (window as any).grecaptcha.execute(props.recaptchaSiteKey, {
            action: 'submit_grievance',
        });
    } else {
        form.recaptcha_token = 'dev';
    }

    form.post(route('grievances.public.store'), { forceFormData: true });
}
</script>

<template>
    <Head title="Submit a grievance" />

    <div class="min-h-screen" style="background: var(--color-surface);">
        <!-- HEADER BANNER -->
        <header class="text-white" style="background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);">
            <div class="max-w-5xl mx-auto px-6 py-10">
                <Link :href="route('home')" class="inline-flex items-center gap-2 text-xs font-medium opacity-80 hover:opacity-100 mb-6">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                    Back to home
                </Link>
                <h1 class="font-display text-4xl sm:text-5xl leading-tight">Submit a grievance</h1>
                <p class="mt-3 text-base max-w-2xl opacity-90">
                    Report an issue about a government programme, service, or official. You'll get a reference number so you can track progress.
                </p>
            </div>
        </header>

        <div class="max-w-5xl mx-auto px-6 py-8 lg:grid lg:grid-cols-3 lg:gap-8">
            <!-- MAIN FORM -->
            <form class="lg:col-span-2 space-y-5" @submit.prevent="submit">
                <!-- Error summary -->
                <div v-if="hasErrors" class="rounded-xl p-4 border bg-rose-50 border-rose-200 text-sm text-rose-800">
                    Please review the highlighted fields below and try again.
                </div>

                <!-- 1. About the grievance -->
                <section class="bg-white rounded-xl p-6" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <div class="flex items-start gap-3 mb-5">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0" style="background: var(--color-navy);">1</div>
                        <div>
                            <h2 class="text-lg font-bold" style="color: var(--color-navy);">About the grievance</h2>
                            <p class="text-xs mt-0.5" style="color: var(--color-slate);">A short title plus the full story of what happened.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <label class="block">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Summary <span class="text-rose-500">*</span></span>
                            <input
                                v-model="form.summary"
                                type="text"
                                required
                                maxlength="500"
                                placeholder="e.g. Delayed payment for Cash for Work project"
                                class="block w-full rounded-lg border-slate-300 px-3 py-2"
                                :class="form.errors.summary ? 'border-rose-400' : ''"
                            />
                            <span v-if="form.errors.summary" class="mt-1 block text-xs text-rose-600">{{ form.errors.summary }}</span>
                        </label>

                        <label class="block">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Description</span>
                            <textarea
                                v-model="form.description"
                                rows="5"
                                placeholder="What happened? When? Who was involved?"
                                class="block w-full rounded-lg border-slate-300 px-3 py-2"
                                :class="form.errors.description ? 'border-rose-400' : ''"
                            />
                            <span v-if="form.errors.description" class="mt-1 block text-xs text-rose-600">{{ form.errors.description }}</span>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="block text-sm font-medium text-slate-700 mb-1">Grievance type <span class="text-rose-500">*</span></span>
                                <select v-model="form.grievance_type_id" required class="block w-full rounded-lg border-slate-300 px-3 py-2" :class="form.errors.grievance_type_id ? 'border-rose-400' : ''">
                                    <option v-for="gt in grievanceTypes" :key="gt.id" :value="gt.id">{{ gt.name }}</option>
                                </select>
                                <span v-if="form.errors.grievance_type_id" class="mt-1 block text-xs text-rose-600">{{ form.errors.grievance_type_id }}</span>
                            </label>

                            <label class="block">
                                <span class="block text-sm font-medium text-slate-700 mb-1">How did you report this?</span>
                                <select v-model="form.how_reported_id" class="block w-full rounded-lg border-slate-300 px-3 py-2">
                                    <option :value="null">— Not specified —</option>
                                    <option v-for="hr in howReported" :key="hr.id" :value="hr.id">{{ hr.name }}</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- 2. Where -->
                <section class="bg-white rounded-xl p-6" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <div class="flex items-start gap-3 mb-5">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0" style="background: var(--color-navy);">2</div>
                        <div>
                            <h2 class="text-lg font-bold" style="color: var(--color-navy);">Where did it happen?</h2>
                            <p class="text-xs mt-0.5" style="color: var(--color-slate);">Optional, but it helps us route your case faster.</p>
                        </div>
                    </div>
                    <LocationPicker :regions="regions" :model-value="locationValue" :errors="form.errors" @update:model-value="onLocationChange" />
                </section>

                <!-- 3. Organisation & programme -->
                <section class="bg-white rounded-xl p-6" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <div class="flex items-start gap-3 mb-5">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0" style="background: var(--color-navy);">3</div>
                        <div>
                            <h2 class="text-lg font-bold" style="color: var(--color-navy);">Organisation or programme</h2>
                            <p class="text-xs mt-0.5" style="color: var(--color-slate);">If you know which organisation or project this relates to, select them. Otherwise leave blank — the review team will classify it.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Implementing organisation</span>
                            <select v-model="form.implementing_organization_id" class="block w-full rounded-lg border-slate-300 px-3 py-2" @change="onGrievanceOrgChange">
                                <option :value="null">— Select or leave blank —</option>
                                <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                            <span v-if="form.errors.implementing_organization_id" class="mt-1 block text-xs text-rose-600">{{ form.errors.implementing_organization_id }}</span>
                        </label>

                        <label v-show="form.implementing_organization_id !== null" class="block">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Programme / project</span>
                            <select v-model="form.programme_id" class="block w-full rounded-lg border-slate-300 px-3 py-2">
                                <option :value="null">— Not listed / Other —</option>
                                <option v-for="p in grievanceProgrammeOptions" :key="p.id" :value="p.id">
                                    {{ p.name }}{{ p.acronym ? ` (${p.acronym})` : '' }}
                                </option>
                            </select>
                            <span v-if="form.errors.programme_id" class="mt-1 block text-xs text-rose-600">{{ form.errors.programme_id }}</span>
                        </label>
                    </div>
                </section>

                <!-- 4. Contact / anonymous -->
                <section class="bg-white rounded-xl p-6" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <div class="flex items-start gap-3 mb-5">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0" style="background: var(--color-navy);">4</div>
                        <div>
                            <h2 class="text-lg font-bold" style="color: var(--color-navy);">Your contact details</h2>
                            <p class="text-xs mt-0.5" style="color: var(--color-slate);">So we can follow up. Choose "anonymous" to hide your name from the public case file — officers can still reach you via your chosen channel.</p>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 rounded-lg p-3 border cursor-pointer" :class="form.is_anonymous ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50'">
                        <input v-model="form.is_anonymous" type="checkbox" class="mt-0.5 rounded border-slate-300" />
                        <span>
                            <span class="block text-sm font-medium text-slate-800">Submit anonymously</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Your name and contact will be hidden from the public case view. Our review team still sees them.</span>
                        </span>
                    </label>

                    <div v-if="!form.is_anonymous" class="mt-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="block text-sm font-medium text-slate-700 mb-1">First name</span>
                                <input v-model="form.complainer.first_name" type="text" maxlength="100" class="block w-full rounded-lg border-slate-300 px-3 py-2" />
                                <span v-if="form.errors['complainer.first_name']" class="mt-1 block text-xs text-rose-600">{{ form.errors['complainer.first_name'] }}</span>
                            </label>
                            <label class="block">
                                <span class="block text-sm font-medium text-slate-700 mb-1">Last name</span>
                                <input v-model="form.complainer.last_name" type="text" maxlength="100" class="block w-full rounded-lg border-slate-300 px-3 py-2" />
                                <span v-if="form.errors['complainer.last_name']" class="mt-1 block text-xs text-rose-600">{{ form.errors['complainer.last_name'] }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="block text-sm font-medium text-slate-700 mb-1">Email</span>
                                <input v-model="form.complainer.email" type="email" maxlength="150" placeholder="you@example.com" class="block w-full rounded-lg border-slate-300 px-3 py-2" />
                            </label>
                            <label class="block">
                                <span class="block text-sm font-medium text-slate-700 mb-1">Phone number</span>
                                <input v-model="form.complainer.phone_number" type="tel" maxlength="30" placeholder="+232…" class="block w-full rounded-lg border-slate-300 px-3 py-2" />
                            </label>
                        </div>

                        <label class="block">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Address</span>
                            <textarea v-model="form.complainer.address" rows="2" maxlength="500" class="block w-full rounded-lg border-slate-300 px-3 py-2" />
                        </label>
                    </div>
                </section>

                <!-- 5. People -->
                <section class="bg-white rounded-xl p-6" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <div class="flex items-start justify-between gap-3 mb-5">
                        <div class="flex items-start gap-3">
                            <div class="h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0" style="background: var(--color-navy);">5</div>
                            <div>
                                <h2 class="text-lg font-bold" style="color: var(--color-navy);">Person(s) the grievance is about</h2>
                                <p class="text-xs mt-0.5" style="color: var(--color-slate);">Optional. Add details about the staff member, committee member, or beneficiary this concerns.</p>
                            </div>
                        </div>
                        <button type="button" class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: var(--color-navy);" @click="addSuspect">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Add person
                        </button>
                    </div>

                    <p v-if="form.suspects.length === 0" class="text-sm text-slate-400 italic">No people added yet.</p>

                    <div v-for="(suspect, i) in form.suspects" :key="i" class="rounded-lg p-4 mb-3 last:mb-0 relative space-y-3" style="border: 1px solid var(--color-border); background: var(--color-surface);">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--color-slate);">Person {{ i + 1 }}</span>
                            <button type="button" class="text-xs text-rose-600 hover:text-rose-800 font-medium" @click="removeSuspect(i)">Remove</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input v-model="suspect.first_name" placeholder="First name" class="rounded-lg border-slate-300 px-3 py-2 text-sm" />
                            <input v-model="suspect.last_name" placeholder="Last name" class="rounded-lg border-slate-300 px-3 py-2 text-sm" />
                            <input v-model="suspect.title" placeholder="Title / role" class="rounded-lg border-slate-300 px-3 py-2 text-sm" />
                            <input v-model="suspect.phone_number" placeholder="Phone" class="rounded-lg border-slate-300 px-3 py-2 text-sm" />
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="suspect.is_beneficiary" type="checkbox" class="rounded border-slate-300" @change="onBeneficiaryToggle(i)" />
                            This person is a project beneficiary
                        </label>

                        <div v-show="suspect.is_beneficiary" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <label class="block text-sm sm:col-span-2">
                                <span class="block text-slate-700 mb-1">Beneficiary ID number</span>
                                <input v-model="suspect.beneficiary_id_number" type="text" maxlength="100" placeholder="e.g. SCT-2024-018234" class="block w-full rounded-lg border-slate-300 px-3 py-2 text-sm" />
                                <span v-if="form.errors[`suspects.${i}.beneficiary_id_number`]" class="mt-1 block text-xs text-rose-600">{{ form.errors[`suspects.${i}.beneficiary_id_number`] }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="block text-slate-700 mb-1">Project</span>
                                <select v-model="suspect.programme_id" class="block w-full rounded-lg border-slate-300 px-3 py-2 text-sm" @change="onProgrammeChange(i)">
                                    <option :value="null">— Other / not listed —</option>
                                    <option v-for="p in programmes" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </label>
                            <label class="block text-sm">
                                <span class="block text-slate-700 mb-1">Implementing organisation</span>
                                <select v-model="suspect.implementing_organization_id" :disabled="isOrgLocked(i)" :class="['block w-full rounded-lg border-slate-300 px-3 py-2 text-sm', isOrgLocked(i) ? 'bg-slate-100 text-slate-500' : '']">
                                    <option :value="null">— Select —</option>
                                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- 6. Attachments -->
                <section class="bg-white rounded-xl p-6" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <div class="flex items-start gap-3 mb-5">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0" style="background: var(--color-navy);">6</div>
                        <div>
                            <h2 class="text-lg font-bold" style="color: var(--color-navy);">Attachments</h2>
                            <p class="text-xs mt-0.5" style="color: var(--color-slate);">Photos, documents, or recordings. Up to 10 files, 10 MB each.</p>
                        </div>
                    </div>

                    <label
                        class="block rounded-xl border-2 border-dashed p-6 text-center cursor-pointer transition hover:border-slate-400"
                        style="border-color: var(--color-border);"
                        @dragover.prevent
                        @drop="onDrop"
                    >
                        <input type="file" multiple class="hidden" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.mp3,.mp4,.m4a,.txt" @change="onFilesChosen" />
                        <svg class="w-8 h-8 mx-auto mb-2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        <p class="text-sm text-slate-700 font-medium">Click to browse or drag files here</p>
                        <p class="text-xs text-slate-400 mt-1">PDF · Image · Word · Excel · Audio</p>
                    </label>

                    <ul v-if="form.attachments.length > 0" class="mt-3 space-y-1.5">
                        <li v-for="(f, i) in form.attachments" :key="i" class="flex items-center gap-2 text-xs bg-slate-50 rounded px-3 py-1.5">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            <span class="flex-1 truncate text-slate-700">{{ f.name }}</span>
                            <span class="text-slate-400 shrink-0">{{ fmtSize(f.size) }}</span>
                            <button type="button" class="text-rose-500 hover:text-rose-700 shrink-0" @click="removeAttachment(i)">×</button>
                        </li>
                    </ul>
                </section>

                <!-- Submit bar -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing || !recaptchaReady"
                        class="flex-1 px-5 py-3.5 rounded-lg text-white font-semibold text-sm transition hover:opacity-95 disabled:opacity-50"
                        style="background: var(--color-navy);"
                    >
                        {{ form.processing ? 'Submitting…' : 'Submit grievance' }}
                    </button>
                    <Link :href="route('home')" class="px-5 py-3.5 rounded-lg border text-sm text-slate-700 hover:bg-slate-50 text-center" style="border-color: var(--color-border);">Cancel</Link>
                </div>

                <p class="text-[11px] text-slate-400 text-center">
                    Protected by reCAPTCHA. By submitting, you consent to us processing this case to deliver a response.
                </p>
            </form>

            <!-- SIDE RAIL -->
            <aside class="hidden lg:block space-y-4 mt-0 sticky top-6 self-start">
                <div class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-top: 3px solid var(--color-gold);">
                    <h3 class="font-bold text-sm mb-3" style="color: var(--color-navy);">What happens next</h3>
                    <ol class="space-y-2.5 text-xs" style="color: var(--color-slate);">
                        <li class="flex gap-2">
                            <span class="h-5 w-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shrink-0" style="background: var(--color-navy);">1</span>
                            <span>You'll receive a GRM reference number.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="h-5 w-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shrink-0" style="background: var(--color-navy);">2</span>
                            <span>The ACC intake team reviews and classifies your case.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="h-5 w-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shrink-0" style="background: var(--color-navy);">3</span>
                            <span>The responsible organisation is assigned and begins work.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="h-5 w-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shrink-0" style="background: var(--color-navy);">4</span>
                            <span>You can check progress anytime using your reference number.</span>
                        </li>
                    </ol>
                </div>

                <div class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <h3 class="font-bold text-sm mb-2" style="color: var(--color-navy);">Your privacy</h3>
                    <p class="text-xs leading-relaxed" style="color: var(--color-slate);">
                        Your personal information is only shared with the reviewing officers. It's never published. Choose "Submit anonymously" to keep your identity out of the case file entirely.
                    </p>
                </div>

                <div class="bg-white rounded-xl p-5" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border);">
                    <h3 class="font-bold text-sm mb-2" style="color: var(--color-navy);">Already submitted?</h3>
                    <p class="text-xs mb-3" style="color: var(--color-slate);">Check the status of an existing grievance.</p>
                    <Link :href="route('home')" class="text-xs font-semibold inline-flex items-center gap-1" style="color: var(--color-gold);">
                        Track a grievance
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>
            </aside>
        </div>
    </div>
</template>
