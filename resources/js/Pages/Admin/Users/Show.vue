<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    user: {
        data: {
            id: number;
            name: string;
            username: string;
            email: string;
            phone_number: string | null;
            roles: string[];
            email_verified: boolean;
        };
    };
    roles: string[];
}>();

const form = useForm({
    roles: [...props.user.data.roles],
});

function save(): void {
    form.put(route('admin.users.roles.update', props.user.data.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="user.data.name" />
    <AppLayout>
        <h1 class="text-2xl font-semibold text-slate-900 mb-4">{{ user.data.name }}</h1>

        <div class="grid grid-cols-2 gap-6">
            <section class="bg-white border border-slate-200 rounded p-5 space-y-2 text-sm">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Profile</h2>
                <div class="flex justify-between"><span class="text-slate-500">Username</span><span class="font-mono">{{ user.data.username }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Email</span><span>{{ user.data.email }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Phone</span><span>{{ user.data.phone_number ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Email verified</span><span>{{ user.data.email_verified ? 'Yes' : 'No' }}</span></div>
            </section>

            <section class="bg-white border border-slate-200 rounded p-5">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Roles</h2>
                <div class="space-y-2">
                    <label v-for="r in roles" :key="r" class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.roles" :value="r" type="checkbox" class="rounded border-slate-300" />
                        {{ r }}
                    </label>
                </div>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="mt-4 px-3 py-1.5 rounded bg-slate-900 text-white text-sm disabled:opacity-50"
                    @click="save"
                >
                    Save roles
                </button>
                <p class="mt-2 text-xs text-slate-500">
                    Only super-admins can grant or revoke the super-admin role.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
