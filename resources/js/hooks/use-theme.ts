import { useCallback, useEffect, useState } from 'react';

export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'meridian.theme';

function readTheme(): Theme {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' ? stored : 'system';
    } catch {
        return 'system';
    }
}

export function applyTheme(theme: Theme): void {
    const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
}

/** Light, dark or system theme, remembered in the browser. */
export function useTheme(): { theme: Theme; setTheme: (theme: Theme) => void } {
    const [theme, setThemeState] = useState<Theme>(readTheme);

    useEffect(() => {
        applyTheme(theme);

        if (theme !== 'system') {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = () => applyTheme('system');
        media.addEventListener('change', listener);

        return () => media.removeEventListener('change', listener);
    }, [theme]);

    const setTheme = useCallback((next: Theme) => {
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Private browsing: the choice lasts until the page is closed.
        }

        setThemeState(next);
    }, []);

    return { theme, setTheme };
}
