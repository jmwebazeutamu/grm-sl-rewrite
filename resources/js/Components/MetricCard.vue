<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
    tone: { type: String, default: 'default' },
});
</script>

<template>
    <div
        :class="[
            'bg-white rounded-xl p-5 transition',
            tone === 'alert' ? 'border-l-[4px]' : '',
        ]"
        :style="{
            boxShadow: 'var(--shadow-card)',
            border: tone === 'alert' ? '' : '1px solid var(--color-border)',
            borderLeftColor: tone === 'alert' ? 'var(--color-sla-red)' : undefined,
            borderTop: tone === 'alert' ? '1px solid var(--color-border)' : undefined,
            borderRight: tone === 'alert' ? '1px solid var(--color-border)' : undefined,
            borderBottom: tone === 'alert' ? '1px solid var(--color-border)' : undefined,
        }"
    >
        <p class="text-[11px] font-semibold uppercase tracking-[0.1em]" style="color: var(--color-slate);">{{ label }}</p>
        <p
            :class="['mt-2 text-3xl font-bold tracking-tight', tone === 'alert' ? 'text-red-600' : '']"
            :style="tone !== 'alert' ? { color: 'var(--color-navy)' } : {}"
        >
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1.5 text-xs" style="color: var(--color-slate);">{{ hint }}</p>
    </div>
</template>
