import { ref } from 'vue';

const isDark = ref(document.documentElement.classList.contains('dark'));

export function useTheme() {
    function toggle() {
        isDark.value = !isDark.value;
        document.documentElement.classList.toggle('dark', isDark.value);
        try {
            localStorage.setItem('logscope.theme', isDark.value ? 'dark' : 'light');
        } catch (e) {}
    }

    return { isDark, toggle };
}
