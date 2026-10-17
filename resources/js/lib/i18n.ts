import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export type Replacements = Record<string, string | number>;

/**
 * Translate an English text with the dictionary of the current language.
 * Placeholders are written ":name".
 */
export function translate(dictionary: Record<string, string>, key: string, replace: Replacements = {}): string {
    let text = dictionary[key] ?? key;

    // Longest names first, so that ":to" never eats the beginning of ":total".
    const names = Object.keys(replace).sort((a, b) => b.length - a.length);

    for (const name of names) {
        text = text.replaceAll(`:${name}`, String(replace[name]));
    }

    return text;
}

export function useTranslation(): { t: (key: string, replace?: Replacements) => string; locale: string } {
    const { meridian } = usePage<SharedProps>().props;

    return {
        locale: meridian.locale,
        t: (key, replace) => translate(meridian.translations, key, replace),
    };
}
