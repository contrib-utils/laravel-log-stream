<script setup>
import { computed } from 'vue';
import { levelMeta, isHighSeverity } from '../levels.js';

const props = defineProps({
    level: { type: String, default: 'unknown' },
});

const meta = computed(() => levelMeta(props.level));
const high = computed(() => isHighSeverity(props.level));

// High-severity chips are solid (loud); low-severity are tinted (quiet) so the
// stream stays scannable. Fixed width keeps the level column aligned.
const style = computed(() =>
    high.value
        ? { backgroundColor: meta.value.color, color: '#fff' }
        : { backgroundColor: meta.value.color + '24', color: meta.value.color },
);
</script>

<template>
    <span
        class="inline-flex w-[4.25rem] items-center justify-center rounded px-1 py-px text-[10px] font-bold uppercase leading-4 tracking-wide tabular-nums"
        :style="style"
        :title="meta.label"
    >{{ meta.label }}</span>
</template>
