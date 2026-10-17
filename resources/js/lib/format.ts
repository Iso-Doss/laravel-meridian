/**
 * Formatting helpers, in the visitor's language (Intl).
 */

export function formatNumber(value: number, locale: string): string {
    return new Intl.NumberFormat(locale).format(value);
}

export function formatCompact(value: number, locale: string): string {
    return new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

/** Milliseconds: "120 ms", "1.25 s", "2 min 5 s". */
export function formatRuntime(milliseconds: number | null, locale: string): string {
    if (milliseconds === null) {
        return '—';
    }

    if (milliseconds < 1000) {
        return `${formatNumber(milliseconds, locale)} ms`;
    }

    if (milliseconds < 60_000) {
        return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(milliseconds / 1000)} s`;
    }

    return formatSeconds(Math.round(milliseconds / 1000));
}

/** Seconds: "45 s", "2 min 5 s", "1 h 4 min". */
export function formatSeconds(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${seconds} s`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min ${seconds % 60} s`;
    }

    return `${Math.floor(seconds / 3600)} h ${Math.floor((seconds % 3600) / 60)} min`;
}

export function formatBytes(bytes: number, locale: string): string {
    const format = new Intl.NumberFormat(locale, { maximumFractionDigits: 1 });

    return bytes >= 1_048_576 ? `${format.format(bytes / 1_048_576)} MB` : `${format.format(Math.round(bytes / 1024))} KB`;
}

/** Unix timestamp to a full date and time. */
export function formatDateTime(timestamp: number | null, locale: string): string {
    if (timestamp === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'medium' }).format(timestamp * 1000);
}

export function formatTime(timestamp: number, locale: string): string {
    return new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(timestamp * 1000);
}

/** Unix timestamp to "3 minutes ago" / "il y a 3 minutes". */
export function formatRelative(timestamp: number | null, locale: string, now: number = Date.now()): string {
    if (timestamp === null) {
        return '—';
    }

    const seconds = Math.round(timestamp - now / 1000);
    const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['day', 86_400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return format.format(Math.round(seconds / size), unit);
        }
    }

    return format.format(seconds, 'second');
}
