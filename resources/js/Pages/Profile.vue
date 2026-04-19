<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, required: true },
    permissionCount: { type: Number, required: true },
});
</script>

<template>
    <Head title="Your profile" />
    <AppLayout>
        <div class="max-w-2xl">
            <h1 class="text-2xl font-semibold text-slate-900 mb-6">Your profile</h1>

            <section class="bg-white border border-slate-200 rounded p-6 space-y-4">
                <div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Name</div>
                    <div class="mt-1 text-slate-900">{{ user.name }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Username</div>
                    <div class="mt-1 font-mono text-sm text-slate-900">{{ user.username }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Email</div>
                    <div class="mt-1 text-slate-900">
                        {{ user.email }}
                        <span v-if="user.email_verified_at" class="ml-2 text-xs text-emerald-700">verified</span>
                        <span v-else class="ml-2 text-xs text-amber-700">unverified</span>
                    </div>
                </div>
                <div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Phone</div>
                    <div class="mt-1 text-slate-900">{{ user.phone_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Roles</div>
                    <div class="mt-1">
                        <span v-if="roles.length === 0" class="text-slate-500">No roles assigned.</span>
                        <span
                            v-for="r in roles"
                            :key="r"
                            class="inline-block mr-1 mb-1 px-2 py-0.5 rounded bg-slate-100 text-xs text-slate-700"
                        >
                            {{ r }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Effective permissions</div>
                    <div class="mt-1 text-slate-900">{{ permissionCount }}</div>
                </div>
            </section>

            <p class="mt-4 text-xs text-slate-500">
                To change your password, use the password-reset flow at the sign-in page.
            </p>
        </div>
    </AppLayout>
</template>
