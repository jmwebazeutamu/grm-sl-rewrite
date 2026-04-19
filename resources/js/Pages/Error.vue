<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    status: { type: Number, required: true },
});

const titles = {
    403: 'Forbidden',
    404: 'Not found',
    500: 'Server error',
    503: 'Service unavailable',
};
</script>

<template>
    <Head :title="titles[status] ?? 'Error'" />
    <div class="min-h-screen flex items-center justify-center bg-slate-50">
        <div class="text-center max-w-md px-6">
            <p class="text-5xl font-bold text-slate-900">{{ status }}</p>
            <h1 class="mt-2 text-xl text-slate-700">{{ titles[status] ?? 'Something went wrong' }}</h1>
            <p v-if="status === 403" class="mt-3 text-sm text-slate-500">
                You don't have permission to view this page.
            </p>
            <p v-else-if="status === 404" class="mt-3 text-sm text-slate-500">
                The page you're looking for doesn't exist.
            </p>
            <Link :href="route('home')" class="mt-6 inline-block text-sm text-slate-700 underline">
                Back to home
            </Link>
        </div>
    </div>
</template>
