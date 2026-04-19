<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const grmNumber = ref('');
const loading = ref(false);
const error = ref(null);
const result = ref(null);
const showModal = ref(false);

async function checkStatus() {
    if (!grmNumber.value.trim()) return;
    loading.value = true;
    error.value = null;
    result.value = null;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const res = await fetch('/grievance/status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ grm_number: grmNumber.value.trim() }),
        });
        const data = await res.json();
        if (data.error) { error.value = data.error; } else { result.value = data; showModal.value = true; }
    } catch { error.value = 'Unable to check status. Please try again.'; }
    finally { loading.value = false; }
}

function closeModal() { showModal.value = false; }

function stateBadgeClass(stateValue) {
    const map = {
        submitted: 'bg-blue-100 text-blue-700', under_review: 'bg-indigo-100 text-indigo-700',
        accepted: 'bg-blue-100 text-blue-700', in_progress: 'bg-amber-100 text-amber-700',
        reopened: 'bg-amber-100 text-amber-700', resolved: 'bg-emerald-100 text-emerald-700',
        closed: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700',
        escalated: 'bg-orange-100 text-orange-700', under_admin_review: 'bg-purple-100 text-purple-700',
    };
    return map[stateValue] ?? 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <Head title="Grievance Redress Mechanism — Anti-Corruption Commission Sierra Leone" />

    <div class="min-h-screen flex flex-col" style="background: var(--color-surface);">
        <!-- Header -->
        <header class="px-6 py-4" style="background: var(--color-navy);">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/images/sl-coat-of-arms.svg" class="h-12" alt="Sierra Leone" onerror="this.style.display='none'" />
                    <img src="/images/acc-logo.png" class="h-10" alt="ACC" onerror="this.style.display='none'" />
                </div>
                <div class="hidden md:block text-center">
                    <p class="text-white font-bold text-xl leading-tight">Grievance Redress Mechanism &amp; Anti-Corruption Reporting</p>
                    <p class="text-base mt-0.5" style="color: var(--color-gold-light);">Managed by Anti-Corruption Commission</p>
                </div>
                <a href="/login" class="text-sm font-medium px-4 py-2 rounded-lg transition hover:opacity-90" style="color: var(--color-navy); background: var(--color-gold);">Staff login</a>
            </div>
        </header>

        <!-- Hero -->
        <section class="py-20 px-6 text-center" style="background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);">
            <h1 class="text-4xl md:text-5xl font-bold text-white tracking-tight">Your voice matters.</h1>
            <p class="text-lg mt-3 max-w-2xl mx-auto" style="color: var(--color-gold-light);">
                Submit a grievance, track your case, or sign in to manage responses.
            </p>
        </section>

        <!-- Action cards -->
        <section class="py-14 px-6">
            <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Submit -->
                <div class="bg-white rounded-2xl p-6 flex flex-col" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-top: 4px solid var(--color-state-resolved);">
                    <div class="h-12 w-12 rounded-xl flex items-center justify-center mb-4" style="background: #ecfdf5;">
                        <svg class="w-6 h-6" style="color: var(--color-state-resolved);" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    </div>
                    <h2 class="text-lg font-bold" style="color: var(--color-navy);">Submit a Grievance</h2>
                    <p class="text-sm mt-2 flex-1" style="color: var(--color-slate);">Report a concern, complaint, or grievance. Anonymous submissions are supported.</p>
                    <a href="/submit-grievance" class="mt-5 block text-center rounded-lg py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background: var(--color-state-resolved);">Submit now</a>
                </div>

                <!-- Check status -->
                <div class="bg-white rounded-2xl p-6 flex flex-col" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-top: 4px solid var(--color-gold);">
                    <div class="h-12 w-12 rounded-xl flex items-center justify-center mb-4" style="background: #fef9ee;">
                        <svg class="w-6 h-6" style="color: var(--color-gold);" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    </div>
                    <h2 class="text-lg font-bold" style="color: var(--color-navy);">Check Grievance Status</h2>
                    <p class="text-sm mt-2" style="color: var(--color-slate);">Enter your reference number to see the current status of your grievance.</p>
                    <div class="mt-4">
                        <input v-model="grmNumber" type="text" placeholder="e.g. GRM-2026-000025" class="w-full rounded-lg px-4 py-2.5 text-sm" style="border: 1px solid var(--color-border);" @keydown.enter="checkStatus" />
                        <button type="button" :disabled="loading || !grmNumber.trim()" class="mt-2 w-full rounded-lg py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50 flex items-center justify-center gap-2" style="background: var(--color-gold); color: var(--color-navy);" @click="checkStatus">
                            <span v-if="loading" class="inline-block h-3 w-3 rounded-full border-2 border-current border-t-transparent animate-spin" />
                            {{ loading ? 'Checking…' : 'Check status' }}
                        </button>
                        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
                    </div>
                </div>

                <!-- Staff login -->
                <div class="bg-white rounded-2xl p-6 flex flex-col" style="box-shadow: var(--shadow-card); border: 1px solid var(--color-border); border-top: 4px solid var(--color-navy);">
                    <div class="h-12 w-12 rounded-xl flex items-center justify-center mb-4" style="background: #eef2ff;">
                        <svg class="w-6 h-6" style="color: var(--color-navy);" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                    </div>
                    <h2 class="text-lg font-bold" style="color: var(--color-navy);">Staff Portal</h2>
                    <p class="text-sm mt-2 flex-1" style="color: var(--color-slate);">Sign in to manage grievances, review cases, and generate reports.</p>
                    <a href="/login" class="mt-5 block text-center rounded-lg py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background: var(--color-navy);">Sign in</a>
                </div>
            </div>
        </section>

        <!-- Partners -->
        <section class="py-12 px-6" style="background: #f1f5f9;">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-center mb-8" style="color: var(--color-slate);">Our Partners</p>
            <div class="flex flex-wrap justify-center items-center gap-12 opacity-60">
                <img src="/images/worldbank-logo.svg" class="h-10" alt="World Bank" onerror="this.outerHTML='<span style=\'color: var(--color-slate); font-weight: 700; font-size: 1.125rem;\'>World Bank</span>'" />
                <span class="font-bold text-lg" style="color: var(--color-slate);">UNICEF</span>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-6 px-6 mt-auto" style="background: var(--color-navy);">
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-3 text-sm">
                <p style="color: var(--color-slate);">&copy; {{ new Date().getFullYear() }} Anti-Corruption Commission, Sierra Leone. All rights reserved.</p>
                <div class="flex gap-4">
                    <a href="/submit-grievance" class="transition hover:text-white" style="color: var(--color-gold-light);">Submit a Grievance</a>
                    <a href="/login" class="transition hover:text-white" style="color: var(--color-gold-light);">Staff Login</a>
                </div>
            </div>
        </footer>
    </div>

    <!-- Status modal -->
    <Teleport to="body">
        <div v-if="showModal && result" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModal">
            <div class="fixed inset-0 bg-black/50" @click="closeModal" />
            <div class="relative bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto z-10" style="box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <div class="flex items-center justify-between p-5" style="border-bottom: 1px solid var(--color-border);">
                    <h2 class="text-lg font-bold" style="color: var(--color-navy);">Grievance Status</h2>
                    <button type="button" class="text-slate-400 hover:text-slate-700 text-xl transition" @click="closeModal">&times;</button>
                </div>
                <div class="p-5">
                    <dl class="divide-y" style="border-color: var(--color-border);">
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium" style="color: var(--color-slate);">Reference number</dt>
                            <dd class="text-sm font-semibold font-mono" style="color: var(--color-navy);">{{ result.grm_number }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium" style="color: var(--color-slate);">Date submitted</dt>
                            <dd class="text-sm text-slate-900">{{ result.submitted_at }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium" style="color: var(--color-slate);">Last updated</dt>
                            <dd class="text-sm text-slate-900">{{ result.last_updated }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium" style="color: var(--color-slate);">Current state</dt>
                            <dd><span :class="[stateBadgeClass(result.state_value), 'state-badge inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold']">{{ result.state }}</span></dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-sm font-medium" style="color: var(--color-slate);">Assigned organisation</dt>
                            <dd class="text-sm text-slate-900">{{ result.assigned_org }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </Teleport>
</template>
