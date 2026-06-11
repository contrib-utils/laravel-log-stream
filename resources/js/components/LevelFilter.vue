<script setup>
import { computed } from 'vue';
import { levelList } from '../levels.js';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

const levels = computed(() => levelList());

// Empty selection means "no filter" → every level is effectively shown.
const noFilter = computed(() => props.modelValue.length === 0);

// A level reads as "on" when explicitly selected, or when nothing is selected
// (all visible). It reads as "off/dimmed" only when a filter excludes it.
const isOn = (key) => noFilter.value || props.modelValue.includes(key);

function toggle(key) {
    const set = new Set(props.modelValue);
    set.has(key) ? set.delete(key) : set.add(key);
    emit('update:modelValue', [...set]);
}

function clear() {
    emit('update:modelValue', []);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Filter by level">
        <button
            v-for="lvl in levels"
            :key="lvl.key"
            type="button"
            class="group inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold uppercase leading-none tracking-wide transition"
            :class="isOn(lvl.key)
                ? 'border-transparent text-white'
                : 'border-slate-200 bg-transparent text-slate-400 opacity-60 hover:opacity-100 dark:border-slate-700 dark:text-slate-500'"
            :style="isOn(lvl.key) ? { backgroundColor: lvl.color } : {}"
            :aria-pressed="!noFilter && modelValue.includes(lvl.key)"
            @click="toggle(lvl.key)"
        >
            <span
                v-if="!isOn(lvl.key)"
                class="h-1.5 w-1.5 rounded-full"
                :style="{ backgroundColor: lvl.color }"
            ></span>
            {{ lvl.label }}
            <span
                v-if="counts[lvl.key]"
                class="rounded px-1 text-[10px] font-bold tabular-nums"
                :class="isOn(lvl.key) ? 'bg-black/20 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            >{{ counts[lvl.key] }}</span>
        </button>

        <button
            v-if="!noFilter"
            type="button"
            class="ml-0.5 inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-[11px] font-medium text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200"
            @click="clear"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="h-3 w-3"><path stroke-linecap="round" d="M6 6 18 18M18 6 6 18"/></svg>
            Clear
        </button>
    </div>
</template>
