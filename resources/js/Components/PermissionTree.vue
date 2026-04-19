<script setup lang="ts">
import { computed } from 'vue';

interface Group {
    resource: string;
    permissions: string[];
}

const props = defineProps<{
    groups: Group[];
    modelValue: string[];
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

function toggle(name: string): void {
    const next = props.modelValue.includes(name)
        ? props.modelValue.filter((n) => n !== name)
        : [...props.modelValue, name];
    emit('update:modelValue', next);
}

function toggleGroup(group: Group): void {
    const allOn = group.permissions.every((p) => props.modelValue.includes(p));
    const next = allOn
        ? props.modelValue.filter((n) => !group.permissions.includes(n))
        : [...new Set([...props.modelValue, ...group.permissions])];
    emit('update:modelValue', next);
}

function groupState(group: Group): 'all' | 'some' | 'none' {
    const on = group.permissions.filter((p) => props.modelValue.includes(p)).length;
    if (on === 0) return 'none';
    if (on === group.permissions.length) return 'all';
    return 'some';
}

const ability = (name: string) => name.split('.').slice(1).join('.');
</script>

<template>
    <div class="space-y-3">
        <details
            v-for="g in groups"
            :key="g.resource"
            class="bg-white border border-slate-200 rounded"
        >
            <summary class="cursor-pointer px-4 py-3 flex items-center justify-between">
                <span class="font-medium text-slate-900">{{ g.resource }}</span>
                <label class="flex items-center gap-2 text-xs text-slate-500" @click.stop>
                    <input
                        type="checkbox"
                        :checked="groupState(g) === 'all'"
                        :indeterminate.prop="groupState(g) === 'some'"
                        :disabled="disabled"
                        class="rounded border-slate-300"
                        @change="toggleGroup(g)"
                    />
                    all
                </label>
            </summary>
            <div class="px-4 pb-3 grid grid-cols-3 gap-2">
                <label
                    v-for="p in g.permissions"
                    :key="p"
                    class="flex items-center gap-2 text-sm text-slate-700"
                >
                    <input
                        type="checkbox"
                        :checked="modelValue.includes(p)"
                        :disabled="disabled"
                        class="rounded border-slate-300"
                        @change="toggle(p)"
                    />
                    {{ ability(p) }}
                </label>
            </div>
        </details>
    </div>
</template>
