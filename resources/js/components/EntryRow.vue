<script setup>
import { ref, computed } from 'vue';
import LevelBadge from './LevelBadge.vue';
import HighlightText from './HighlightText.vue';
import { formatTimestamp } from '../format.js';
import { api } from '../api.js';

const props = defineProps({
    entry: { type: Object, required: true },
    query: { type: String, default: '' },
    expanded: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle']);

const copied = ref('');

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
    <div class="border-b border-gray-100 dark:border-gray-800">
        <div
            class="flex cursor-pointer items-start gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-900/60"
            role="button"
            :aria-expanded="expanded"
            tabindex="0"
            @click="emit('toggle')"
            @keydown="onKey"
        >
            <LevelBadge :level="entry.level" class="mt-0.5 shrink-0" />
            <time class="mt-0.5 shrink-0 font-mono text-xs text-gray-400" :datetime="entry.logged_at">
                {{ formatTimestamp(entry.logged_at) }}
            </time>
            <div class="min-w-0 flex-1">
                <p class="truncate font-mono text-sm" :class="expanded ? 'whitespace-pre-wrap break-words' : ''">
                    <HighlightText :text="entry.message" :query="query" />
                </p>
            </div>
            <span class="mt-0.5 shrink-0 text-gray-300 dark:text-gray-600">{{ expanded ? '▾' : '▸' }}</span>
        </div>

        <div v-if="expanded" class="space-y-3 bg-gray-50 px-3 py-3 dark:bg-gray-900/40">
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <span v-if="entry.channel" class="rounded bg-gray-200 px-1.5 py-0.5 dark:bg-gray-800">{{ entry.channel }}</span>
                <span v-if="entry.execution_id" class="rounded bg-gray-200 px-1.5 py-0.5 font-mono dark:bg-gray-800">exec: {{ entry.execution_id }}</span>
                <button type="button" class="ml-auto rounded px-2 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-800" @click.stop="copy(entry.message, 'message')">
                    {{ copied === 'message' ? 'Copied!' : 'Copy message' }}
                </button>
                <button type="button" class="rounded px-2 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-800" @click.stop="copy(permalink, 'link')">
                    {{ copied === 'link' ? 'Copied!' : 'Copy link' }}
                </button>
            </div>

            <div v-if="prettyContext">
                <h4 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Context</h4>
                <pre class="overflow-x-auto rounded bg-white p-2 text-xs dark:bg-black/30"><code>{{ prettyContext }}</code></pre>
            </div>

            <div v-if="entry.stack">
                <h4 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Stack trace</h4>
                <pre class="max-h-80 overflow-auto rounded bg-white p-2 text-xs dark:bg-black/30"><code>{{ entry.stack }}</code></pre>
            </div>
        </div>
    </div>
</template>
