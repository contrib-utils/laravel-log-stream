<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, watch } from 'vue';
import EntryRow from './EntryRow.vue';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    query: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    hasMore: { type: Boolean, default: false },
    empty: { type: Boolean, default: false },
});
const emit = defineEmits(['load-more']);

const expanded = reactive(new Set());
const sentinel = ref(null);
let observer = null;

function toggle(id) {
    expanded.has(id) ? expanded.delete(id) : expanded.add(id);
}

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && props.hasMore && !props.loading) {
            emit('load-more');
        }
    }, { rootMargin: '200px' });

    if (sentinel.value) observer.observe(sentinel.value);
});

onBeforeUnmount(() => observer?.disconnect());

// Collapse all when the underlying file/filter changes (entries replaced).
watch(() => props.empty, () => expanded.clear());
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Column header — aligns with EntryRow's fixed-width columns. -->
        <div
            v-if="entries.length || loading"
            class="flex shrink-0 items-center gap-2.5 border-b border-slate-200/80 border-l-[3px] border-l-transparent bg-slate-100/70 py-1 pl-2 pr-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 sm:gap-3 dark:border-slate-800 dark:bg-slate-900/50"
        >
            <span class="w-3 shrink-0"></span>
            <span class="w-[4.25rem] shrink-0">Level</span>
            <span class="hidden shrink-0 md:block">Time</span>
            <span class="flex-1">Message</span>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto" role="log" aria-live="polite" aria-label="Log entries">
            <EntryRow
                v-for="entry in entries"
                :key="entry.id"
                :entry="entry"
                :query="query"
                :expanded="expanded.has(entry.id)"
                @toggle="toggle(entry.id)"
            />

            <div v-if="empty && !loading" class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800/70 dark:text-slate-500">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-6 w-6"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
                </div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No matching entries</p>
                <p class="mt-1 text-sm text-slate-400">Try adjusting your search or level filters.</p>
            </div>

            <div ref="sentinel" class="h-px"></div>

            <div v-if="loading" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                <div v-for="n in 10" :key="n" class="flex animate-pulse items-center gap-2.5 py-[5px] pl-2 pr-3 sm:gap-3">
                    <span class="w-3 shrink-0"></span>
                    <div class="h-3.5 w-[4.25rem] shrink-0 rounded bg-slate-200 dark:bg-slate-800"></div>
                    <div class="hidden h-3 w-28 shrink-0 rounded bg-slate-100 md:block dark:bg-slate-800/60"></div>
                    <div class="h-3 rounded bg-slate-100 dark:bg-slate-800/60" :style="{ width: (40 + (n * 13) % 45) + '%' }"></div>
                </div>
            </div>
            <div v-else-if="!hasMore && entries.length" class="flex items-center justify-center gap-2 py-4 text-[11px] font-medium uppercase tracking-wider text-slate-300 dark:text-slate-600">
                <span class="h-px w-8 bg-slate-200 dark:bg-slate-800"></span>
                End of log
                <span class="h-px w-8 bg-slate-200 dark:bg-slate-800"></span>
            </div>
        </div>
    </div>
</template>
