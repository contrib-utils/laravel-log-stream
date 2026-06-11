<script setup>
import { computed } from 'vue';
import { api } from '../api.js';

const props = defineProps({
    level: { type: String, default: 'unknown' },
});

const levels = api.config.levels ?? {};

const meta = computed(() => levels[props.level] ?? { label: props.level, color: '#9ca3af' });
</script>

<template>
    <span
        class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white"
        :style="{ backgroundColor: meta.color }"
        :title="meta.label"
    >{{ meta.label }}</span>
</template>
