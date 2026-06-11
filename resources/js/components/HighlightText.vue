<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, default: '' },
    query: { type: String, default: '' },
});

// Split the text into matched / unmatched segments. Rendered with text
// interpolation only (never v-html) so log content stays escaped.
const segments = computed(() => {
    const q = (props.query ?? '').trim();
    if (!q) {
        return [{ text: props.text, match: false }];
    }

    const out = [];
    const haystack = props.text ?? '';
    const lower = haystack.toLowerCase();
    const needle = q.toLowerCase();
    let i = 0;

    while (i < haystack.length) {
        const at = lower.indexOf(needle, i);
        if (at === -1) {
            out.push({ text: haystack.slice(i), match: false });
            break;
        }
        if (at > i) out.push({ text: haystack.slice(i, at), match: false });
        out.push({ text: haystack.slice(at, at + needle.length), match: true });
        i = at + needle.length;
    }

    return out;
});
</script>

<template>
    <span>
        <template v-for="(seg, idx) in segments" :key="idx"><mark
            v-if="seg.match"
            class="rounded bg-yellow-200 px-0.5 text-inherit dark:bg-yellow-500/40"
        >{{ seg.text }}</mark><template v-else>{{ seg.text }}</template></template>
    </span>
</template>
