<script setup>
import { formatBytes, formatRelative } from '../format.js';

defineProps({
    sources: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    selectedSource: { type: String, default: null },
    selectedFileId: { type: String, default: null },
    loadingFiles: { type: Boolean, default: false },
});
const emit = defineEmits(['select-source', 'select-file']);
</script>

<template>
    <nav class="flex h-full flex-col overflow-y-auto p-3" aria-label="Sources and files">
        <h2 class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Sources</h2>
        <ul class="mt-2 space-y-0.5 text-sm" role="list">
            <li v-for="src in sources" :key="src.key">
                <button
                    type="button"
                    class="w-full rounded-md px-2 py-1.5 text-left font-medium"
                    :class="src.key === selectedSource
                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800'"
                    :aria-current="src.key === selectedSource ? 'true' : undefined"
                    @click="emit('select-source', src.key)"
                >{{ src.label }}</button>
            </li>
        </ul>

        <h2 class="mt-5 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Files</h2>
        <p v-if="loadingFiles" class="mt-2 px-2 text-sm text-gray-400">Loading…</p>
        <p v-else-if="!files.length" class="mt-2 px-2 text-sm text-gray-400">No files found.</p>
        <ul v-else class="mt-2 space-y-0.5" role="list">
            <li v-for="file in files" :key="file.id">
                <button
                    type="button"
                    class="w-full rounded-md px-2 py-1.5 text-left"
                    :class="file.id === selectedFileId
                        ? 'bg-blue-50 dark:bg-blue-500/10'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800'"
                    :aria-current="file.id === selectedFileId ? 'true' : undefined"
                    :title="file.path"
                    @click="emit('select-file', file)"
                >
                    <span class="block truncate text-sm font-medium" :class="file.readable ? '' : 'text-gray-400 line-through'">
                        {{ file.name }}
                    </span>
                    <span class="mt-0.5 flex justify-between text-[11px] text-gray-400">
                        <span>{{ formatBytes(file.size) }}</span>
                        <span>{{ formatRelative(file.mtime) }}</span>
                    </span>
                </button>
            </li>
        </ul>
    </nav>
</template>
