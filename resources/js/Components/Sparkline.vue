<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    points: number[];
    height?: number;
    width?: number;
}>();

const width = computed(() => props.width ?? 200);
const height = computed(() => props.height ?? 40);

const path = computed(() => {
    if (props.points.length === 0) return '';
    const max = Math.max(...props.points, 1);
    const step = width.value / Math.max(props.points.length - 1, 1);
    const h = height.value;
    return props.points
        .map((v, i) => {
            const x = i * step;
            const y = h - (v / max) * (h - 2) - 1;
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
});
</script>

<template>
    <svg :width="width" :height="height" class="overflow-visible">
        <path :d="path" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
