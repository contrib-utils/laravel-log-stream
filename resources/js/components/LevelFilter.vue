<script setup>
import { computed } from 'vue';
import { api } from '../api.js';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

const levels = computed(() => Object.entries(api.config.levels ?? {}).map(([key, meta]) => ({
    key,
    label: meta.label ?? key,
    color: meta.color ?? '#9ca3af',
})));

function toggle(key) {
    const set = new Set(props.modelValue);
    set.has(key) ? set.delete(key) : set.add(key);
    emit('update:modelValue', [...set]);
}

const isActive = (key) => props.modelValue.includes(key);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Filter by level">
        <button
            v-for="lvl in levels"
            :key="lvl.key"
            type="button"
            class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide transition"
            :class="isActive(lvl.key)
                ? 'border-transparent text-white'
                : 'border-gray-300 text-gray-500 hover:border-gray-400 dark:border-gray-700 dark:text-gray-400'"
            :style="isActive(lvl.key) ? { backgroundColor: lvl.color } : {}"
            :aria-pressed="isActive(lvl.key)"
            @click="toggle(lvl.key)"
        >{{ lvl.label }}</button>
    </div>
</template>
