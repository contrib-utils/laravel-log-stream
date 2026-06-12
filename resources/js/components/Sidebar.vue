<script setup>
import { ref } from 'vue';
import { api } from '../api.js';
import { formatBytes, formatRelative } from '../format.js';

defineProps({
    sources: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    selectedSource: { type: String, default: null },
    selectedFileId: { type: String, default: null },
    loadingFiles: { type: Boolean, default: false },
    allowFileOps: { type: Boolean, default: false },
});
const emit = defineEmits(['select-source', 'select-file', 'clear-file', 'delete-file']);

// Id of the file whose actions menu is open (only one at a time).
const menuFor = ref(null);

function toggleMenu(id) {
    menuFor.value = menuFor.value === id ? null : id;
}

function act(event, file) {
    menuFor.value = null;
    emit(event, file);
}
</script>

<template>
    <nav class="flex h-full flex-col overflow-y-auto px-2 py-3" aria-label="Sources and files">
        <h2 class="flex items-center gap-1.5 px-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3"><ellipse cx="12" cy="6" rx="8" ry="3"/><path stroke-linecap="round" d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>
            Sources
        </h2>
        <ul class="mt-1 space-y-0.5 text-[13px]" role="list">
            <li v-for="src in sources" :key="src.key">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left font-medium transition"
                    :class="src.key === selectedSource
                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800/70'"
                    :aria-current="src.key === selectedSource ? 'true' : undefined"
                    @click="emit('select-source', src.key)"
                >
                    <span
                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                        :class="src.key === selectedSource ? 'bg-indigo-500' : 'bg-slate-300 dark:bg-slate-600'"
                    ></span>
                    <span class="truncate">{{ src.label }}</span>
                </button>
            </li>
        </ul>

        <h2 class="mt-4 flex items-center gap-1.5 px-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M5 3h9l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
            Files
            <span v-if="!loadingFiles && files.length" class="ml-auto rounded bg-slate-100 px-1.5 text-[10px] font-semibold tabular-nums text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ files.length }}</span>
        </h2>

        <div v-if="loadingFiles" class="mt-1 space-y-1">
            <div v-for="n in 6" :key="n" class="animate-pulse rounded-md px-2 py-1.5">
                <div class="h-2.5 w-3/4 rounded bg-slate-200 dark:bg-slate-800"></div>
                <div class="mt-1.5 h-2 w-1/2 rounded bg-slate-100 dark:bg-slate-800/60"></div>
            </div>
        </div>
        <p v-else-if="!files.length" class="mt-2 px-2 text-[13px] text-slate-400">No files found.</p>
        <ul v-else class="mt-1 space-y-px" role="list">
            <li v-for="file in files" :key="file.id" class="group/file relative">
                <div
                    class="flex items-center rounded-md border-l-2 border-transparent pr-1 transition"
                    :class="file.id === selectedFileId
                        ? 'border-l-indigo-500 bg-indigo-50 dark:bg-indigo-500/10'
                        : 'hover:bg-slate-100 dark:hover:bg-slate-800/70'"
                >
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 items-center px-2 py-1 text-left"
                        :aria-current="file.id === selectedFileId ? 'true' : undefined"
                        :title="file.path"
                        @click="emit('select-file', file)"
                    >
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-[13px] font-medium leading-5"
                                :class="[
                                    file.readable ? '' : 'text-slate-400 line-through',
                                    file.id === selectedFileId ? 'text-indigo-700 dark:text-indigo-200' : 'text-slate-700 dark:text-slate-200',
                                ]"
                            >{{ file.name }}</span>
                            <span class="flex items-center gap-1.5 font-mono text-[10px] leading-4 text-slate-400">
                                <span>{{ formatBytes(file.size) }}</span>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <span>{{ formatRelative(file.mtime) }}</span>
                            </span>
                        </span>
                    </button>

                    <!-- Per-file actions -->
                    <button
                        v-if="allowFileOps"
                        type="button"
                        class="inline-flex h-7 w-6 shrink-0 items-center justify-center rounded text-slate-400 opacity-0 transition hover:bg-slate-200/70 hover:text-slate-700 focus:opacity-100 group-hover/file:opacity-100 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                        :class="menuFor === file.id ? 'opacity-100' : ''"
                        :aria-label="`Actions for ${file.name}`"
                        aria-haspopup="menu"
                        :aria-expanded="menuFor === file.id"
                        @click.stop="toggleMenu(file.id)"
                    >
                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                    </button>
                </div>

                <!-- Actions menu -->
                <template v-if="menuFor === file.id">
                    <div class="fixed inset-0 z-30" @click="menuFor = null"></div>
                    <div
                        class="absolute right-1 top-full z-40 mt-0.5 w-40 overflow-hidden rounded-lg bg-white py-1 shadow-lg ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"
                        role="menu"
                    >
                        <a
                            :href="api.downloadUrl(file.id)"
                            class="flex items-center gap-2 px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/70"
                            role="menuitem"
                            @click="menuFor = null"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
                            Download
                        </a>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/70"
                            role="menuitem"
                            @click="act('clear-file', file)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-7 4v6m4-6v6"/></svg>
                            Clear log
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                            role="menuitem"
                            @click="act('delete-file', file)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1 13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1L6 7Zm3 0V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            Delete file
                        </button>
                    </div>
                </template>
            </li>
        </ul>
    </nav>
</template>
