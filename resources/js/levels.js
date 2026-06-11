import { api } from './api.js';

// Canonical level metadata supplied by the backend (config/logscope.php).
// The insertion order is the severity order: debug -> emergency.
const LEVELS = api.config.levels ?? {};

const ORDER = Object.keys(LEVELS);

// Levels that must visually shout while scrolling a dense stream.
const HIGH_SEVERITY = new Set(['error', 'critical', 'alert', 'emergency']);

const FALLBACK = { label: 'Unknown', color: '#9ca3af' };

export function levelMeta(key) {
    return LEVELS[key] ?? { label: key || FALLBACK.label, color: FALLBACK.color };
}

export function levelColor(key) {
    return levelMeta(key).color ?? FALLBACK.color;
}

export function severityRank(key) {
    const i = ORDER.indexOf(key);
    return i === -1 ? -1 : i;
}

export function isHighSeverity(key) {
    return HIGH_SEVERITY.has(key);
}

export function levelList() {
    return ORDER.map((key) => ({ key, ...levelMeta(key) }));
}
