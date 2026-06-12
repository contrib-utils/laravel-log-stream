<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    danger: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);

const confirmBtn = ref(null);

function onKey(e) {
    if (e.key === 'Escape') emit('cancel');
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            window.addEventListener('keydown', onKey);
            nextTick(() => confirmBtn.value?.focus());
        } else {
            window.removeEventListener('keydown', onKey);
        }
    },
);

onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <teleport to="body">
        <transition name="logscope-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
            >
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="emit('cancel')"></div>

                <div class="logscope-fade-in relative w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                            :class="danger ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400'"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ title }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ message }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="emit('cancel')"
                        >Cancel</button>
                        <button
                            ref="confirmBtn"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-white transition disabled:opacity-60"
                            :class="danger ? 'bg-rose-600 hover:bg-rose-500' : 'bg-indigo-600 hover:bg-indigo-500'"
                            :disabled="busy"
                            @click="emit('confirm')"
                        >
                            <svg v-if="busy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="logscope-spin h-4 w-4"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/></svg>
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
