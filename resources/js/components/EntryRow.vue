<script setup>
import { ref, computed } from 'vue';
import LevelBadge from './LevelBadge.vue';
import HighlightText from './HighlightText.vue';
import { formatTimestamp } from '../format.js';
import { api } from '../api.js';
import { levelColor, isHighSeverity } from '../levels.js';

const props = defineProps({
    entry: { type: Object, required: true },
    query: { type: String, default: '' },
    expanded: { type: Boolean, default: false },
    // Cross-file search results show which file each row came from.
    showFile: { type: Boolean, default: false },
    // Briefly ring + tint a row that just arrived (live tail) or was deep-linked.
    flash: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle', 'open-file']);

const copied = ref('');

const color = computed(() => levelColor(props.entry.level));
const high = computed(() => isHighSeverity(props.entry.level));

// Accent stripe + (for high-severity only) a faint row tint so CRITICAL/ERROR
// rows are impossible to miss while DEBUG/INFO recede.
const rowStyle = computed(() => ({
    borderLeftColor: color.value,
    backgroundColor: high.value ? color.value + '0f' : undefined,
}));

const hasDetail = computed(() => !!props.entry.context || !!props.entry.stack);

const prettyContext = computed(() =>
    props.entry.context ? JSON.stringify(props.entry.context, null, 2) : null,
);

const permalink = computed(() => {
    const prefix = api.config.prefix ?? '/logscope';
    return `${window.location.origin}${prefix}/files/${props.entry.file_id}/entries/${props.entry.id}`;
});

async function copy(text, label) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = label;
        setTimeout(() => (copied.value = ''), 1500);
    } catch (e) {}
}

function onKey(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        emit('toggle');
    }
}
</script>

<template>
    <div
        class="group relative border-b border-l-[3px] border-b-slate-100 transition-colors dark:border-b-slate-800/60"
        :class="[
            expanded ? 'bg-slate-50 dark:bg-slate-900/50' : 'hover:bg-slate-50/70 dark:hover:bg-slate-900/40',
            flash ? 'logscope-flash' : '',
        ]"
        :style="rowStyle"
    >
        <!-- Single-line summary -->
        <div
            class="flex cursor-pointer items-center gap-2.5 py-[3px] pl-2 pr-3 sm:gap-3"
            role="button"
            :aria-expanded="expanded"
            tabindex="0"
            @click="emit('toggle')"
            @keydown="onKey"
        >
            <svg
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                class="h-3 w-3 shrink-0 text-slate-300 transition-transform duration-150 dark:text-slate-600"
                :class="[expanded ? 'rotate-90' : '', hasDetail ? '' : 'invisible']"
            ><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>

            <LevelBadge :level="entry.level" class="shrink-0" />

            <time
                class="hidden shrink-0 font-mono text-[11px] tabular-nums text-slate-400 md:block dark:text-slate-500"
                :datetime="entry.logged_at"
            >{{ formatTimestamp(entry.logged_at) }}</time>

            <button
                v-if="showFile && entry.file_name"
                type="button"
                class="hidden shrink-0 items-center gap-1 rounded bg-slate-100 px-1.5 py-px font-mono text-[10px] text-slate-500 transition hover:bg-indigo-100 hover:text-indigo-700 sm:inline-flex dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-indigo-500/20 dark:hover:text-indigo-300"
                :title="`Open ${entry.file_name}`"
                @click.stop="emit('open-file', entry)"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M5 3h9l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                <span class="max-w-[120px] truncate">{{ entry.file_name }}</span>
            </button>

            <span
                class="min-w-0 flex-1 truncate font-sans text-[13px] leading-5 text-slate-700 dark:text-slate-200"
                :class="high ? 'font-medium' : ''"
            ><HighlightText :text="entry.message" :query="query" /></span>

            <span
                v-if="entry.channel"
                class="hidden shrink-0 truncate font-mono text-[11px] text-slate-400 transition-opacity group-hover:opacity-0 lg:block lg:max-w-[140px] dark:text-slate-500"
            >{{ entry.channel }}</span>

            <!-- Hover actions -->
            <div class="absolute right-2 top-1/2 hidden -translate-y-1/2 items-center gap-0.5 rounded-md bg-white/90 px-0.5 opacity-0 shadow-sm ring-1 ring-slate-200 backdrop-blur-sm transition-opacity group-hover:flex group-hover:opacity-100 dark:bg-slate-900/90 dark:ring-slate-700">
                <button
                    type="button"
                    class="inline-flex h-6 w-6 items-center justify-center rounded text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    :title="copied === 'message' ? 'Copied!' : 'Copy message'"
                    @click.stop="copy(entry.message, 'message')"
                >
                    <svg v-if="copied === 'message'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-3.5 w-3.5 text-emerald-500"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                </button>
                <button
                    type="button"
                    class="inline-flex h-6 w-6 items-center justify-center rounded text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    :title="copied === 'link' ? 'Copied!' : 'Copy link'"
                    @click.stop="copy(permalink, 'link')"
                >
                    <svg v-if="copied === 'link'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="h-3.5 w-3.5 text-emerald-500"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1m-1 8a5 5 0 0 1-7 0l-2-2a5 5 0 0 1 7-7l1 1"/></svg>
                </button>
            </div>
        </div>

        <!-- Expanded detail: tight inline monospace blocks, no heavy card -->
        <div v-if="expanded" class="logscope-fade-in space-y-2 pb-2.5 pl-7 pr-3 text-[12px]">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-[11px] text-slate-500 dark:text-slate-400">
                <span class="md:hidden"><span class="text-slate-400 dark:text-slate-600">time</span> {{ formatTimestamp(entry.logged_at) }}</span>
                <span v-if="showFile && entry.file_name"><span class="text-slate-400 dark:text-slate-600">file</span> {{ entry.file_name }}</span>
                <span v-if="entry.channel"><span class="text-slate-400 dark:text-slate-600">channel</span> {{ entry.channel }}</span>
                <span v-if="entry.execution_id"><span class="text-slate-400 dark:text-slate-600">exec</span> {{ entry.execution_id }}</span>
            </div>

            <pre v-if="prettyContext" class="overflow-x-auto whitespace-pre-wrap break-words border-l-2 border-slate-200 bg-slate-100/60 py-1.5 pl-3 font-mono text-[11.5px] leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300"><code>{{ prettyContext }}</code></pre>

            <pre v-if="entry.stack" class="max-h-72 overflow-auto whitespace-pre-wrap break-words border-l-2 border-rose-300 bg-rose-50/60 py-1.5 pl-3 font-mono text-[11.5px] leading-relaxed text-slate-600 dark:border-rose-500/40 dark:bg-rose-500/5 dark:text-slate-300"><code>{{ entry.stack }}</code></pre>
        </div>
    </div>
</template>
