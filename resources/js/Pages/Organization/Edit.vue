<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface OrgData {
    id: number;
    name: string;
    acronym: string | null;
    description: string | null;
    parent_id: number | null;
    grievance_types?: Array<{ id: number; name: string }>;
}

const props = defineProps<{
    organization: { data: OrgData };
    parents: Array<{ id: number; name: string }>;
    grievanceTypes: Array<{ id: number; name: string }>;
}>();

const org = props.organization.data;

const form = useForm({
    name: org.name,
    acronym: org.acronym ?? '',
    description: org.description ?? '',
    parent_id: org.parent_id,
    grievance_type_ids: (org.grievance_types ?? []).map((gt) => gt.id),
});

function submit(): void {
    form.put(route('admin.organizations.update', org.id));
}
</script>

<template>
    <Head :title="`Edit ${org.name}`" />
    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-4">Edit {{ org.name }}</h1>

        <form class="bg-white rounded shadow-sm p-6 space-y-4 max-w-2xl" @submit.prevent="submit">
            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Name</span>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    maxlength="200"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                />
                <span v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</span>
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Acronym</span>
                <input
                    v-model="form.acronym"
                    type="text"
                    maxlength="50"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                />
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Description</span>
                <textarea
                    v-model="form.description"
                    rows="3"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                />
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-slate-700">Parent organization</span>
                <select
                    v-model="form.parent_id"
                    class="mt-1 block w-full rounded border-slate-300 focus:border-slate-500 focus:ring-slate-500"
                >
                    <option :value="null">— None —</option>
                    <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </label>

            <fieldset>
                <legend class="text-sm font-medium text-slate-700 mb-2">Handles grievance types</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label v-for="gt in grievanceTypes" :key="gt.id" class="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            v-model="form.grievance_type_ids"
                            type="checkbox"
                            :value="gt.id"
                            class="rounded border-slate-300"
                        />
                        {{ gt.name }}
                    </label>
                </div>
            </fieldset>

            <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800 disabled:opacity-50"
            >
                Save
            </button>
        </form>
    </AppLayout>
</template>
