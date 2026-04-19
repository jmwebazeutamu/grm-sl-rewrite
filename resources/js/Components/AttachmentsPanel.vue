<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    grievanceId: { type: Number, required: true },
    attachments: { type: Array, default: () => [] },
    canUpload: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const MAX_FILES = 5;
const MAX_BYTES = 10 * 1024 * 1024;
const ALLOWED = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
const ACCEPT_ATTR = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png';

const submitted = computed(() => props.attachments.filter((a) => a.source === 'submission'));
const officer = computed(() => props.attachments.filter((a) => a.source === 'officer'));

const selected = ref([]);
const description = ref('');
const isDragging = ref(false);
const isUploading = ref(false);
const clientErrors = ref([]);
const fileInput = ref(null);

function iconFor(ext) {
    const e = (ext || '').toLowerCase();
    if (['pdf'].includes(e)) return { label: 'PDF', cls: 'bg-rose-100 text-rose-700' };
    if (['doc', 'docx'].includes(e)) return { label: 'DOC', cls: 'bg-blue-100 text-blue-700' };
    if (['xls', 'xlsx'].includes(e)) return { label: 'XLS', cls: 'bg-emerald-100 text-emerald-700' };
    if (['jpg', 'jpeg', 'png'].includes(e)) return { label: 'IMG', cls: 'bg-violet-100 text-violet-700' };
    return { label: 'FILE', cls: 'bg-slate-100 text-slate-700' };
}

function fmtSize(bytes) {
    const b = Number(bytes || 0);
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
    return `${(b / (1024 * 1024)).toFixed(1)} MB`;
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString();
}

function validateFile(file) {
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!ALLOWED.includes(ext)) return `Type not allowed: ${file.name}`;
    if (file.size > MAX_BYTES) return `Exceeds 10 MB: ${file.name}`;
    return null;
}

function addFiles(fileList) {
    clientErrors.value = [];
    const incoming = Array.from(fileList);
    const all = [...selected.value, ...incoming];
    if (all.length > MAX_FILES) {
        clientErrors.value.push(`Maximum ${MAX_FILES} files per upload.`);
        return;
    }
    const errs = [];
    for (const f of incoming) {
        const err = validateFile(f);
        if (err) errs.push(err);
    }
    if (errs.length) {
        clientErrors.value = errs;
        return;
    }
    selected.value = all;
}

function onPick(event) {
    addFiles(event.target.files);
    event.target.value = '';
}

function onDrop(event) {
    event.preventDefault();
    isDragging.value = false;
    addFiles(event.dataTransfer.files);
}

function removeAt(i) {
    selected.value = selected.value.filter((_, idx) => idx !== i);
}

function submit() {
    if (selected.value.length === 0 || isUploading.value) return;
    clientErrors.value = [];
    isUploading.value = true;

    const form = new FormData();
    selected.value.forEach((f) => form.append('files[]', f));
    if (description.value) form.append('description', description.value);

    router.post(route('admin.grievances.attachments.store', props.grievanceId), form, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            selected.value = [];
            description.value = '';
        },
        onError: (errors) => {
            clientErrors.value = Object.values(errors).flat();
        },
        onFinish: () => {
            isUploading.value = false;
        },
    });
}

function confirmDelete(attachment) {
    if (!confirm(`Delete "${attachment.original_name}"? This cannot be undone.`)) return;
    router.delete(route('admin.grievances.attachments.destroy', [props.grievanceId, attachment.id]), {
        preserveScroll: true,
    });
}

function downloadUrl(attachment) {
    return route('admin.grievances.attachments.download', [props.grievanceId, attachment.id]);
}
</script>

<template>
    <section class="bg-white border border-slate-200 rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide flex items-center gap-2">
                <span class="text-slate-500">📎</span>
                Files &amp; Documents
                <span class="ml-2 px-2 py-0.5 rounded-full bg-slate-100 text-xs text-slate-600">
                    {{ attachments.length }} {{ attachments.length === 1 ? 'file' : 'files' }}
                </span>
            </h2>
        </div>

        <!-- Section A: Submitted with grievance -->
        <div class="mb-5">
            <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Submitted with grievance</h3>
            <p v-if="submitted.length === 0" class="text-sm text-slate-400 italic">No files were submitted with this grievance.</p>
            <ul v-else class="divide-y divide-slate-100 border border-slate-200 rounded">
                <li v-for="a in submitted" :key="a.id" class="flex items-center gap-3 px-3 py-2">
                    <span :class="['flex-none inline-flex items-center justify-center h-9 w-9 rounded text-[10px] font-semibold', iconFor(a.extension).cls]">
                        {{ iconFor(a.extension).label }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-900 truncate" :title="a.original_name">{{ a.original_name }}</p>
                        <p class="text-xs text-slate-500">{{ a.size_formatted }} · {{ fmtDate(a.created_at) }}</p>
                    </div>
                    <a :href="downloadUrl(a)" target="_blank" rel="noopener" class="flex-none px-2.5 py-1 rounded border border-slate-300 text-xs text-slate-700 hover:bg-slate-50">
                        Download
                    </a>
                </li>
            </ul>
        </div>

        <!-- Section B: Officer uploads -->
        <div class="mb-5">
            <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Officer uploads</h3>
            <p v-if="officer.length === 0" class="text-sm text-slate-400 italic">No files have been added by officers yet.</p>
            <ul v-else class="divide-y divide-slate-100 border border-slate-200 rounded">
                <li v-for="a in officer" :key="a.id" class="flex items-start gap-3 px-3 py-2">
                    <span :class="['flex-none inline-flex items-center justify-center h-9 w-9 rounded text-[10px] font-semibold', iconFor(a.extension).cls]">
                        {{ iconFor(a.extension).label }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-900 truncate" :title="a.original_name">{{ a.original_name }}</p>
                        <p v-if="a.description" class="text-xs text-slate-500 italic mt-0.5">{{ a.description }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ a.size_formatted }} · {{ fmtDate(a.created_at) }}
                            <span v-if="a.uploaded_by"> · by {{ a.uploaded_by.name }}</span>
                        </p>
                    </div>
                    <div class="flex-none flex items-center gap-2">
                        <a :href="downloadUrl(a)" target="_blank" rel="noopener" class="px-2.5 py-1 rounded border border-slate-300 text-xs text-slate-700 hover:bg-slate-50">
                            Download
                        </a>
                        <button
                            v-if="canDelete"
                            type="button"
                            class="px-2.5 py-1 rounded border border-rose-300 text-xs text-rose-700 hover:bg-rose-50"
                            @click="confirmDelete(a)"
                        >
                            Delete
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Section C: Upload form -->
        <div v-if="canUpload" class="border-t border-slate-200 pt-4">
            <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Upload files</h3>

            <div
                :class="[
                    'rounded border-2 border-dashed px-4 py-6 text-center transition',
                    isDragging ? 'border-indigo-400 bg-indigo-50' : 'border-slate-300 bg-slate-50',
                ]"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop="onDrop"
            >
                <p class="text-sm text-slate-600">
                    Drag files here, or
                    <button type="button" class="text-indigo-600 underline hover:text-indigo-800" @click="fileInput?.click()">
                        click to browse
                    </button>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    PDF, DOC, XLS, JPG, PNG · up to 10 MB per file · max 5 files
                </p>
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    :accept="ACCEPT_ATTR"
                    class="hidden"
                    @change="onPick"
                />
            </div>

            <ul v-if="selected.length" class="mt-3 space-y-1">
                <li v-for="(f, i) in selected" :key="i" class="flex items-center gap-2 text-sm text-slate-700">
                    <span class="flex-1 truncate">{{ f.name }}</span>
                    <span class="text-xs text-slate-500">{{ fmtSize(f.size) }}</span>
                    <button type="button" class="px-1.5 text-slate-500 hover:text-rose-600" @click="removeAt(i)">×</button>
                </li>
            </ul>

            <div v-if="clientErrors.length" class="mt-3 rounded bg-rose-50 border border-rose-200 px-3 py-2 text-sm text-rose-800">
                <p v-for="(err, i) in clientErrors" :key="i">{{ err }}</p>
            </div>

            <div class="mt-3">
                <input
                    v-model="description"
                    type="text"
                    maxlength="255"
                    placeholder="Brief description of these files (optional)"
                    class="block w-full rounded border-slate-300 text-sm"
                />
            </div>

            <div class="mt-3 flex items-center gap-3">
                <button
                    type="button"
                    :disabled="selected.length === 0 || isUploading"
                    class="px-4 py-2 rounded bg-slate-900 text-white text-sm disabled:opacity-50 inline-flex items-center gap-2"
                    @click="submit"
                >
                    <span v-if="isUploading" class="inline-block h-3 w-3 rounded-full border-2 border-white border-t-transparent animate-spin" />
                    {{ isUploading ? 'Uploading…' : 'Upload files' }}
                </button>
                <span v-if="selected.length" class="text-xs text-slate-500">
                    {{ selected.length }} of {{ MAX_FILES }} selected
                </span>
            </div>
        </div>
    </section>
</template>
