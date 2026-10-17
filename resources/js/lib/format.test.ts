import { describe, expect, it } from 'vitest';
import { formatBytes, formatRelative, formatRuntime, formatSeconds } from './format';

describe('format', () => {
    it('formats runtimes', () => {
        expect(formatRuntime(null, 'en')).toBe('—');
        expect(formatRuntime(120, 'en')).toBe('120 ms');
        expect(formatRuntime(1250, 'en')).toBe('1.25 s');
        expect(formatRuntime(1250, 'fr')).toBe('1,25 s');
        expect(formatRuntime(125_000, 'en')).toBe('2 min 5 s');
    });

    it('formats durations in seconds', () => {
        expect(formatSeconds(45)).toBe('45 s');
        expect(formatSeconds(125)).toBe('2 min 5 s');
        expect(formatSeconds(3840)).toBe('1 h 4 min');
    });

    it('formats memory', () => {
        expect(formatBytes(2048, 'en')).toBe('2 KB');
        expect(formatBytes(3 * 1_048_576, 'en')).toBe('3 MB');
    });

    it('formats relative times in the visitor language', () => {
        const now = Date.UTC(2026, 0, 1, 12, 0, 0);

        expect(formatRelative(now / 1000 - 180, 'en', now)).toBe('3 minutes ago');
        expect(formatRelative(now / 1000 - 180, 'fr', now)).toBe('il y a 3 minutes');
        expect(formatRelative(null, 'en', now)).toBe('—');
    });
});
