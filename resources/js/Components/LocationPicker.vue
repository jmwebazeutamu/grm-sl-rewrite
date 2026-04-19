<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    regions: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const value = ref({
    region_id: props.modelValue?.region_id ?? null,
    district_id: props.modelValue?.district_id ?? null,
    chiefdom_id: props.modelValue?.chiefdom_id ?? null,
    section_id: props.modelValue?.section_id ?? null,
    locality_id: props.modelValue?.locality_id ?? null,
});

const districts = ref([]);
const chiefdoms = ref([]);
const sections = ref([]);
const localities = ref([]);

const loading = ref({
    districts: false, chiefdoms: false, sections: false, localities: false,
});

async function fetchList(endpoint, parentKey, parentId) {
    const url = `/api/geography/${endpoint}?${parentKey}=${parentId}`;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    return res.ok ? await res.json() : [];
}

function push() {
    emit('update:modelValue', { ...value.value });
}

async function loadDistricts(regionId, preserve = false) {
    if (!regionId) { districts.value = []; return; }
    loading.value.districts = true;
    districts.value = await fetchList('districts', 'region_id', regionId);
    loading.value.districts = false;
    if (!preserve) { value.value.district_id = null; clearBelow('district'); }
}

async function loadChiefdoms(districtId, preserve = false) {
    if (!districtId) { chiefdoms.value = []; return; }
    loading.value.chiefdoms = true;
    chiefdoms.value = await fetchList('chiefdoms', 'district_id', districtId);
    loading.value.chiefdoms = false;
    if (!preserve) { value.value.chiefdom_id = null; clearBelow('chiefdom'); }
}

async function loadSections(chiefdomId, preserve = false) {
    if (!chiefdomId) { sections.value = []; return; }
    loading.value.sections = true;
    sections.value = await fetchList('sections', 'chiefdom_id', chiefdomId);
    loading.value.sections = false;
    if (!preserve) { value.value.section_id = null; clearBelow('section'); }
}

async function loadLocalities(sectionId, preserve = false) {
    if (!sectionId) { localities.value = []; return; }
    loading.value.localities = true;
    localities.value = await fetchList('localities', 'section_id', sectionId);
    loading.value.localities = false;
    if (!preserve) { value.value.locality_id = null; }
}

function clearBelow(level) {
    const below = {
        region: ['district_id', 'chiefdom_id', 'section_id', 'locality_id'],
        district: ['chiefdom_id', 'section_id', 'locality_id'],
        chiefdom: ['section_id', 'locality_id'],
        section: ['locality_id'],
    }[level] || [];
    below.forEach((k) => { value.value[k] = null; });
    if (below.includes('chiefdom_id')) chiefdoms.value = [];
    if (below.includes('section_id')) sections.value = [];
    if (below.includes('locality_id')) localities.value = [];
}

async function onRegionChange() {
    clearBelow('region');
    await loadDistricts(value.value.region_id);
    push();
}
async function onDistrictChange() {
    clearBelow('district');
    await loadChiefdoms(value.value.district_id);
    push();
}
async function onChiefdomChange() {
    clearBelow('chiefdom');
    await loadSections(value.value.chiefdom_id);
    push();
}
async function onSectionChange() {
    clearBelow('section');
    await loadLocalities(value.value.section_id);
    push();
}
function onLocalityChange() { push(); }

// Pre-populate on mount
onMounted(async () => {
    if (value.value.region_id) await loadDistricts(value.value.region_id, true);
    if (value.value.district_id) await loadChiefdoms(value.value.district_id, true);
    if (value.value.chiefdom_id) await loadSections(value.value.chiefdom_id, true);
    if (value.value.section_id) await loadLocalities(value.value.section_id, true);
});

// Keep internal state in sync if parent replaces modelValue
watch(() => props.modelValue, (mv) => {
    value.value = {
        region_id: mv?.region_id ?? null,
        district_id: mv?.district_id ?? null,
        chiefdom_id: mv?.chiefdom_id ?? null,
        section_id: mv?.section_id ?? null,
        locality_id: mv?.locality_id ?? null,
    };
}, { deep: true });

// Readonly label
function readonlyLabel() {
    const mv = props.modelValue || {};
    const parts = [
        mv.region?.name, mv.district?.name, mv.chiefdom?.name, mv.section?.name, mv.locality?.name,
    ].filter(Boolean);
    return parts.length === 0 ? '—' : parts.join(' > ');
}
</script>

<template>
    <div v-if="readonly" class="text-sm text-slate-900">{{ readonlyLabel() }}</div>
    <div v-else class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <label class="block text-sm">
            <span class="block text-slate-700 mb-1">Region</span>
            <select v-model="value.region_id" class="block w-full rounded border-slate-300 text-sm" @change="onRegionChange">
                <option :value="null">— Select —</option>
                <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
            <span v-if="errors?.region_id" class="text-xs text-rose-600">{{ errors.region_id }}</span>
        </label>

        <label v-show="value.region_id" class="block text-sm">
            <span class="block text-slate-700 mb-1 flex items-center gap-1">
                District
                <span v-if="loading.districts" class="inline-block h-3 w-3 rounded-full border-2 border-slate-400 border-t-transparent animate-spin" />
            </span>
            <select v-model="value.district_id" class="block w-full rounded border-slate-300 text-sm" @change="onDistrictChange">
                <option :value="null">— Select —</option>
                <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <span v-if="errors?.district_id" class="text-xs text-rose-600">{{ errors.district_id }}</span>
        </label>

        <label v-show="value.district_id" class="block text-sm">
            <span class="block text-slate-700 mb-1 flex items-center gap-1">
                Chiefdom
                <span v-if="loading.chiefdoms" class="inline-block h-3 w-3 rounded-full border-2 border-slate-400 border-t-transparent animate-spin" />
            </span>
            <select v-model="value.chiefdom_id" class="block w-full rounded border-slate-300 text-sm" @change="onChiefdomChange">
                <option :value="null">— Select —</option>
                <option v-for="c in chiefdoms" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <span v-if="errors?.chiefdom_id" class="text-xs text-rose-600">{{ errors.chiefdom_id }}</span>
        </label>

        <label v-show="value.chiefdom_id" class="block text-sm">
            <span class="block text-slate-700 mb-1 flex items-center gap-1">
                Section
                <span v-if="loading.sections" class="inline-block h-3 w-3 rounded-full border-2 border-slate-400 border-t-transparent animate-spin" />
            </span>
            <select v-model="value.section_id" class="block w-full rounded border-slate-300 text-sm" @change="onSectionChange">
                <option :value="null">— Select —</option>
                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <span v-if="errors?.section_id" class="text-xs text-rose-600">{{ errors.section_id }}</span>
        </label>

        <label v-show="value.section_id" class="block text-sm">
            <span class="block text-slate-700 mb-1 flex items-center gap-1">
                Locality
                <span v-if="loading.localities" class="inline-block h-3 w-3 rounded-full border-2 border-slate-400 border-t-transparent animate-spin" />
            </span>
            <select v-model="value.locality_id" class="block w-full rounded border-slate-300 text-sm" @change="onLocalityChange">
                <option :value="null">— Select —</option>
                <option v-for="l in localities" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
            <span v-if="errors?.locality_id" class="text-xs text-rose-600">{{ errors.locality_id }}</span>
        </label>
    </div>
</template>
