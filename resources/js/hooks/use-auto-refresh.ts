import { usePage, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { SharedProps } from '@/types';

const STORAGE_KEY = 'meridian.poll';
const EVENT = 'meridian:auto-refresh';

export function isAutoRefreshEnabled(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) !== '0';
    } catch {
        return true;
    }
}

/** The auto-refresh switch, shared by every component through a window event. */
export function useAutoRefreshSetting(): [boolean, (enabled: boolean) => void] {
    const [enabled, setEnabledState] = useState(isAutoRefreshEnabled);

    useEffect(() => {
        const listener = () => setEnabledState(isAutoRefreshEnabled());
        window.addEventListener(EVENT, listener);

        return () => window.removeEventListener(EVENT, listener);
    }, []);

    const setEnabled = useCallback((next: boolean) => {
        try {
            localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
        } catch {
            // Ignored: the setting is then only kept in memory.
        }

        setEnabledState(next);
        window.dispatchEvent(new Event(EVENT));
    }, []);

    return [enabled, setEnabled];
}

/** Reload the page props every "meridian.poll" seconds while enabled. */
export function useAutoRefresh(only?: string[]): void {
    const { poll } = usePage<SharedProps>().props.meridian;
    const [enabled] = useAutoRefreshSetting();
    const { start, stop } = usePoll(Math.max(1, poll) * 1000, only ? { only } : {}, { autoStart: false });

    useEffect(() => {
        if (enabled && poll > 0) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [enabled, poll, start, stop]);
}
