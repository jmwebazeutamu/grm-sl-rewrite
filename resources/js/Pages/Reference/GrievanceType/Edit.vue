<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import NameOnlyForm from '@/Components/NameOnlyForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    item: { id: number; name: string };
    resource: string;
}>();
</script>

<template>
    <Head :title="`Edit ${item.name}`" />
    <AppLayout>
        <NameOnlyForm :initial="item" :resource="resource" :title="`Edit ${item.name}`" />
    </AppLayout>
</template>
