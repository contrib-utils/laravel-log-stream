<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { api } from './api.js';
import { useTheme } from './useTheme.js';
import Sidebar from './components/Sidebar.vue';
import LevelFilter from './components/LevelFilter.vue';
import EntryList from './components/EntryList.vue';

const { isDark, toggle: toggleTheme } = useTheme();

// --- State -------------------------------------------------------------------
const sources = ref([]);
const selectedSource = ref(null);

const files = ref([]);
const selectedFile = ref(null);
const loadingFiles = ref(false);

const entries = ref([]);
const cursor = ref(null);
const hasMore = ref(false);
const loadingEntries = ref(false);

const levels = ref([]);
const q = ref('');
const debouncedQ = ref('');

const railOpen = ref(false);
const error = ref(null);

// Guards against stale async responses clobbering newer ones.
let entriesToken = 0;
let searchTimer = null;

const searchInput = ref(null);

const emptyEntries = computed(
    () => selectedFile.value && !entries.value.length && !loadingEntries.value,
);

// Per-level tallies across the currently loaded entries, surfaced on the
// level-filter toggles so each level shows how many rows it contributes.
const levelCounts = computed(() => {
    const m = {};
    for (const e of entries.value) m[e.level] = (m[e.level] ?? 0) + 1;
    return m;
});

// --- URL state ---------------------------------------------------------------
function readUrl() {
    const p = new URLSearchParams(window.location.search);
    return {
        source: p.get('source'),
        file: p.get('file'),
        q: p.get('q') ?? '',
        levels: p.get('levels') ? p.get('levels').split(',').filter(Boolean) : [],
    };
}

function writeUrl() {
    const p = new URLSearchParams();
    if (selectedSource.value) p.set('source', selectedSource.value);
    if (selectedFile.value) p.set('file', selectedFile.value.id);
    if (debouncedQ.value) p.set('q', debouncedQ.value);
    if (levels.value.length) p.set('levels', levels.value.join(','));
    const qs = p.toString();
    window.history.replaceState(null, '', `${window.location.pathname}${qs ? '?' + qs : ''}`);
}

// --- Data loading ------------------------------------------------------------
async function loadSources(urlState) {
    try {
        sources.value = await api.sources();
        const wanted = sources.value.find((s) => s.key === urlState.source);
        selectedSource.value = (wanted ?? sources.value[0])?.key ?? null;
        if (selectedSource.value) await loadFiles(urlState.file);
    } catch (e) {
        error.value = 'Failed to load sources.';
    }
}

async function loadFiles(wantedFileId = null) {
    if (!selectedSource.value) return;
    loadingFiles.value = true;
    selectedFile.value = null;
    try {
        files.value = await api.files(selectedSource.value);
        const wanted = files.value.find((f) => f.id === wantedFileId);
        selectedFile.value = wanted ?? files.value[0] ?? null;
        await reloadEntries();
    } catch (e) {
        error.value = 'Failed to load files.';
    } finally {
        loadingFiles.value = false;
    }
}

async function reloadEntries() {
    entries.value = [];
    cursor.value = null;
    hasMore.value = !!selectedFile.value;
    writeUrl();
    await loadEntries(true);
}

async function loadEntries(reset = false) {
    if (!selectedFile.value || loadingEntries.value) return;
    if (!reset && !hasMore.value) return;

    const token = ++entriesToken;
    loadingEntries.value = true;
    try {
        const res = await api.entries(selectedFile.value.id, {
            cursor: reset ? null : cursor.value,
            level: levels.value,
            q: debouncedQ.value,
        });
        if (token !== entriesToken) return; // a newer request superseded this one
        entries.value = reset ? res.data : [...entries.value, ...res.data];
        cursor.value = res.meta?.next_cursor ?? null;
        hasMore.value = cursor.value !== null;
    } catch (e) {
        if (token === entriesToken) error.value = 'Failed to load entries.';
    } finally {
        if (token === entriesToken) loadingEntries.value = false;
    }
}

// --- Interactions ------------------------------------------------------------
function selectSource(key) {
    if (key === selectedSource.value) return;
    selectedSource.value = key;
    loadFiles();
}

function selectFile(file) {
    selectedFile.value = file;
    railOpen.value = false;
    reloadEntries();
}

watch(levels, () => reloadEntries());

watch(q, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        debouncedQ.value = val.trim();
    }, 300);
});

watch(debouncedQ, () => reloadEntries());

// Global keyboard: '/' focuses search.
function onGlobalKey(e) {
    if (e.key === '/' && document.activeElement?.tagName !== 'INPUT') {
        e.preventDefault();
        searchInput.value?.focus();
    }
}

onMounted(() => {
    const urlState = readUrl();
    q.value = urlState.q;
    debouncedQ.value = urlState.q;
    levels.value = urlState.levels;
    window.addEventListener('keydown', onGlobalKey);
    loadSources(urlState);
});
</script>

<template>
    <div class="flex h-full flex-col bg-slate-50 text-slate-800 dark:bg-[#0a0e16] dark:text-slate-200">
        <!-- Top bar -->
        <header class="relative z-30 flex h-14 shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/80 px-3 backdrop-blur-md sm:px-4 dark:border-slate-800/80 dark:bg-slate-900/70">
            <button
                class="-ml-1 inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 md:hidden dark:text-slate-400 dark:hover:bg-slate-800"
                aria-label="Toggle navigation"
                @click="railOpen = !railOpen"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <span class="flex items-center gap-2.5 font-semibold tracking-tight">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm shadow-indigo-500/30">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 12h16M4 18h7"/></svg>
                </span>
                <span class="text-[15px]">LogScope</span>
            </span>

            <div class="ml-auto flex items-center gap-2">
                <div class="relative hidden sm:block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
                    <input
                        ref="searchInput"
                        v-model="q"
                        type="search"
                        placeholder="Search this file…"
                        class="w-52 rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-9 text-sm text-slate-700 placeholder-slate-400 transition focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 md:w-72 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:focus:bg-slate-800"
                        aria-label="Search log entries"
                    />
                    <kbd class="pointer-events-none absolute right-2.5 top-1/2 hidden -translate-y-1/2 rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-400 md:block dark:border-slate-700 dark:bg-slate-900">/</kbd>
                </div>
                <button
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                    :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    @click="toggleTheme"
                >
                    <svg v-if="isDark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                </button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- Left rail -->
            <aside
                class="absolute inset-y-0 left-0 z-20 w-72 shrink-0 transform border-r border-slate-200/80 bg-white pt-14 shadow-xl transition-transform duration-200 ease-out md:static md:z-auto md:w-64 md:translate-x-0 md:pt-0 md:shadow-none dark:border-slate-800/80 dark:bg-slate-900/40"
                :class="railOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <Sidebar
                    :sources="sources"
                    :files="files"
                    :selected-source="selectedSource"
                    :selected-file-id="selectedFile?.id ?? null"
                    :loading-files="loadingFiles"
                    @select-source="selectSource"
                    @select-file="selectFile"
                />
            </aside>

            <div v-if="railOpen" class="absolute inset-0 z-10 bg-slate-900/40 backdrop-blur-sm md:hidden" @click="railOpen = false"></div>

            <!-- Main pane -->
            <main class="flex min-w-0 flex-1 flex-col">
                <!-- Search (mobile) + filters -->
                <div class="flex flex-wrap items-center gap-3 border-b border-slate-200/80 bg-white/60 px-3 py-2.5 sm:px-4 dark:border-slate-800/80 dark:bg-slate-900/30">
                    <div class="relative w-full sm:hidden">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
                        <input
                            v-model="q"
                            type="search"
                            placeholder="Search this file…"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800/60"
                            aria-label="Search log entries"
                        />
                    </div>
                    <LevelFilter v-model="levels" :counts="levelCounts" />
                    <div class="ml-auto flex min-w-0 items-center gap-2">
                        <span v-if="entries.length" class="shrink-0 font-mono text-[11px] tabular-nums text-slate-400">
                            {{ entries.length }}{{ hasMore ? '+' : '' }} entries
                        </span>
                        <span
                            v-if="selectedFile"
                            class="inline-flex max-w-[45vw] items-center gap-1.5 truncate rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-500 dark:bg-slate-800/70 dark:text-slate-400"
                            :title="selectedFile.path"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4a1 1 0 0 0 1 1h4M5 3h9l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                            <span class="truncate">{{ selectedFile.name }}</span>
                        </span>
                    </div>
                </div>

                <div v-if="error" class="flex items-center gap-2 border-b border-red-200/60 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
                    {{ error }}
                </div>

                <div v-if="!selectedFile && !loadingFiles" class="flex flex-1 items-center justify-center p-6 text-center">
                    <div class="logscope-fade-in">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 text-slate-400 ring-1 ring-slate-200 dark:from-slate-800 dark:to-slate-800/50 dark:text-slate-500 dark:ring-slate-700">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-8 w-8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No file selected</p>
                        <p class="mt-1 text-sm text-slate-400">Pick a log file from the sidebar to get started.</p>
                    </div>
                </div>

                <EntryList
                    v-else
                    class="min-h-0 flex-1"
                    :entries="entries"
                    :query="debouncedQ"
                    :loading="loadingEntries"
                    :has-more="hasMore"
                    :empty="emptyEntries"
                    @load-more="loadEntries(false)"
                />
            </main>
        </div>
    </div>
</template>
