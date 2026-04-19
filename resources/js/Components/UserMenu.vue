<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const open = ref(false);
const menu = ref(null);

function toggle() {
    open.value = !open.value;
}

function closeOnOutside(event) {
    if (menu.value && !menu.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', closeOnOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutside));

function logout() {
    open.value = false;
    router.post(route('logout'));
}

const initials = computed(() => {
    if (!user.value?.name) return '?';
    return user.value.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((n) => n[0].toUpperCase())
        .join('');
});
</script>

<template>
    <div v-if="user" ref="menu" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-full hover:bg-slate-100 pl-1 pr-3 py-1 transition"
            @click="toggle"
        >
            <span class="h-8 w-8 rounded-full bg-slate-900 text-white text-xs font-semibold flex items-center justify-center">
                {{ initials }}
            </span>
            <span class="text-sm text-slate-700">{{ user.name }}</span>
            <svg class="h-3 w-3 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.24 4.38a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute right-0 mt-1 w-56 rounded-lg border border-slate-200 bg-white shadow-lg py-1 z-50"
        >
            <div class="px-3 py-2 border-b border-slate-100">
                <div class="text-sm font-medium text-slate-900 truncate">{{ user.name }}</div>
                <div class="text-xs text-slate-500 truncate">{{ user.email }}</div>
            </div>
            <Link
                :href="route('profile.show')"
                class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="open = false"
            >
                View profile
            </Link>
            <Link
                :href="route('settings.notifications.edit')"
                class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="open = false"
            >
                Notification preferences
            </Link>
            <button
                type="button"
                class="w-full text-left px-3 py-2 text-sm text-rose-700 hover:bg-rose-50"
                @click="logout"
            >
                Sign out
            </button>
        </div>
    </div>
</template>
