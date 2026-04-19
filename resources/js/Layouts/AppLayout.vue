<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const roles = computed(() => page.props.auth.roles ?? []);
const permissions = computed(() => page.props.auth.permissions ?? []);

const can = (p) => permissions.value.includes(p);
const hasRole = (r) => roles.value.includes(r);
const sidebarOpen = ref(true);
const mobileMenuOpen = ref(false);
const profileOpen = ref(false);
const flashVisible = ref(false);
const flashMsg = ref('');
const flashType = ref('success');

function logout() {
    router.post(route('logout'));
}

const initials = computed(() => {
    if (!user.value?.name) return '?';
    return user.value.name.split(' ').filter(Boolean).slice(0, 2).map((n) => n[0].toUpperCase()).join('');
});

const primaryRole = computed(() => {
    const r = roles.value[0];
    if (!r) return '';
    return r.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
});

const orgName = computed(() => page.props.auth.user?.organization?.name ?? '');

function isActive(path) {
    return page.url.startsWith(path);
}

function navClass(path) {
    return isActive(path)
        ? 'bg-[var(--color-gold)]/15 text-[var(--color-gold-light)] font-semibold border-l-[3px] border-[var(--color-gold)]'
        : 'text-slate-400 hover:bg-white/5 hover:text-white border-l-[3px] border-transparent';
}

// Flash notifications
watch(flash, (f) => {
    if (f?.success || f?.error) {
        flashMsg.value = f.success || f.error;
        flashType.value = f.success ? 'success' : 'error';
        flashVisible.value = true;
        setTimeout(() => { flashVisible.value = false; }, 4000);
    }
}, { immediate: true });

// Close profile menu on outside click
function closeProfile(e) {
    if (profileOpen.value && !e.target.closest('.profile-trigger')) {
        profileOpen.value = false;
    }
}
onMounted(() => document.addEventListener('click', closeProfile));
onUnmounted(() => document.removeEventListener('click', closeProfile));
</script>

<template>
    <div class="min-h-screen flex" style="background: var(--color-surface);">
        <!-- Mobile overlay -->
        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 z-40 bg-black/50 md:hidden"
            @click="mobileMenuOpen = false"
        />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col transition-all duration-200 overflow-hidden',
                sidebarOpen ? 'w-64' : 'w-[68px]',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
            ]"
            style="background: var(--color-navy);"
        >
            <!-- Brand -->
            <div class="flex items-center gap-3 px-4 h-16 shrink-0" style="border-bottom: 1px solid var(--color-navy-light);">
                <div class="flex flex-col gap-[3px] shrink-0">
                    <span class="block h-[3px] w-7 rounded-full" style="background: #1EB53A;" />
                    <span class="block h-[3px] w-7 rounded-full bg-white" />
                    <span class="block h-[3px] w-7 rounded-full" style="background: #0072C6;" />
                </div>
                <div v-if="sidebarOpen" class="min-w-0">
                    <p class="text-white text-sm font-bold leading-tight truncate">GRM Sierra Leone</p>
                    <p class="text-slate-400 text-[10px] tracking-wide">Anti-Corruption Commission</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-4 space-y-6 text-[13px]">
                <!-- Dashboard -->
                <div>
                    <Link :href="route('dashboard')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/dashboard')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" /></svg>
                        <span v-if="sidebarOpen">Dashboard</span>
                    </Link>
                    <Link v-if="can('grievance.viewAny')" :href="route('admin.reports.quarterly')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/reports/quarterly')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                        <span v-if="sidebarOpen">Quarterly Report</span>
                    </Link>
                </div>

                <!-- Grievance Management -->
                <div v-if="can('grievance.viewAny')">
                    <p v-if="sidebarOpen" class="px-4 mb-1 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--color-gold);">Case Management</p>
                    <Link :href="route('admin.grievances.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/grievances')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        <span v-if="sidebarOpen">Grievances</span>
                    </Link>
                    <Link :href="route('admin.beneficiaries.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/beneficiaries')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                        <span v-if="sidebarOpen">Project Beneficiaries</span>
                    </Link>
                    <Link :href="route('admin.reports.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/reports')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" /></svg>
                        <span v-if="sidebarOpen">Reports</span>
                    </Link>
                </div>

                <!-- Organization -->
                <div v-if="hasRole('super-admin') || can('locality.viewAny')">
                    <p v-if="sidebarOpen" class="px-4 mb-1 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--color-gold);">Organisation</p>
                    <Link v-if="hasRole('super-admin')" :href="route('admin.organizations.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/organizations')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18m16.5-18v18M5.25 3h13.5M5.25 21V3m13.5 18V3M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15" /></svg>
                        <span v-if="sidebarOpen">Organisations</span>
                    </Link>
                    <Link v-if="hasRole('super-admin')" :href="route('admin.geography.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/geography')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                        <span v-if="sidebarOpen">Geography</span>
                    </Link>
                </div>

                <!-- People -->
                <div v-if="can('user.viewAny')">
                    <p v-if="sidebarOpen" class="px-4 mb-1 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--color-gold);">People</p>
                    <Link :href="route('admin.org.users.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/org/users')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                        <span v-if="sidebarOpen">Users</span>
                    </Link>
                </div>

                <!-- Configuration -->
                <div v-if="hasRole('super-admin') || can('grievance.transition') || hasRole('org-admin') || hasRole('grm-officer') || can('role.viewAny') || can('audit.viewAny')">
                    <p v-if="sidebarOpen" class="px-4 mb-1 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--color-gold);">Settings</p>
                    <Link v-if="hasRole('super-admin')" :href="route('admin.reference.grievance-types.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/reference')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <span v-if="sidebarOpen">Reference Data</span>
                    </Link>
                    <Link v-if="hasRole('org-admin') || hasRole('grm-officer')" :href="route('admin.org.grievance-types.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/org/grievance-types')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" /></svg>
                        <span v-if="sidebarOpen">Sub-classifications</span>
                    </Link>
                    <Link v-if="hasRole('org-admin') || hasRole('super-admin') || hasRole('grm-officer')" :href="route('admin.programmes.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/programmes')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" /></svg>
                        <span v-if="sidebarOpen">Programmes</span>
                    </Link>
                    <Link v-if="hasRole('org-admin') || hasRole('grm-officer')" :href="route('admin.org.sla.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/org/sla')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span v-if="sidebarOpen">SLA Settings</span>
                    </Link>
                    <Link v-if="can('role.viewAny')" :href="route('admin.roles.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/roles')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                        <span v-if="sidebarOpen">Roles</span>
                    </Link>
                    <Link v-if="can('audit.viewAny')" :href="route('admin.audit.index')" :class="['flex items-center gap-3 px-4 py-2 mx-1 rounded-md transition', navClass('/admin/audit')]">
                        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                        <span v-if="sidebarOpen">Audit Log</span>
                    </Link>
                </div>
            </nav>

            <!-- Sidebar footer -->
            <div class="p-3 text-center shrink-0" style="border-top: 1px solid var(--color-navy-light);">
                <p v-if="sidebarOpen" class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Anti-Corruption Commission</p>
                <div v-else class="flex flex-col gap-[2px] items-center">
                    <span class="block h-[2px] w-5 rounded-full" style="background: #1EB53A;" />
                    <span class="block h-[2px] w-5 rounded-full bg-white/50" />
                    <span class="block h-[2px] w-5 rounded-full" style="background: #0072C6;" />
                </div>
            </div>

            <!-- Collapse toggle (desktop) -->
            <button
                type="button"
                class="hidden md:flex absolute -right-3 top-[72px] h-6 w-6 rounded-full text-white text-[10px] items-center justify-center shadow-md transition hover:scale-110"
                style="background: var(--color-gold);"
                @click="sidebarOpen = !sidebarOpen"
            >
                {{ sidebarOpen ? '◂' : '▸' }}
            </button>
        </aside>

        <!-- Main content -->
        <div :class="['flex-1 flex flex-col transition-all duration-200 min-h-screen', sidebarOpen ? 'md:ml-64' : 'md:ml-[68px]']">
            <!-- Top bar -->
            <header class="sticky top-0 z-20 bg-white border-b px-4 md:px-6 h-16 flex items-center justify-between" style="border-color: var(--color-border);">
                <!-- Mobile hamburger -->
                <button type="button" class="md:hidden mr-3 text-slate-600" @click="mobileMenuOpen = !mobileMenuOpen">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>

                <div class="flex-1"><slot name="header" /></div>

                <!-- User section -->
                <div v-if="user" class="relative flex items-center gap-3 profile-trigger">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-slate-900 leading-tight">{{ user.name }}</p>
                        <div class="flex items-center justify-end gap-1.5 mt-0.5">
                            <span class="state-badge inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold" style="background: var(--color-gold); color: var(--color-navy);">
                                {{ primaryRole }}
                            </span>
                            <span v-if="orgName" class="text-[11px] text-slate-500 truncate max-w-[120px]">{{ orgName }}</span>
                        </div>
                    </div>
                    <button type="button" class="profile-trigger flex items-center gap-1" @click="profileOpen = !profileOpen">
                        <span class="h-9 w-9 rounded-full text-white text-xs font-bold flex items-center justify-center" style="background: var(--color-navy);">
                            {{ initials }}
                        </span>
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>

                    <!-- Profile dropdown -->
                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="profileOpen" class="absolute right-0 top-full mt-2 w-56 rounded-lg border bg-white shadow-lg py-1 z-50" style="border-color: var(--color-border);">
                            <div class="px-4 py-3 border-b" style="border-color: var(--color-border);">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ user.name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ user.email }}</p>
                            </div>
                            <Link :href="route('profile.show')" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="profileOpen = false">View profile</Link>
                            <Link :href="route('settings.notifications.edit')" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="profileOpen = false">Notifications</Link>
                            <div class="border-t my-1" style="border-color: var(--color-border);" />
                            <button type="button" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50" @click="logout">Sign out</button>
                        </div>
                    </Transition>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 px-4 md:px-8 py-6"><slot /></main>

            <!-- Footer -->
            <footer class="px-4 md:px-8 py-4 border-t text-xs text-slate-400" style="border-color: var(--color-border);">
                Grievance Redress Mechanism · Anti-Corruption Commission Sierra Leone · {{ new Date().getFullYear() }}
            </footer>
        </div>

        <!-- Flash toast — top right -->
        <Teleport to="body">
            <Transition
                enter-active-class="flash-enter"
                leave-active-class="flash-exit"
            >
                <div
                    v-if="flashVisible"
                    :class="[
                        'fixed top-4 right-4 z-[60] max-w-sm px-4 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2',
                        flashType === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white',
                    ]"
                >
                    <svg v-if="flashType === 'success'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                    <span>{{ flashMsg }}</span>
                    <button type="button" class="ml-auto opacity-70 hover:opacity-100" @click="flashVisible = false">&times;</button>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
