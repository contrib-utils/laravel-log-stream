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
        <div class="min-h-0 flex-1 overflow-y-auto" role="log" aria-live="polite" aria-label="Log entries">
            <EntryRow
                v-for="entry in entries"
                :key="entry.id"
                :entry="entry"
                :query="query"
                :expanded="expanded.has(entry.id)"
                @toggle="toggle(entry.id)"
            />

            <div v-if="empty && !loading" class="p-10 text-center text-sm text-gray-400">
                No matching entries.
            </div>

            <div ref="sentinel" class="h-px"></div>

            <div v-if="loading" class="p-4 text-center text-sm text-gray-400">Loading…</div>
            <div v-else-if="!hasMore && entries.length" class="p-4 text-center text-xs text-gray-300 dark:text-gray-600">
                — end of log —
            </div>
        </div>
    </div>
</template>
