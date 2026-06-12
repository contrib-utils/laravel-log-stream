<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, watch } from 'vue';
import EntryRow from './EntryRow.vue';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    query: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    hasMore: { type: Boolean, default: false },
    empty: { type: Boolean, default: false },
    // Cross-file search shows the originating file per row.
    showFile: { type: Boolean, default: false },
    // Entry ids to briefly highlight (just-arrived live rows, deep-link target).
    flashIds: { type: Array, default: () => [] },
    // Count of newly-arrived entries waiting to be revealed (live tail).
    newCount: { type: Number, default: 0 },
    // A deep-linked entry pinned above the stream.
    pinnedEntry: { type: Object, default: null },
});
const emit = defineEmits(['load-more', 'apply-new', 'open-file', 'dismiss-pinned', 'top-change']);

const expanded = reactive(new Set());
const sentinel = ref(null);
const scroller = ref(null);
const atTop = ref(true);
let observer = null;

function toggle(id) {
    expanded.has(id) ? expanded.delete(id) : expanded.add(id);
}

function onScroll() {
    const el = scroller.value;
    if (!el) return;
    const top = el.scrollTop < 24;
    if (top !== atTop.value) {
        atTop.value = top;
        emit('top-change', top);
    }
}

function scrollToTop() {
    scroller.value?.scrollTo({ top: 0, behavior: 'smooth' });
}

defineExpose({ scrollToTop });

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
    <div class="relative flex h-full flex-col">
        <!-- New-entries pill (live tail) -->
        <transition name="logscope-pill">
            <button
                v-if="newCount > 0"
                type="button"
                class="absolute left-1/2 top-2 z-20 inline-flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-indigo-600 py-1.5 pl-3 pr-3.5 text-xs font-semibold text-white shadow-lg shadow-indigo-600/30 ring-1 ring-white/20 transition hover:bg-indigo-500"
                aria-live="polite"
                @click="emit('apply-new')"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="m6 15 6-6 6 6"/></svg>
                {{ newCount }} new {{ newCount === 1 ? 'entry' : 'entries' }}
            </button>
        </transition>

        <!-- Jump to top -->
        <transition name="logscope-fade">
            <button
                v-if="!atTop && entries.length"
                type="button"
                class="absolute bottom-4 right-4 z-20 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white text-slate-500 shadow-lg ring-1 ring-slate-200 transition hover:text-indigo-600 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:text-indigo-400"
                aria-label="Scroll to top"
                @click="scrollToTop"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0-6 6m6-6 6 6"/></svg>
            </button>
        </transition>

        <!-- Column header — aligns with EntryRow's fixed-width columns. -->
        <div
            v-if="entries.length || loading || pinnedEntry"
            class="flex shrink-0 items-center gap-2.5 border-b border-slate-200/80 border-l-[3px] border-l-transparent bg-slate-100/70 py-1 pl-2 pr-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 sm:gap-3 dark:border-slate-800 dark:bg-slate-900/50"
        >
            <span class="w-3 shrink-0"></span>
            <span class="w-[4.25rem] shrink-0">Level</span>
            <span class="hidden shrink-0 md:block">Time</span>
            <span class="flex-1">Message</span>
        </div>

        <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto" role="log" aria-live="polite" aria-label="Log entries" @scroll.passive="onScroll">
            <!-- Deep-linked entry, pinned above the stream -->
            <div v-if="pinnedEntry" class="border-b border-indigo-200/70 bg-indigo-50/50 dark:border-indigo-500/20 dark:bg-indigo-500/5">
                <div class="flex items-center justify-between px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-300">
                    <span class="inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1m-1 8a5 5 0 0 1-7 0l-2-2a5 5 0 0 1 7-7l1 1"/></svg>
                        Linked entry
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 normal-case tracking-normal text-indigo-500 transition hover:bg-indigo-100 hover:text-indigo-700 dark:hover:bg-indigo-500/20"
                        @click="emit('dismiss-pinned')"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="h-3 w-3"><path stroke-linecap="round" d="M6 6 18 18M18 6 6 18"/></svg>
                        Dismiss
                    </button>
                </div>
                <EntryRow
                    :entry="pinnedEntry"
                    :query="query"
                    :show-file="showFile"
                    :expanded="expanded.has(pinnedEntry.id)"
                    flash
                    @toggle="toggle(pinnedEntry.id)"
                    @open-file="emit('open-file', $event)"
                />
            </div>

            <EntryRow
                v-for="entry in entries"
                :key="entry.id"
                :entry="entry"
                :query="query"
                :show-file="showFile"
                :flash="flashIds.includes(entry.id)"
                :expanded="expanded.has(entry.id)"
                @toggle="toggle(entry.id)"
                @open-file="emit('open-file', $event)"
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
