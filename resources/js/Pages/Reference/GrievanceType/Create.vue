<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import NameOnlyForm from '@/Components/NameOnlyForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{ resource: string }>();
</script>

<template>
    <Head title="New grievance type" />
    <AppLayout>
        <NameOnlyForm :resource="resource" title="New grievance type" />
    </AppLayout>
</template>
