<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ReferenceTable from '@/Components/ReferenceTable.vue';
import { Head } from '@inertiajs/vue3';

interface GrievanceType {
    id: number;
    name: string;
}

defineProps<{
    items: { data: GrievanceType[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    resource: string;
}>();
</script>

<template>
    <Head title="Grievance types" />
    <AppLayout>
        <ReferenceTable title="Grievance types" :resource="resource" :items="items" />
    </AppLayout>
</template>
