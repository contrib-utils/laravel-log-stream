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
    <div class="flex h-full flex-col bg-gray-50 text-gray-900 dark:bg-[#0b0f17] dark:text-gray-100">
        <!-- Top bar -->
        <header class="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 px-3 dark:border-gray-800">
            <button
                class="rounded-md p-2 hover:bg-gray-200 md:hidden dark:hover:bg-gray-800"
                aria-label="Toggle navigation"
                @click="railOpen = !railOpen"
            >☰</button>

            <span class="flex items-center gap-2 font-semibold">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                LogScope
            </span>

            <select class="ml-2 rounded-md border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-900" aria-label="Host">
                <option>Local</option>
            </select>

            <div class="ml-auto flex items-center gap-3">
                <input
                    ref="searchInput"
                    v-model="q"
                    type="search"
                    placeholder="Search this file…  (/)"
                    class="hidden w-48 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm placeholder-gray-400 sm:block md:w-64 dark:border-gray-700 dark:bg-gray-900"
                    aria-label="Search log entries"
                />
                <button
                    class="rounded-md p-2 text-lg leading-none hover:bg-gray-200 dark:hover:bg-gray-800"
                    :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    @click="toggleTheme"
                >{{ isDark ? '☀' : '☾' }}</button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- Left rail -->
            <aside
                class="absolute inset-y-0 left-0 z-20 w-64 shrink-0 transform border-r border-gray-200 bg-white pt-14 transition-transform md:static md:z-auto md:translate-x-0 md:pt-0 dark:border-gray-800 dark:bg-gray-950"
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

            <div v-if="railOpen" class="absolute inset-0 z-10 bg-black/30 md:hidden" @click="railOpen = false"></div>

            <!-- Main pane -->
            <main class="flex min-w-0 flex-1 flex-col">
                <!-- Search (mobile) + filters -->
                <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 px-3 py-2 dark:border-gray-800">
                    <input
                        v-model="q"
                        type="search"
                        placeholder="Search this file…"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm sm:hidden dark:border-gray-700 dark:bg-gray-900"
                        aria-label="Search log entries"
                    />
                    <LevelFilter v-model="levels" />
                    <span v-if="selectedFile" class="ml-auto truncate text-xs text-gray-400" :title="selectedFile.path">
                        {{ selectedFile.name }}
                    </span>
                </div>

                <div v-if="error" class="bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300" role="alert">
                    {{ error }}
                </div>

                <div v-if="!selectedFile && !loadingFiles" class="flex flex-1 items-center justify-center p-6 text-center">
                    <div>
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gray-200 text-2xl dark:bg-gray-800">📂</div>
                        <p class="text-sm text-gray-500">Select a file to view its log entries.</p>
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
